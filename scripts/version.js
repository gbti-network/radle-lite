const fs = require('fs-extra');
const path = require('path');
const semver = require('semver');

// Paths to files that need version updates
const mainPackageJson = path.resolve(__dirname, '../package.json');
const scriptsPackageJson = path.resolve(__dirname, './package.json');
const pluginFile = path.resolve(__dirname, '../radle-lite.php');
const readmeTxt = path.resolve(__dirname, '../readme.txt');
const readmeMd = path.resolve(__dirname, '../readme.md');

/**
 * Update version in a package.json file
 */
async function updatePackageVersion(filePath, newVersion) {
    const pkg = await fs.readJson(filePath);
    pkg.version = newVersion;
    await fs.writeJson(filePath, pkg, { spaces: 2 });
    console.log(`✓ Updated version in ${path.basename(filePath)} to ${newVersion}`);
}

/**
 * Update version in the main plugin file
 */
async function updatePluginVersion(newVersion) {
    let content = await fs.readFile(pluginFile, 'utf8');
    
    // Update plugin header version
    content = content.replace(
        /Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
        `Version: ${newVersion}`
    );
    
    // Update RADLE_VERSION constant
    content = content.replace(
        /define\(\s*'RADLE_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\);/,
        `define( 'RADLE_VERSION', '${newVersion}' );`
    );
    
    await fs.writeFile(pluginFile, content, 'utf8');
    console.log(`✓ Updated version in plugin file to ${newVersion}`);
}

/**
 * Update version in readme.txt file
 */
async function updateReadmeTxtVersion(newVersion) {
    let content = await fs.readFile(readmeTxt, 'utf8');
    content = content.replace(
        /Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
        `Stable tag: ${newVersion}`
    );
    await fs.writeFile(readmeTxt, content, 'utf8');
    console.log(`✓ Updated version in readme.txt to ${newVersion}`);
}

/**
 * Update version in readme.md file if it exists
 */
async function updateReadmeMdVersion(newVersion) {
    try {
        if (await fs.pathExists(readmeMd)) {
            let content = await fs.readFile(readmeMd, 'utf8');
            content = content.replace(
                /Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
                `Version: ${newVersion}`
            );
            await fs.writeFile(readmeMd, content, 'utf8');
            console.log(`✓ Updated version in readme.md to ${newVersion}`);
        }
    } catch (error) {
        console.log(`⚠️ Could not update readme.md: ${error.message}`);
    }
}

/**
 * Update version across all files
 */
async function updateVersions(type = 'patch') {
    try {
        console.log('\n📦 Updating version numbers...');
        
        // Read current version from main package.json
        const pkg = await fs.readJson(mainPackageJson);
        const currentVersion = pkg.version;
        
        // Calculate new version
        const newVersion = semver.inc(currentVersion, type);
        if (!newVersion) {
            throw new Error('Invalid version increment type');
        }

        // Update all files
        await updatePackageVersion(mainPackageJson, newVersion);
        await updatePackageVersion(scriptsPackageJson, newVersion);
        await updatePluginVersion(newVersion);
        await updateReadmeTxtVersion(newVersion);
        await updateReadmeMdVersion(newVersion);

        console.log(`\n✅ Successfully updated all versions from ${currentVersion} to ${newVersion}`);
        return newVersion;
    } catch (error) {
        console.error('\n❌ Error updating versions:', error.message);
        throw error;
    }
}

/**
 * Get current version from main package.json
 */
async function getCurrentVersion() {
    const pkg = await fs.readJson(mainPackageJson);
    return pkg.version;
}

/**
 * Rollback version to a specific version across all files
 */
async function rollbackVersion(targetVersion) {
    try {
        console.log(`\n🔄 Rolling back version to ${targetVersion}...`);

        // Update all files back to the target version
        await updatePackageVersion(mainPackageJson, targetVersion);
        await updatePackageVersion(scriptsPackageJson, targetVersion);
        await updatePluginVersion(targetVersion);
        await updateReadmeTxtVersion(targetVersion);
        await updateReadmeMdVersion(targetVersion);

        console.log(`\n✅ Successfully rolled back all versions to ${targetVersion}`);
        return targetVersion;
    } catch (error) {
        console.error('\n❌ Error rolling back versions:', error.message);
        throw error;
    }
}

module.exports = {
    updateVersions,
    getCurrentVersion,
    rollbackVersion
};
