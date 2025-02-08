require('dotenv').config();
const fs = require('fs-extra');
const path = require('path');
const { execSync } = require('child_process');
const inquirer = require('inquirer');

// SVN configuration
const config = {
    svnUrl: 'https://plugins.svn.wordpress.org/radle-lite',
    buildDir: path.resolve(__dirname, '../build'),
    sourceDir: path.resolve(__dirname, '..'),
    svnDir: path.resolve(__dirname, '../svn'),
    svnUsername: process.env.SVN_USERNAME,
    svnPassword: process.env.SVN_PASSWORD,
    pluginSlug: 'radle-lite'
};

/**
 * Execute SVN command with credentials
 */
function svnExec(command, options = {}) {
    const credentialCommand = `${command} --username ${config.svnUsername} --password ${config.svnPassword}`;
    return execSync(credentialCommand, { ...options, stdio: 'inherit' });
}

/**
 * Prepare SVN directory structure
 */
async function prepareSvnDir() {
    console.log('\n Preparing SVN directory...');

    // Ensure SVN directories exist
    await fs.ensureDir(path.join(config.svnDir, 'trunk'));
    await fs.ensureDir(path.join(config.svnDir, 'assets'));
    await fs.ensureDir(path.join(config.svnDir, 'tags'));

    // Clean trunk directory
    await fs.emptyDir(path.join(config.svnDir, 'trunk'));

    console.log('SVN directory prepared');
}

/**
 * Copy files to SVN directory
 */
async function copyToSvn() {
    console.log('\n Copying files to SVN directory...');

    // Copy build files to trunk
    await fs.copy(
        path.join(config.buildDir, config.pluginSlug),
        path.join(config.svnDir, 'trunk'),
        {
            filter: (src) => {
                // Exclude development files
                return !src.includes('.git') && 
                       !src.includes('node_modules') &&
                       !src.includes('svn') &&
                       !src.includes('scripts') &&
                       !src.includes('.env');
            }
        }
    );

    // Copy WordPress.org assets if they exist
    const wpOrgAssetsDir = path.join(config.sourceDir, '.wordpress-org');
    if (await fs.pathExists(wpOrgAssetsDir)) {
        await fs.copy(wpOrgAssetsDir, path.join(config.svnDir, 'assets'));
    }

    // Copy plugin assets if they exist (banner, icon, screenshots)
    const pluginAssetsDir = path.join(config.sourceDir, 'assets');
    if (await fs.pathExists(pluginAssetsDir)) {
        const assetFiles = await fs.readdir(pluginAssetsDir);
        for (const file of assetFiles) {
            if (file.match(/^(banner|icon|screenshot).*\.(png|jpg|jpeg|gif)$/i)) {
                await fs.copy(
                    path.join(pluginAssetsDir, file),
                    path.join(config.svnDir, 'assets', file)
                );
            }
        }
    }

    console.log('Files copied to SVN directory');
}

/**
 * Create SVN tag
 */
async function createSvnTag(version) {
    console.log(` Creating SVN tag: ${version}...`);

    const tagPath = path.join(config.svnDir, 'tags', version);
    
    // Create tag directory
    await fs.ensureDir(path.join(config.svnDir, 'tags'));
    
    // Remove tag if it exists
    if (await fs.pathExists(tagPath)) {
        await fs.remove(tagPath);
    }

    // Copy trunk to tag
    await fs.copy(path.join(config.svnDir, 'trunk'), tagPath);

    console.log('SVN tag created');
}

/**
 * Commit changes to SVN
 */
async function commitToSvn(version) {
    console.log('\n Preparing SVN commit...');

    // First, check if we have SVN access
    try {
        svnExec(`svn info ${config.svnUrl}`);
    } catch (error) {
        throw new Error('Unable to access WordPress.org SVN. Please check your credentials.');
    }

    // Create a temporary directory for SVN checkout
    const tmpDir = path.join(config.buildDir, '.svn-tmp');
    await fs.ensureDir(tmpDir);

    try {
        // Checkout the SVN repository
        svnExec(`svn checkout ${config.svnUrl} ${tmpDir}`);

        // Copy our prepared svn contents to the SVN checkout
        await fs.copy(config.svnDir, tmpDir, { overwrite: true });

        // SVN add all unversioned files
        svnExec('svn add * --force', { 
            cwd: tmpDir,
            shell: true
        });

        // Remove deleted files
        svnExec('svn status | grep "^!" | sed "s/! *//" | xargs -I% svn rm %', { 
            cwd: tmpDir,
            shell: true 
        });

        // Commit changes
        svnExec(`svn ci -m "Release version ${version}"`, { cwd: tmpDir });

        console.log('Changes committed to WordPress.org');
    } finally {
        // Cleanup
        await fs.remove(tmpDir);
    }
}

/**
 * Main SVN release function
 */
async function createSvnRelease() {
    try {
        // Get current version
        const pluginFile = path.resolve(__dirname, '../radle-lite.php');
        const content = await fs.readFile(pluginFile, 'utf8');
        const versionMatch = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
        const version = versionMatch ? versionMatch[1] : 'unknown';

        // Confirm SVN release
        const { confirm } = await inquirer.prompt([
            {
                type: 'confirm',
                name: 'confirm',
                message: `Ready to release version ${version} to WordPress.org?`,
                default: false
            }
        ]);

        if (!confirm) {
            console.log('SVN release cancelled');
            return;
        }

        await prepareSvnDir();
        await copyToSvn();
        await createSvnTag(version);
        await commitToSvn(version);

        console.log('\nSVN release completed successfully!');
        
    } catch (error) {
        throw new Error(`Failed to create SVN release: ${error.message}`);
    }
}

module.exports = {
    createSvnRelease
};