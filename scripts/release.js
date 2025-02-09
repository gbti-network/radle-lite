var github = require('./release-github');
var svn = require('./release-svn');

/**
 * Handle git branch operations for release
 */
function handleGitBranches(callback) {
    github.handleGitBranches(callback);
}

/**
 * Create a new release on GitHub
 */
function createGithubRelease(zipFile, callback) {
    github.createGithubRelease(zipFile, callback);
}

/**
 * Create a new release on SVN
 */
function createSvnRelease(callback) {
    svn.createSvnRelease(callback);
}

/**
 * Create a release on both GitHub and SVN
 */
function createCombinedRelease(zipFile, callback) {
    // First create GitHub release
    createGithubRelease(zipFile, function(err) {
        if (err) {
            if (callback) callback(err);
            return;
        }

        // If GitHub succeeds, create SVN release
        createSvnRelease(function(err) {
            if (callback) callback(err);
        });
    });
}

/**
 * Test GitHub access and operations
 */
function testGitHubAccess(callback) {
    github.testGitHubAccess(callback);
}

/**
 * Test SVN access and operations
 */
function testSvnAccess(callback) {
    svn.testSvnAccess(callback);
}

/**
 * Test both GitHub and SVN systems
 */
function testAllSystems(callback) {
    console.log('\n🔍 Starting system tests...');
    console.log('\n1. Testing GitHub Integration:');
    
    testGitHubAccess(function(err) {
        if (err) {
            if (callback) callback(err);
            return;
        }
        
        console.log('\n2. Testing SVN Integration:');
        testSvnAccess(function(err) {
            if (err) {
                if (callback) callback(err);
                return;
            }
            
            console.log('\n✅ All system tests completed successfully!');
            if (callback) callback(null);
        });
    });
}

module.exports = {
    handleGitBranches: handleGitBranches,
    createGithubRelease: createGithubRelease,
    createSvnRelease: createSvnRelease,
    createCombinedRelease: createCombinedRelease,
    testGitHubAccess: testGitHubAccess,
    testSvnAccess: testSvnAccess,
    testAllSystems: testAllSystems
};
