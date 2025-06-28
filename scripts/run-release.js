// Load environment variables
const dotenv = require('dotenv');
const path = require('path');
dotenv.config({ path: path.join(__dirname, '.env') });

// Import the release module
const release = require('./release');

// Global variable to track version changes for rollback
let originalVersion = null;
let newVersion = null;

// Rollback function to revert version changes
function rollbackVersion() {
    if (originalVersion && newVersion && originalVersion !== newVersion) {
        console.log('\n🔄 Rolling back version changes...');
        try {
            const version = require('./version');
            // Force update back to original version
            const fs = require('fs-extra');
            const path = require('path');
            
            // Rollback main package.json
            const mainPackageJson = path.resolve(__dirname, '../package.json');
            const pkg = fs.readJsonSync(mainPackageJson);
            pkg.version = originalVersion;
            fs.writeJsonSync(mainPackageJson, pkg, { spaces: 2 });
            
            // Rollback scripts package.json
            const scriptsPackageJson = path.resolve(__dirname, './package.json');
            const scriptsPkg = fs.readJsonSync(scriptsPackageJson);
            scriptsPkg.version = originalVersion;
            fs.writeJsonSync(scriptsPackageJson, scriptsPkg, { spaces: 2 });
            
            // Rollback plugin file
            const pluginFile = path.resolve(__dirname, '../radle-lite.php');
            let content = fs.readFileSync(pluginFile, 'utf8');
            content = content.replace(
                /Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
                `Version: ${originalVersion}`
            );
            content = content.replace(
                /define\(\s*'RADLE_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\);/,
                `define( 'RADLE_VERSION', '${originalVersion}' );`
            );
            fs.writeFileSync(pluginFile, content, 'utf8');
            
            // Rollback readme.txt
            const readmeTxt = path.resolve(__dirname, '../readme.txt');
            content = fs.readFileSync(readmeTxt, 'utf8');
            content = content.replace(
                /Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
                `Stable tag: ${originalVersion}`
            );
            fs.writeFileSync(readmeTxt, content, 'utf8');
            
            console.log(`✅ Version rolled back from ${newVersion} to ${originalVersion}`);
        } catch (error) {
            console.error('❌ Failed to rollback version:', error.message);
        }
    }
}

// Main async function to handle the release process
async function runRelease() {
    try {
        console.log('\n🚀 Starting release process...');
        
        // Configure Git user if not already set
        const execSync = require('child_process').execSync;
        try {
            execSync('git config user.email "gbti.labs@gmail.com"', { stdio: 'inherit' });
            execSync('git config user.name "GBTI"', { stdio: 'inherit' });
            console.log('✓ Git user configuration set');
        } catch (error) {
            console.log('⚠️ Could not set Git configuration:', error.message);
        }
        
        // Step 1: Get current version and update versions
        console.log('\nStep 1: Updating versions');
        const version = require('./version');
        originalVersion = await version.getCurrentVersion();
        console.log(`Current version: ${originalVersion}`);
        
        newVersion = await release.handleVersionUpdate();
        console.log(`\n✅ Version updated to ${newVersion}`);
        
        // Step 2: Test systems before release
        console.log('\nStep 2: Testing systems before release');
        
        release.testAllSystems(function(err) {
            if (err) {
                console.error('\n❌ System tests failed:', err);
                rollbackVersion();
                process.exit(1);
            }
            
            console.log('\n✅ System tests passed');
            
            // Step 3: Handle Git branches for release
            console.log('\nStep 3: Preparing Git branches');
            release.handleGitBranches(function(err) {
                if (err) {
                    console.error('\n❌ Failed to prepare Git branches:', err);
                    rollbackVersion();
                    process.exit(1);
                }
                
                console.log('\n✅ Git branches prepared');
                
                // Step 4: Build deployment package
                console.log('\nStep 4: Building deployment package');
                const deploy = require('./deploy');
                
                deploy(function(err, zipFile) {
                    if (err) {
                        console.error('\n❌ Failed to build deployment package:', err);
                        rollbackVersion();
                        process.exit(1);
                    }
                    
                    console.log('\n✅ Deployment package created:', zipFile);
                    
                    // Step 5: Create combined release
                    console.log('\nStep 5: Creating release with zip file');
                    release.createCombinedRelease(zipFile, function(err) {
                        if (err) {
                            console.error('\n❌ Failed to create release:', err);
                            rollbackVersion();
                            process.exit(1);
                        }
                        
                        console.log('\n🎉 Release completed successfully!');
                    });
                });
            });
        });
    } catch (error) {
        console.error('\n❌ Release process failed:', error);
        rollbackVersion();
        process.exit(1);
    }
}

// Run the release process
runRelease();
