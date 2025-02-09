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
        '.snapshots/**'
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
    
    // Add the entire plugin directory
    archive.directory(path.join(config.buildDir, config.pluginSlug), config.pluginSlug);
    
    await new Promise((resolve, reject) => {
        output.on('close', resolve);
        archive.on('error', reject);
        archive.finalize();
    });

    console.log(`✓ Created: ${zipFileName}`);
    console.log(`✓ Size: ${(fs.statSync(zipFilePath).size / 1024 / 1024).toFixed(2)} MB`);
    
    return zipFilePath;
}

/**
 * Main deploy function
 */
async function deploy(callback) {
    try {
        console.log('🚀 Starting deployment process...\n');
        
        await cleanDirectories();
        await copyFiles();
        const zipFile = await createZip();
        
        console.log('\n✅ Deployment package created successfully!');
        console.log(`📦 Package location: ${zipFile}`);
        
        if (callback) callback(null, zipFile);
    } catch (error) {
        console.error('\n❌ Error during deployment:', error.message);
        if (callback) callback(error);
        else process.exit(1);
    }
}

// Run deploy if called directly
if (require.main === module) {
    deploy(function(err) {
        if (err) process.exit(1);
        process.exit(0);
    });
}

module.exports = deploy;
