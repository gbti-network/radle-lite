var dotenv = require('dotenv');
var fs = require('fs-extra');
var path = require('path');
var execSync = require('child_process').execSync;
var inquirer = require('inquirer');

// Initialize dotenv with path to scripts directory
dotenv.config({ path: path.join(__dirname, '.env') });

// SVN configuration
var config = {
    svnUrl: process.env.SVN_URL || 'https://plugins.svn.wordpress.org/radle-lite',
    buildDir: path.resolve(__dirname, '../build'),
    sourceDir: path.resolve(__dirname, '..'),
    svnDir: path.resolve(__dirname, '../svn'),
    svnUsername: process.env.SVN_USERNAME,
    svnPassword: process.env.SVN_PASSWORD,
    pluginSlug: 'radle-lite'
};

// Validate SVN configuration
if (!config.svnUsername) {
    throw new Error('SVN_USERNAME is not set in .env file');
}
if (!config.svnPassword) {
    throw new Error('SVN_PASSWORD is not set in .env file');
}

/**
 * Execute SVN command with credentials and timeout
 */
function svnExec(command, options) {
    try {
        var credentialCommand = command + ' --username ' + config.svnUsername + ' --password ' + config.svnPassword + ' --non-interactive --no-auth-cache';
        return execSync(credentialCommand, { 
            ...options,
            timeout: 10000 // 10 second timeout
        });
    } catch (error) {
        if (error.signal === 'SIGTERM') {
            throw new Error('SVN command timed out: ' + command);
        }
        throw new Error('SVN command failed: ' + command + '\n' + error.message);
    }
}

/**
 * Prepare SVN directory structure
 */
function prepareSvnDir(callback) {
    console.log('\n Preparing SVN directory...');

    // Ensure SVN directories exist
    fs.ensureDir(path.join(config.svnDir, 'trunk'), function(err) {
        if (err) {
            callback(err);
            return;
        }
        fs.ensureDir(path.join(config.svnDir, 'assets'), function(err) {
            if (err) {
                callback(err);
                return;
            }
            fs.ensureDir(path.join(config.svnDir, 'tags'), function(err) {
                if (err) {
                    callback(err);
                    return;
                }

                // Clean trunk directory
                fs.emptyDir(path.join(config.svnDir, 'trunk'), function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }
                    console.log('SVN directory prepared');
                    callback(null);
                });
            });
        });
    });
}

/**
 * Copy files to SVN directory
 */
function copyToSvn(callback) {
    console.log('\n Copying files to SVN directory...');

    // Copy build files to trunk
    fs.copy(
        path.join(config.buildDir, config.pluginSlug),
        path.join(config.svnDir, 'trunk'),
        {
            filter: function(src) {
                // Exclude development files
                return !src.includes('.git') && 
                       !src.includes('node_modules') &&
                       !src.includes('svn') &&
                       !src.includes('scripts') &&
                       !src.includes('.env');
            }
        },
        function(err) {
            if (err) {
                callback(err);
                return;
            }

            // Copy WordPress.org assets if they exist
            var wpOrgAssetsDir = path.join(config.sourceDir, '.wordpress-org');
            fs.pathExists(wpOrgAssetsDir, function(err, exists) {
                if (err) {
                    callback(err);
                    return;
                }
                if (exists) {
                    fs.copy(wpOrgAssetsDir, path.join(config.svnDir, 'assets'), function(err) {
                        if (err) {
                            callback(err);
                            return;
                        }

                        // Copy plugin assets if they exist (banner, icon, screenshots)
                        var pluginAssetsDir = path.join(config.sourceDir, 'assets');
                        fs.pathExists(pluginAssetsDir, function(err, exists) {
                            if (err) {
                                callback(err);
                                return;
                            }
                            if (exists) {
                                fs.readdir(pluginAssetsDir, function(err, files) {
                                    if (err) {
                                        callback(err);
                                        return;
                                    }
                                    var assetFiles = files;
                                    var count = 0;
                                    assetFiles.forEach(function(file) {
                                        if (file.match(/^(banner|icon|screenshot).*\.(png|jpg|jpeg|gif)$/i)) {
                                            fs.copy(
                                                path.join(pluginAssetsDir, file),
                                                path.join(config.svnDir, 'assets', file),
                                                function(err) {
                                                    if (err) {
                                                        callback(err);
                                                        return;
                                                    }
                                                    count++;
                                                    if (count === assetFiles.length) {
                                                        console.log('Files copied to SVN directory');
                                                        callback(null);
                                                    }
                                                }
                                            );
                                        } else {
                                            count++;
                                            if (count === assetFiles.length) {
                                                console.log('Files copied to SVN directory');
                                                callback(null);
                                            }
                                        }
                                    });
                                });
                            } else {
                                console.log('Files copied to SVN directory');
                                callback(null);
                            }
                        });
                    });
                } else {
                    console.log('Files copied to SVN directory');
                    callback(null);
                }
            });
        }
    );
}

/**
 * Create SVN tag
 */
function createSvnTag(version, callback) {
    console.log(` Creating SVN tag: ${version}...`);

    var tagPath = path.join(config.svnDir, 'tags', version);
    
    // Create tag directory
    fs.ensureDir(path.join(config.svnDir, 'tags'), function(err) {
        if (err) {
            callback(err);
            return;
        }

        // Remove tag if it exists
        fs.pathExists(tagPath, function(err, exists) {
            if (err) {
                callback(err);
                return;
            }
            if (exists) {
                fs.remove(tagPath, function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }

                    // Copy trunk to tag
                    fs.copy(path.join(config.svnDir, 'trunk'), tagPath, function(err) {
                        if (err) {
                            callback(err);
                            return;
                        }
                        console.log('SVN tag created');
                        callback(null);
                    });
                });
            } else {
                // Copy trunk to tag
                fs.copy(path.join(config.svnDir, 'trunk'), tagPath, function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }
                    console.log('SVN tag created');
                    callback(null);
                });
            }
        });
    });
}

/**
 * Commit changes to SVN
 */
function commitToSvn(version, callback) {
    console.log('\n Preparing SVN commit...');

    // First, check if we have SVN access
    svnExec(`svn info ${config.svnUrl}`, function(err) {
        if (err) {
            callback(err);
            return;
        }

        // Create a temporary directory for SVN checkout
        var tmpDir = path.join(config.buildDir, '.svn-tmp');
        fs.ensureDir(tmpDir, function(err) {
            if (err) {
                callback(err);
                return;
            }

            try {
                // Checkout the SVN repository
                svnExec(`svn checkout ${config.svnUrl} ${tmpDir}`, { cwd: tmpDir }, function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }

                    // Copy our prepared svn contents to the SVN checkout
                    fs.copy(config.svnDir, tmpDir, { overwrite: true }, function(err) {
                        if (err) {
                            callback(err);
                            return;
                        }

                        // SVN add all unversioned files
                        svnExec('svn add * --force', { cwd: tmpDir, shell: true }, function(err) {
                            if (err) {
                                callback(err);
                                return;
                            }

                            // Remove deleted files
                            svnExec('svn status | grep "^!" | sed "s/! *//" | xargs -I% svn rm %', { cwd: tmpDir, shell: true }, function(err) {
                                if (err) {
                                    callback(err);
                                    return;
                                }

                                // Commit changes
                                svnExec(`svn ci -m "Release version ${version}"`, { cwd: tmpDir }, function(err) {
                                    if (err) {
                                        callback(err);
                                        return;
                                    }
                                    console.log('Changes committed to WordPress.org');
                                    callback(null);
                                });
                            });
                        });
                    });
                });
            } finally {
                // Cleanup
                fs.remove(tmpDir, function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }
                });
            }
        });
    });
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

    // Check if SVN is installed
    if (!checkSvnAvailable()) {
        console.error('\n❌ SVN is not installed or not in PATH');
        console.error('Please install SVN and add it to your system PATH');
        console.error('You can download SVN from: https://tortoisesvn.net/downloads.html');
        if (callback) callback(new Error('SVN command not found'));
        return;
    }

    // First validate configuration
    if (!config.svnUsername || !config.svnPassword) {
        var error = new Error('Missing SVN credentials. Please check your .env file.');
        if (callback) callback(error);
        return;
    }

    console.log('Testing with:');
    console.log('  - SVN URL:', config.svnUrl);
    console.log('  - Username:', config.svnUsername);

    try {
        // Test SVN repository access
        console.log('\nTesting repository access...');
        try {
            svnExec(`svn info ${config.svnUrl}`);
            console.log('  ✓ Repository access confirmed');
        } catch (error) {
            if (error.message.includes('authorization failed')) {
                throw new Error('Authentication failed. Please check your SVN credentials.');
            } else if (error.message.includes('timed out')) {
                throw new Error('Connection timed out. Please check your network connection.');
            } else {
                throw error;
            }
        }

        // Test SVN list operations
        console.log('\nTesting directory listing...');
        try {
            svnExec(`svn list ${config.svnUrl}`);
            console.log('  ✓ Directory listing successful');
        } catch (error) {
            if (error.message.includes('timed out')) {
                throw new Error('Directory listing timed out. Please check your network connection.');
            } else {
                throw error;
            }
        }

        // Test SVN structure
        console.log('\nVerifying repository structure...');
        try {
            var dirs = svnExec(`svn list ${config.svnUrl}`).toString().split('\n');
            
            var hasAssets = dirs.includes('assets/');
            var hasTrunk = dirs.includes('trunk/');
            var hasTags = dirs.includes('tags/');

            if (!hasTrunk) {
                throw new Error('Missing trunk directory in SVN repository');
            }
            if (!hasTags) {
                throw new Error('Missing tags directory in SVN repository');
            }
            
            console.log('  ✓ Repository structure verified:');
            console.log('    - Trunk directory:', hasTrunk ? 'Present' : 'Missing');
            console.log('    - Tags directory:', hasTags ? 'Present' : 'Missing');
            console.log('    - Assets directory:', hasAssets ? 'Present' : 'Not required');
        } catch (error) {
            if (error.message.includes('timed out')) {
                throw new Error('Structure verification timed out. Please check your network connection.');
            } else {
                throw error;
            }
        }

        console.log('\n✅ All SVN tests passed successfully!');
        if (callback) callback(null);
    } catch (error) {
        console.error('\n❌ SVN test failed:');
        if (error.message.includes('authorization failed')) {
            console.error('  - Authentication failed. Please check your SVN credentials.');
        } else if (error.message.includes('timed out')) {
            console.error('  - Connection timed out. Please check your network connection.');
        } else if (error.message.includes('not found')) {
            console.error('  - Repository not found:', config.svnUrl);
        } else {
            console.error('  -', error.message);
        }
        if (callback) callback(error);
    }
}

/**
 * Main SVN release function
 */
function createSvnRelease(callback) {
    try {
        // Get current version
        var pluginFile = path.resolve(__dirname, '../radle-lite.php');
        fs.readFile(pluginFile, 'utf8', function(err, content) {
            if (err) {
                callback(err);
                return;
            }
            var versionMatch = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
            var version = versionMatch ? versionMatch[1] : 'unknown';

            // Confirm SVN release
            inquirer.prompt([
                {
                    type: 'confirm',
                    name: 'confirm',
                    message: `Ready to release version ${version} to WordPress.org?`,
                    default: false
                }
            ]).then(function(answers) {
                if (!answers.confirm) {
                    console.log('SVN release cancelled');
                    callback(null);
                    return;
                }

                prepareSvnDir(function(err) {
                    if (err) {
                        callback(err);
                        return;
                    }

                    copyToSvn(function(err) {
                        if (err) {
                            callback(err);
                            return;
                        }

                        createSvnTag(version, function(err) {
                            if (err) {
                                callback(err);
                                return;
                            }

                            commitToSvn(version, function(err) {
                                if (err) {
                                    callback(err);
                                    return;
                                }

                                console.log('\nSVN release completed successfully!');
                                callback(null);
                            });
                        });
                    });
                });
            });
        });
    } catch (error) {
        callback(new Error(`Failed to create SVN release: ${error.message}`));
    }
}

module.exports = {
    createSvnRelease: createSvnRelease,
    testSvnAccess: testSvnAccess
};