var dotenv = require('dotenv');
var Octokit = require('@octokit/rest').Octokit;
var fs = require('fs-extra');
var path = require('path');
var execSync = require('child_process').execSync;

// Initialize dotenv with path to scripts directory
dotenv.config({ path: path.join(__dirname, '.env') });

// GitHub configuration
var config = {
    owner: process.env.GITHUB_OWNER,
    repo: process.env.GITHUB_REPO,
    token: process.env.GITHUB_TOKEN,
    pluginFile: path.resolve(__dirname, '../radle-lite.php')
};

// Validate GitHub configuration
if (!config.token) {
    throw new Error('GITHUB_TOKEN is not set in .env file');
}
if (!config.owner) {
    throw new Error('GITHUB_OWNER is not set in .env file');
}
if (!config.repo) {
    throw new Error('GITHUB_REPO is not set in .env file');
}

var octokit = new Octokit({ auth: config.token });

/**
 * Execute git command and handle errors
 */
function gitExec(command, options) {
    try {
        return execSync(command, options || { stdio: 'inherit' });
    } catch (error) {
        throw new Error('Git command failed: ' + command + '\n' + error.message);
    }
}

/**
 * Check if there are any changes to commit
 */
function hasChanges() {
    try {
        const status = gitExec('git status --porcelain');
        return status.toString().trim().length > 0;
    } catch (error) {
        return false;
    }
}

/**
 * Handle git branch operations for commit only (no release)
 */
function handleGitCommit(callback) {
    console.log('\n🔄 Committing to develop branch...');

    try {
        // Make sure we're on develop branch
        gitExec('git checkout develop');
        
        // Stage all files, including new ones
        gitExec('git add -A');
        
        // Commit changes
        gitExec('git commit -m "Prepare release"');
        gitExec('git push origin develop');

        console.log('✓ Commit completed');
        
        if (callback) callback(null);
    } catch (error) {
        if (callback) callback(error);
    }
}

/**
 * Handle git branch operations for release
 */
function handleGitRelease(callback) {
    console.log('\n🔄 Managing git branches for release...');

    try {
        // First commit and push to develop if there are changes
        console.log('Pushing changes to develop branch...');
        
        // Make sure we're on develop branch
        gitExec('git checkout develop');
        
        // Only commit if there are changes
        if (hasChanges()) {
            // Stage all files, including new ones
            gitExec('git add -A');
            
            // Commit changes
            gitExec('git commit -m "Prepare release"');
            gitExec('git push origin develop');
        }

        // Switch to master
        console.log('Switching to master branch...');
        gitExec('git checkout master');

        // Pull latest master just in case
        console.log('Pulling latest master...');
        gitExec('git pull origin master');

        // Merge develop into master with strategy to prefer develop changes
        console.log('Merging develop into master...');
        try {
            gitExec('git merge develop');
        } catch (mergeError) {
            // If merge fails due to conflicts, use develop's version for all conflicts
            console.log('⚠️  Merge conflicts detected, resolving by accepting develop branch changes...');
            gitExec('git merge --abort');
            gitExec('git merge develop -X theirs');
        }

        // Push to master
        console.log('Pushing to master...');
        gitExec('git push origin master');

        // Switch back to develop
        console.log('Switching back to develop branch...');
        gitExec('git checkout develop');

        console.log('✓ Release branch management completed');
        
        if (callback) callback(null);
    } catch (error) {
        // If anything fails, try to get back to develop branch
        try {
            gitExec('git checkout develop');
        } catch (checkoutError) {
            // Ignore checkout error
        }
        if (callback) callback(error);
    }
}

/**
 * Handle git branch operations based on action type
 */
function handleGitBranches(callback, isRelease = false) {
    if (isRelease) {
        handleGitRelease(callback);
    } else {
        handleGitCommit(callback);
    }
}

/**
 * Create a new release on GitHub
 */
function createGithubRelease(zipFile, callback) {
    console.log('\n🚀 Creating GitHub release...');

    // Get current version
    var version;
    try {
        const content = fs.readFileSync(config.pluginFile, 'utf8');
        const versionMatch = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
        version = versionMatch ? versionMatch[1] : 'unknown';
    } catch (error) {
        if (callback) callback(new Error('Failed to read version: ' + error.message));
        return;
    }

    // Handle git branches first
    handleGitBranches(function(err) {
        if (err) {
            if (callback) callback(err);
            return;
        }

        // Create GitHub release
        octokit.repos.createRelease({
            owner: config.owner,
            repo: config.repo,
            tag_name: 'v' + version,
            name: 'Version ' + version,
            body: 'Release of version ' + version,
            draft: false,
            prerelease: false,
            target_commitish: 'master' // Create release from master branch
        }).then(function(releaseResponse) {
            // Upload release asset if zip file provided
            if (zipFile && fs.existsSync(zipFile)) {
                const zipContent = fs.readFileSync(zipFile);
                return octokit.repos.uploadReleaseAsset({
                    owner: config.owner,
                    repo: config.repo,
                    release_id: releaseResponse.data.id,
                    name: path.basename(zipFile),
                    data: zipContent
                });
            }
        }).then(function() {
            console.log('✓ GitHub release created successfully');
            if (callback) callback(null);
        }).catch(function(error) {
            if (callback) callback(new Error('Failed to create GitHub release: ' + error.message));
        });
    }, true);
}

/**
 * Test GitHub access and operations
 */
function testGitHubAccess(callback) {
    console.log('\n🔍 Testing GitHub configuration...');
    console.log('Testing with:');
    console.log('  - Owner:', config.owner);
    console.log('  - Repo:', config.repo);

    // First validate configuration
    if (!config.token) {
        var error = new Error('GitHub token not found. Please check your .env file.');
        if (callback) callback(error);
        return;
    }

    // Test GitHub token and repository access
    octokit.repos.get({
        owner: config.owner,
        repo: config.repo
    }).then(function(response) {
        console.log('\nTesting repository access...');
        console.log('  ✓ Repository access confirmed:', response.data.full_name);
        
        // Test branch access
        console.log('\nTesting branch access...');
        return octokit.repos.listBranches({
            owner: config.owner,
            repo: config.repo
        });
    }).then(function(response) {
        console.log('  ✓ Branch access confirmed. Found', response.data.length, 'branches');
        
        // Test release creation permissions
        console.log('\nTesting release access...');
        return octokit.repos.listReleases({
            owner: config.owner,
            repo: config.repo
        });
    }).then(function(response) {
        console.log('  ✓ Release access confirmed. Found', response.data.length, 'releases');
        console.log('\n✅ All GitHub tests passed successfully!');
        if (callback) callback(null);
    }).catch(function(error) {
        console.error('\n❌ GitHub test failed:');
        if (error.status === 404) {
            console.error('  - Repository not found:', config.owner + '/' + config.repo);
        } else if (error.status === 401) {
            console.error('  - Invalid GitHub token. Please check your GITHUB_TOKEN in .env');
        } else if (error.status === 403) {
            console.error('  - Permission denied. Your token may not have the required permissions.');
        } else {
            console.error('  -', error.message);
        }
        if (callback) callback(error);
    });
}

module.exports = {
    handleGitBranches: handleGitBranches,
    createGithubRelease: createGithubRelease,
    testGitHubAccess: testGitHubAccess
};
