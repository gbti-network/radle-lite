const dotenv = require('dotenv');
const inquirer = require('inquirer');
const fs = require('fs-extra');
const path = require('path');
const deploy = require('./deploy');
const semver = require('semver');
const release = require('./release');
const glob = require('glob');

// Initialize dotenv
dotenv.config();

// Configuration
const config = {
    pluginSlug: 'radle-lite',
    sourceDir: path.resolve(__dirname, '..'),
    buildDir: path.resolve(__dirname, '../build'),
    distDir: path.resolve(__dirname, '../dist'),
    mainFile: path.resolve(__dirname, '../radle-lite.php'),
    readmeFile: path.resolve(__dirname, '../readme.txt'),
    // Files and directories to exclude from the build
    exclude: [
        // Build and distribution
        'build',
        'build/**',
        'dist',
        'dist/**',
        // Version control and WordPress.org
        '.git',
        '.git/**',
        '.svn',
        '.svn/**',
        'svn',
        'svn/**',
        // Development files
        'node_modules',
        'node_modules/**',
        'scripts',
        'scripts/**',
        // Project files
        '.product',
        '.product/**',
        '.snapshots',
        '.snapshots/**',
        // Environment and config files
        '.env',
        '.env.*',
        'package.json',
        'package-lock.json',
        '.gitignore',
        '.svnignore'
    ]
};

// Store file backups
var fileBackups = {};

/**
 * Read current version from plugin file
 */
function getCurrentVersion() {
    const content = fs.readFileSync(config.mainFile, 'utf8');
    const match = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
    return match ? match[1] : null;
}

/**
 * Backup file contents before making changes
 */
function backupFiles() {
    console.log('\n Backing up files...');
    try {
        fileBackups = {
            mainFile: fs.readFileSync(config.mainFile, 'utf8'),
            readmeFile: fs.readFileSync(config.readmeFile, 'utf8')
        };
        console.log(' Files backed up successfully');
    } catch (error) {
        throw new Error('Failed to backup files: ' + error.message);
    }
}

/**
 * Restore files from backup
 */
function rollbackFiles() {
    console.log('\n Rolling back file changes...');
    try {
        if (fileBackups.mainFile) {
            fs.writeFileSync(config.mainFile, fileBackups.mainFile);
        }
        if (fileBackups.readmeFile) {
            fs.writeFileSync(config.readmeFile, fileBackups.readmeFile);
        }
        console.log(' Files rolled back successfully');
    } catch (error) {
        console.error(' Failed to rollback files:', error.message);
    }
}

/**
 * Update version in files
 */
function updateVersions(newVersion) {
    try {
        // Backup files before making changes
        backupFiles();

        // Update main plugin file header
        let content = fs.readFileSync(config.mainFile, 'utf8');
        content = content.replace(
            /(Version:\s*)([0-9]+\.[0-9]+\.[0-9]+)/,
            '$1' + newVersion
        );
        
        // Update PHP constant
        content = content.replace(
            /(define\s*\(\s*['"]RADLE_VERSION['"]\s*,\s*['"])([0-9]+\.[0-9]+\.[0-9]+)(['"])/,
            '$1' + newVersion + '$3'
        );
        fs.writeFileSync(config.mainFile, content);

        // Update readme.txt
        content = fs.readFileSync(config.readmeFile, 'utf8');
        content = content.replace(
            /(Stable tag:\s*)([0-9]+\.[0-9]+\.[0-9]+)/,
            '$1' + newVersion
        );
        fs.writeFileSync(config.readmeFile, content);
    } catch (error) {
        // If anything fails during version update, rollback
        rollbackFiles();
        throw error;
    }
}

/**
 * Calculate new version based on current version and release type
 */
function calculateNewVersion(currentVersion, releaseType) {
    return semver.inc(currentVersion, releaseType);
}

function shouldExclude(filepath) {
    const normalizedPath = filepath.replace(/\\/g, '/');
    
    // Direct match or subdirectory match for any exclude pattern
    return config.exclude.some(pattern => {
        if (pattern.endsWith('/**')) {
            const dirPath = pattern.slice(0, -3);
            return normalizedPath === dirPath || normalizedPath.startsWith(dirPath + '/');
        }
        return normalizedPath === pattern || normalizedPath.startsWith(pattern + '/');
    });
}

/**
 * Copy plugin files to build directory
 */
function copyFiles(callback, silent) {
    if (!silent) console.log('\nCopying plugin files...');
    try {
        // Clean build directory first
        fs.emptyDirSync(config.buildDir);
        console.log(' ✓ Cleaned build directory');

        // Create plugin directory in build
        const targetDir = path.join(config.buildDir, config.pluginSlug);
        fs.ensureDirSync(targetDir);

        // Walk through the source directory
        function processDirectory(currentPath) {
            const items = fs.readdirSync(currentPath);
            
            for (const item of items) {
                const fullPath = path.join(currentPath, item);
                const relativePath = path.relative(config.sourceDir, fullPath);
                
                // Skip if path should be excluded
                if (shouldExclude(relativePath)) {
                    if (!silent) console.log('  ✓ Skipping:', relativePath);
                    continue;
                }

                const stat = fs.statSync(fullPath);
                if (stat.isDirectory()) {
                    // Create directory in target
                    const targetPath = path.join(targetDir, relativePath);
                    fs.ensureDirSync(targetPath);
                    if (!silent) console.log('  ✓ Created directory:', relativePath);
                    
                    // Process subdirectory
                    processDirectory(fullPath);
                } else {
                    // Copy file
                    const targetPath = path.join(targetDir, relativePath);
                    fs.copySync(fullPath, targetPath);
                    if (!silent) console.log('  ✓ Copied:', relativePath);
                }
            }
        }

        // Start processing from source directory
        processDirectory(config.sourceDir);

        if (!silent) console.log('\n✓ All files copied successfully');
        if (callback) callback(null);
    } catch (error) {
        if (callback) callback(error);
    }
}

/**
 * Main build function
 */
function build(callback) {
    var currentVersion;
    
    try {
        currentVersion = getCurrentVersion();
        if (!currentVersion) {
            throw new Error('Could not determine current version');
        }

        inquirer.prompt([
            {
                type: 'list',
                name: 'action',
                message: 'What would you like to do?',
                choices: [
                    { name: '1. Generate Build Files Only', value: 'build' },
                    { name: '2. Build and Commit to GitHub', value: 'commit' },
                    { name: '3. Build and Make New Release to GitHub', value: 'release' },
                    { name: '4. Build and Make New Release to SVN', value: 'svn' },
                    { name: '5. Build and Make New Release to GitHub & SVN', value: 'both' },
                    { name: '6. Test All Systems (Dry Run)', value: 'test' }
                ]
            }
        ]).then(function(answers) {
            var action = answers.action;
            var newVersion = currentVersion;

            function handleBuild() {
                copyFiles(function(err) {
                    if (err) throw err;

                    deploy(function(err, zipFile) {
                        if (err) throw err;

                        // Handle different actions
                        switch (action) {
                            case 'commit':
                                release.handleGitBranches(function(err) {
                                    if (err) {
                                        rollbackOnError(err, newVersion, currentVersion);
                                    } else {
                                        console.log('\n Build process completed successfully!');
                                    }
                                }, false); // Explicitly pass false for commit
                                break;
                            case 'release':
                                release.handleGitBranches(function(err) {
                                    if (err) {
                                        rollbackOnError(err, newVersion, currentVersion);
                                        return;
                                    }
                                    release.createGithubRelease(zipFile, function(err) {
                                        if (err) {
                                            rollbackOnError(err, newVersion, currentVersion);
                                        } else {
                                            console.log('\n Build process completed successfully!');
                                        }
                                    });
                                }, true); // Pass true for release
                                break;
                            case 'svn':
                                release.createSvnRelease(function(err) {
                                    if (err) {
                                        rollbackOnError(err, newVersion, currentVersion);
                                    } else {
                                        console.log('\n Build process completed successfully!');
                                    }
                                });
                                break;
                            case 'both':
                                release.createCombinedRelease(zipFile, function(err) {
                                    if (err) {
                                        rollbackOnError(err, newVersion, currentVersion);
                                    } else {
                                        console.log('\n Build process completed successfully!');
                                    }
                                });
                                break;
                            case 'test':
                                console.log('\n Starting system test (dry run)...');
                                release.testAllSystems(function(err) {
                                    if (err) {
                                        console.error(' System test failed:', err.message);
                                    } else {
                                        console.log('\n All system tests completed successfully!');
                                    }
                                });
                                break;
                            default:
                                console.log('\n Build process completed successfully!');
                        }
                    });
                });
            }

            if (action === 'release' || action === 'svn' || action === 'both') {
                inquirer.prompt([
                    {
                        type: 'list',
                        name: 'releaseType',
                        message: 'What type of release is this?',
                        choices: [
                            { name: '1. Major (Breaking Changes)', value: 'major' },
                            { name: '2. Minor (New Features)', value: 'minor' },
                            { name: '3. Patch (Bug Fixes)', value: 'patch' }
                        ]
                    }
                ]).then(function(versionAnswer) {
                    newVersion = calculateNewVersion(currentVersion, versionAnswer.releaseType);
                    
                    inquirer.prompt([
                        {
                            type: 'confirm',
                            name: 'confirmVersion',
                            message: 'Current version is ' + currentVersion + '. Update to ' + newVersion + '?',
                            default: true
                        }
                    ]).then(function(confirmAnswer) {
                        if (!confirmAnswer.confirmVersion) {
                            console.log(' Version update cancelled');
                            return;
                        }

                        updateVersions(newVersion);
                        console.log(' Updated version to ' + newVersion);
                        handleBuild();
                    });
                });
            } else {
                handleBuild();
            }
        });

    } catch (error) {
        console.error('\n Error during build:', error.message);
        process.exit(1);
    }
}

function rollbackOnError(error, newVersion, currentVersion) {
    if (newVersion !== currentVersion) {
        console.error('\n Release failed, rolling back version changes...');
        rollbackFiles();
        console.log(' Version rolled back to', currentVersion);
    }
    console.error('\n Error:', error.message);
}

// Export the build function
module.exports = build;

// Run if called directly
if (require.main === module) {
    // Check for command line arguments
    var args = process.argv.slice(2);
    if (args.length > 0) {
        var action = args[0].replace('--', '');
        switch (action) {
            case 'test':
                console.log('\n Starting system test (dry run)...');
                console.log('Building test package...');
                
                // Run deploy in silent mode for tests
                var silent = true;
                copyFiles(function(err) {
                    if (err) {
                        console.error('\n Build test failed:', err.message);
                        process.exit(1);
                    }
                    console.log(' Build package created');
                    
                    release.testAllSystems(function(err) {
                        if (err) {
                            console.error('\n System test failed:', err.message);
                            process.exit(1);
                        } else {
                            console.log('\n All system tests completed successfully!');
                            process.exit(0);
                        }
                    });
                }, silent);
                break;
            case 'release':
            case 'svn':
            case 'both':
                // Start the build process with the specified action
                build(function(err) {
                    if (err) {
                        console.error(' Build failed:', err.message);
                        process.exit(1);
                    }
                    process.exit(0);
                });
                break;
            default:
                console.error(' Unknown command:', action);
                process.exit(1);
        }
    } else {
        // No arguments, show interactive menu
        build(function(err) {
            if (err) {
                console.error(' Build failed:', err.message);
                process.exit(1);
            }
            process.exit(0);
        });
    }
}
