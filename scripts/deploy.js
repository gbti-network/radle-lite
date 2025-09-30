/**
 * Radle Lite Deploy Script
 * 
 * Creates a deployable zip file of the plugin with proper WordPress structure.
 * - Excludes development files and directories
 * - Ensures proper folder structure
 * - Generates distribution-ready zip file
 */

const fs = require('fs-extra');
const path = require('path');
const archiver = require('archiver');
const glob = require('glob');

// Configuration
const config = {
    pluginSlug: 'radle-lite',
    sourceDir: path.resolve(__dirname, '..'),
    buildDir: path.resolve(__dirname, '../build'),
    distDir: path.resolve(__dirname, '../dist'),
    svnDir: path.resolve(__dirname, '../svn'),
    exclude: [
        // Build and distribution
        'build/**',
        'dist/**',
        // Version control and WordPress.org
        '.git/**',
        '.github/**',
        '.svn/**',
        'svn/**',
        // Development files
        'node_modules/**',
        'scripts/**',
        '.vscode/**',
        // Documentation and config
        '**/README.md',
        '**/readme.md',
        '**/Readme.md',
        '.*',
        '*.log',
        'package.json',
        'package-lock.json',
        // Project files
        '.product/**',
        '.snapshots/**',
        '.data/**',
        '.claude/**',
        'CLAUDE.md'
    ]
};

/**
 * Clean up build and dist directories
 */
async function cleanDirectories() {
    console.log('Cleaning directories...');
    await fs.remove(config.buildDir);
    await fs.remove(config.distDir);
    await fs.ensureDir(config.buildDir);
    await fs.ensureDir(config.distDir);
    console.log('✓ Directories cleaned');
}

/**
 * Backup SVN assets directory
 */
async function backupSvnAssets() {
    const svnAssetsDir = path.join(config.svnDir, 'assets');
    const assetsBackupDir = path.join(config.buildDir, '_assets_backup');
    
    if (fs.existsSync(svnAssetsDir)) {
        console.log(' Backing up SVN assets directory...');
        await fs.ensureDir(assetsBackupDir);
        await fs.copy(svnAssetsDir, assetsBackupDir);
        console.log(' ✓ SVN assets backed up');
    }
}

/**
 * Copy plugin files to build directory
 */
async function copyFiles() {
    console.log('\nCopying plugin files...');
    
    // Get all files except excluded ones
    const files = glob.sync('**/*', {
        cwd: config.sourceDir,
        nodir: true,
        ignore: config.exclude,
        dot: false
    });

    // Create plugin directory in build
    const pluginBuildDir = path.join(config.buildDir, config.pluginSlug);
    await fs.ensureDir(pluginBuildDir);

    // Copy each file
    for (const file of files) {
        const sourcePath = path.join(config.sourceDir, file);
        const destPath = path.join(pluginBuildDir, file);
        
        await fs.ensureDir(path.dirname(destPath));
        await fs.copy(sourcePath, destPath);
        console.log(`✓ Copied: ${file}`);
    }
    
    console.log('✓ Files copied successfully');
}

/**
 * Create zip archive
 */
async function createZip() {
    console.log('\nCreating zip archive...');
    
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const zipFileName = `${config.pluginSlug}-${timestamp}.zip`;
    const zipFilePath = path.join(config.distDir, zipFileName);
    
    const output = fs.createWriteStream(zipFilePath);
    const archive = archiver('zip', {
        zlib: { level: 9 }
    });

    archive.pipe(output);
    archive.directory(path.join(config.buildDir, config.pluginSlug), false);

    await archive.finalize();

    const stats = fs.statSync(zipFilePath);
    const fileSizeInMB = (stats.size / 1024 / 1024).toFixed(2);
    
    console.log(`✓ Created: ${zipFileName}`);
    console.log(`✓ Size: ${fileSizeInMB} MB`);
    
    console.log('\n✅ Deployment package created successfully!');
    console.log(`📦 Package location: ${zipFilePath}`);
}

/**
 * Main deploy function
 */
async function deploy(callback) {
    try {
        await cleanDirectories();
        await backupSvnAssets();
        await copyFiles();
        await createZip();
        if (callback) callback(null);
    } catch (error) {
        console.error('\n❌ Error during deployment:', error);
        if (callback) callback(error);
    }
}

// Run deploy if called directly
if (require.main === module) {
    deploy(function(err) {
        if (err) process.exit(1);
    });
} else {
    module.exports = deploy;
}
