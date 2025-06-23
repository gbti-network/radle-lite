var dotenv = require('dotenv');
var fs = require('fs-extra');
var path = require('path');
var execSync = require('child_process').execSync;
var inquirer = require('inquirer');

// Initialize dotenv with path to scripts directory
dotenv.config({ path: path.join(__dirname, '.env') });

// Configuration
const config = {
    svnDir: path.resolve(__dirname, '..', 'svn'),
    buildDir: path.resolve(__dirname, '..', 'build'),
    svnUrl: process.env.SVN_URL || 'https://plugins.svn.wordpress.org/radle-lite',
    svnUsername: process.env.SVN_USERNAME,
    svnPassword: process.env.SVN_PASSWORD,
    pluginSlug: 'radle-lite',
    sourceDir: path.resolve(__dirname, '..')
};

// Validate config
if (!config.svnUsername || !config.svnPassword) {
    console.error('Error: Missing SVN credentials in environment variables. Check your .env file.');
    process.exit(1);
}

/**
 * Execute SVN command with proper authentication and timeout
 */
function svnExec(command, options = {}) {
    // Add authentication to command
    const authCommand = `${command} --username ${config.svnUsername} --password ${config.svnPassword} --non-interactive --no-auth-cache`;
    
    try {
        // Execute command with a longer timeout
        return execSync(authCommand, {
            ...options,
            stdio: options.stdio || 'pipe',
            maxBuffer: 10 * 1024 * 1024, // 10MB buffer
            timeout: 300000 // 5 minute timeout
        });
    } catch (error) {
        console.log(`SVN command failed: ${command}`);
        console.log(`Error: ${error.message}`);
        return null;
    }
}

/**
 * Run SVN command with diagnostics and timeout
 */
function svnDiagnostic(command, options) {
    console.log(` Running SVN command: ${command}`);
    try {
        // Add authentication to command but hide password in log
        const logCommand = `${command} --username ${config.svnUsername} --password ********`;
        console.log(` Full command: ${logCommand}`);

        // Add actual authentication to command
        const authCommand = `${command} --username ${config.svnUsername} --password ${config.svnPassword} --non-interactive --no-auth-cache`;
        
        const result = execSync(authCommand, {
            ...options,
            encoding: 'utf8',
            maxBuffer: 10 * 1024 * 1024, // 10MB buffer
            timeout: 300000 // 5 minute timeout
        });
        return result;
    } catch (error) {
        console.error(' SVN command failed with error:', error.message);
        if (error.stdout) console.log(' stdout:', error.stdout);
        if (error.stderr) console.log(' stderr:', error.stderr);
        throw error;
    }
}

/**
 * Prepare SVN directory structure
 */
async function prepareSvnDir() {
    console.log('\n Preparing SVN directory...');

    try {
        // Create SVN directory if it doesn't exist
        if (!fs.existsSync(config.svnDir)) {
            fs.mkdirSync(config.svnDir, { recursive: true });
        }

        // Check if this is an SVN working copy
        const isSvnWorkingCopy = fs.existsSync(path.join(config.svnDir, '.svn'));

        if (isSvnWorkingCopy) {
            console.log(' Cleaning existing SVN copy...');
            
            // First revert any local changes
            svnExec('svn revert . --recursive', { cwd: config.svnDir });
            
            // Remove unversioned items
            svnExec('svn cleanup', { cwd: config.svnDir });
            
            // Update to get latest
            console.log(' Updating from repository...');
            await updateSvn();
            
            // Remove all local files/dirs except .svn and assets
            fs.readdirSync(config.svnDir).forEach(item => {
                if (item !== '.svn' && item !== 'assets') {
                    fs.rmSync(path.join(config.svnDir, item), { recursive: true, force: true });
                }
            });
        } else {
            console.log(' Performing fresh SVN checkout...');
            svnExec(`svn checkout ${config.svnUrl} .`, { cwd: config.svnDir });
        }

        // Create standard directories
        ['trunk', 'tags', 'assets'].forEach(dir => {
            const dirPath = path.join(config.svnDir, dir);
            if (!fs.existsSync(dirPath)) {
                fs.mkdirSync(dirPath, { recursive: true });
            }
        });

        console.log('✓ SVN directory prepared');
    } catch (error) {
        console.error(' Error preparing SVN directory:', error.message);
        throw error;
    }
}

/**
 * Update SVN working copy
 */
async function updateSvn() {
    console.log(' Updating from repository...');
    try {
        // Update trunk and tags only, leave assets alone
        svnDiagnostic('svn update trunk tags', { cwd: config.svnDir });
    } catch (error) {
        console.error(' Error updating SVN:', error);
        throw error;
    }
}

/**
 * Copy files to SVN directory
 */
async function copyToSvn() {
    console.log('\n Copying files to SVN directory...');
    
    try {
        // Copy plugin files to trunk
        const buildDir = path.join(config.buildDir, config.pluginSlug);
        const trunkDir = path.join(config.svnDir, 'trunk');
        fs.copySync(buildDir, trunkDir);
        
        // Create new version tag
        const version = getCurrentVersion();
        const tagDir = path.join(config.svnDir, 'tags', version);
        fs.ensureDirSync(tagDir);
        fs.copySync(buildDir, tagDir);
        
        console.log('Files copied to SVN directory');
    } catch (error) {
        console.error(' Error copying files:', error);
        throw error;
    }
}

/**
 * Create SVN tag
 */
async function createSvnTag(version) {
    console.log(` Creating SVN tag: ${version}...`);

    var tagPath = path.join(config.svnDir, 'tags', version);
    
    // Create tag directory
    fs.ensureDirSync(path.join(config.svnDir, 'tags'));
    fs.ensureDirSync(tagPath);

    console.log('SVN tag created');
}

/**
 * Commit changes to SVN
 */
async function commitToSvn(message) {
    console.log(' Committing changes to WordPress.org...');
    
    try {
        // First commit the trunk
        console.log(' Committing trunk...');
        try {
            svnDiagnostic('svn add "trunk" --force', { cwd: config.svnDir });
        } catch (e) {
            // Directory might already be versioned
            console.log(' Note: trunk directory is already versioned');
        }
        svnDiagnostic('svn status trunk', { cwd: config.svnDir });
        svnDiagnostic('svn commit -m "' + message + ' (trunk)" trunk', { cwd: config.svnDir });
        
        // Then commit the tags
        console.log(' Committing tags...');
        try {
            svnDiagnostic('svn add "tags" --force', { cwd: config.svnDir });
        } catch (e) {
            // Directory might already be versioned
            console.log(' Note: tags directory is already versioned');
        }
        svnDiagnostic('svn status tags', { cwd: config.svnDir });
        svnDiagnostic('svn commit -m "' + message + ' (tags)" tags', { cwd: config.svnDir });
        
        console.log(' SVN commit completed successfully');
    } catch (error) {
        console.error(' Error during SVN commit:', error.message);
        if (error.stdout) console.log(' stdout:', error.stdout);
        if (error.stderr) console.log(' stderr:', error.stderr);
        throw error;
    }
}

/**
 * Check if SVN is available
 */
function checkSvnAvailable() {
    try {
        execSync('svn --version', { stdio: 'ignore' });
        return true;
    } catch (error) {
        return false;
    }
}

/**
 * Test SVN access and operations
 */
function testSvnAccess(callback) {
    console.log('\n🔍 Testing SVN configuration...');

    // Check if SVN is available
    if (!checkSvnAvailable()) {
        if (callback) callback(new Error('SVN command not found'));
        return;
    }

    // First validate configuration
    if (!config.svnUsername || !config.svnPassword) {
        var error = new Error('Missing SVN credentials in environment variables. Check your .env file.');
        if (callback) callback(error);
        return;
    }

    console.log('\nConfiguration:');
    console.log(`  - SVN URL: ${config.svnUrl}`);
    console.log(`  - Username: ${config.svnUsername}`);
    console.log(`  - Local SVN Directory: ${config.svnDir}`);

    // Validate local directory structure
    console.log('\nVerifying local directory structure...');
    try {
        // Create SVN directory if it doesn't exist
        if (!fs.existsSync(config.svnDir)) {
            fs.mkdirSync(config.svnDir, { recursive: true });
        }

        // Check for trunk, tags, and assets directories
        var hasTrunk = fs.existsSync(path.join(config.svnDir, 'trunk'));
        var hasTags = fs.existsSync(path.join(config.svnDir, 'tags'));
        var hasAssets = fs.existsSync(path.join(config.svnDir, 'assets'));

        console.log('  ✓ Local structure verified:');
        console.log(`    - Trunk directory: ${hasTrunk ? 'Present' : 'Will be created'}`);
        console.log(`    - Tags directory: ${hasTags ? 'Present' : 'Will be created'}`);
        console.log(`    - Assets directory: ${hasAssets ? 'Present' : 'Will be created'}`);

        // Test SVN repository access
        console.log('\nTesting repository access...');
        try {
            var svnInfoResult = svnExec(`svn info ${config.svnUrl}`);
            if (svnInfoResult) {
                console.log(svnInfoResult.toString());
                console.log('  ✓ Repository access confirmed');
            } else {
                console.log('  ⚠️ Could not verify repository access');
                console.log('  - This might be a new WordPress.org plugin');
                console.log('  - The repository will be created during first release');
            }
        } catch (error) {
            console.log('\n⚠️  Repository access error:');
            console.log('  - This might be a new WordPress.org plugin');
            console.log('  - The repository will be created during first release');
        }

        // Test SVN repository structure
        console.log('\nVerifying repository structure...');
        try {
            var svnListResult = svnExec(`svn list ${config.svnUrl}`);
            if (svnListResult) {
                var dirs = svnListResult.toString().split('\n');
                dirs = dirs.filter(dir => dir.trim() !== '');
                
                console.log(dirs.join('\n'));
                
                // Check for required directories
                var hasAssets = dirs.includes('assets/');
                var hasTags = dirs.includes('tags/');
                var hasTrunk = dirs.includes('trunk/');
                
                if (hasAssets && hasTags && hasTrunk) {
                    console.log('\n  ✓ Repository structure verified');
                } else {
                    console.log('\n  ⚠️  Repository structure incomplete:');
                    if (!hasAssets) console.log('     - Missing assets directory');
                    if (!hasTags) console.log('     - Missing tags directory');
                    if (!hasTrunk) console.log('     - Missing trunk directory');
                    console.log('     Will create missing directories during release');
                }
            } else {
                console.log('\n  ⚠️  Could not verify repository structure');
                console.log('     Will create structure during release');
            }
        } catch (error) {
            if (error.message && error.message.includes('timed out')) {
                throw new Error('Structure verification timed out. Please check your network connection.');
            } else {
                console.log('\n⚠️  Repository access error:');
                console.log('  - This might be a new WordPress.org plugin');
                console.log('  - The repository structure will be created during first release');
                console.log('  - Local structure is ready for initial commit');
            }
        }

        // Test local SVN working copy
        console.log('\nTesting local SVN working copy...');
        if (!fs.existsSync(path.join(config.svnDir, '.svn'))) {
            console.log('  ⚠️  No SVN working copy found, will create during release');
        } else {
            var svnInfoResult = svnExec('svn info', { cwd: config.svnDir });
            if (svnInfoResult && svnInfoResult.toString().includes(config.svnUrl)) {
                console.log('  ✓ Local SVN working copy verified');
            } else {
                console.log('  ⚠️  SVN working copy exists but points to wrong repository');
                console.log('     Will recreate during release');
            }
        }

        console.log('\n✅ SVN test completed successfully!');
        if (callback) callback(null);
    } catch (error) {
        console.error('\n❌ SVN test failed:');
        if (error.message && error.message.includes('authorization failed')) {
            console.error('  - Authentication failed. Please check your SVN credentials.');
        } else if (error.message && error.message.includes('timed out')) {
            console.error('  - Connection timed out. Please check your network connection.');
        } else if (error.message && error.message.includes('not found')) {
            console.error('  - SVN command not found. Please install SVN client.');
        } else {
            console.error(`  - ${error.message || 'Unknown error'}`);
        }
        if (callback) callback(error);
    }
}

/**
 * Get current version from plugin file
 */
function getCurrentVersion() {
    var pluginFile = path.resolve(__dirname, '../radle-lite.php');
    var content = fs.readFileSync(pluginFile, 'utf8');
    var versionMatch = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
    return versionMatch ? versionMatch[1] : null;
}

/**
 * Commit assets to SVN
 */
async function handleAssets() {
    console.log(' Committing WordPress.org assets...');
    
    try {
        const svnAssetsDir = path.join(config.svnDir, 'assets');
        
        if (fs.existsSync(svnAssetsDir)) {
            // Add and commit assets
            try {
                svnDiagnostic('svn add "assets" --force', { cwd: config.svnDir });
            } catch (e) {
                console.log(' Note: assets directory is already versioned');
            }
            
            svnDiagnostic('svn status assets', { cwd: config.svnDir });
            svnDiagnostic('svn commit -m "Update WordPress.org assets" assets', { cwd: config.svnDir });
            console.log(' ✓ Assets committed successfully');
        } else {
            console.log(' No assets directory found');
        }
    } catch (error) {
        console.error(' Error handling assets:', error.message);
        throw error;
    }
}

/**
 * Create a new release on SVN
 */
async function createSvnRelease() {
    console.log('\n🚀 Creating SVN release...');
    var currentVersion = getCurrentVersion();
    
    if (!currentVersion) {
        throw new Error('Could not determine current version');
    }

    console.log(' Using current version: ' + currentVersion);

    // Create SVN release with current version
    try {
        await prepareSvnDir();
        await copyToSvn();
        await createSvnTag(currentVersion);
        await commitToSvn(`Release version ${currentVersion}`);
        await handleAssets();
        console.log('✓ SVN release created successfully!');
    } catch (error) {
        throw error;
    }
}

module.exports = {
    createSvnRelease: createSvnRelease,
    testSvnAccess: testSvnAccess
};