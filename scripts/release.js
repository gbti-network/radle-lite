// Load environment variables
var dotenv = require('dotenv');
var path = require('path');
dotenv.config({ path: path.join(__dirname, '.env') });

var github = require('./release-github');
var svn = require('./release-svn');
var version = require('./version');
var inquirer = require('inquirer');

/**
 * Prompt for version increment type
 */
async function promptVersionType() {
    const currentVersion = await version.getCurrentVersion();
    const semver = require('semver');
    
    const patchVersion = semver.inc(currentVersion, 'patch');
    const minorVersion = semver.inc(currentVersion, 'minor');
    const majorVersion = semver.inc(currentVersion, 'major');
    
    const { type } = await inquirer.prompt([{
        type: 'list',
        name: 'type',
        message: 'What kind of version update?',
        choices: [
            { name: `Patch (${currentVersion} → ${patchVersion}) - Backwards compatible bug fixes`, value: 'patch' },
            { name: `Minor (${currentVersion} → ${minorVersion}) - Backwards compatible features`, value: 'minor' },
            { name: `Major (${currentVersion} → ${majorVersion}) - Breaking changes`, value: 'major' }
        ]
    }]);
    return type;
}

/**
 * Handle version updates
 */
async function handleVersionUpdate() {
    try {
        const type = await promptVersionType();
        const newVersion = await version.updateVersions(type);
        return newVersion;
    } catch (error) {
        console.error('Failed to update versions:', error);
        throw error;
    }
}

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
async function createCombinedRelease(zipFile, callback) {
    try {
        // Create GitHub release
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
    } catch (error) {
        if (callback) callback(error);
    }
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
    handleGitBranches,
    createGithubRelease,
    createSvnRelease,
    createCombinedRelease,
    testGitHubAccess,
    testSvnAccess,
    testAllSystems,
    handleVersionUpdate
};
