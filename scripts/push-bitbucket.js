/**
 * Script to push the repository to Bitbucket
 * 
 * This script will:
 * 1. Check if the repository exists on Bitbucket
 * 2. If not, create it
 * 3. Push the local repository to Bitbucket
 */

// Load environment variables from .env file in scripts directory
const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '.env') });
const { execSync } = require('child_process');
const fs = require('fs-extra');
const inquirer = require('inquirer');

// Configuration from environment variables
const BITBUCKET_ACCESS_TOKEN = process.env.BITBUCKET_ACCESS_TOKEN;
const BITBUCKET_WORKSPACE = process.env.BITBUCKET_WORKSPACE;
const BITBUCKET_REPO_NAME = process.env.BITBUCKET_REPO_NAME || 'radle-lite';
const REPO_ROOT = path.resolve(__dirname, '..');

// Check if Bitbucket credentials are configured
if (!BITBUCKET_ACCESS_TOKEN || !BITBUCKET_WORKSPACE) {
  console.error('\x1b[31mError: Bitbucket credentials are not configured in .env file\x1b[0m');
  console.log('Please add the following to your .env file:');
  console.log('BITBUCKET_ACCESS_TOKEN=your_bitbucket_access_token');
  console.log('BITBUCKET_WORKSPACE=your_bitbucket_workspace');
  console.log('BITBUCKET_REPO_NAME=radle-lite (or your preferred repository name)');
  console.log('\nTo create an Access Token:');
  console.log('1. Go to Bitbucket settings > Personal settings > Access tokens');
  console.log('2. Click "Create token"');
  console.log('3. Give it a name and set an expiration date');
  console.log('4. Select "Repository" read/write permissions');
  console.log('5. Click "Create" and copy the token (it will only be shown once)');
  process.exit(1);
}

/**
 * Execute a command and return the output
 * @param {string} command - Command to execute
 * @param {Object} options - Options for child_process.execSync
 * @returns {string} - Command output
 */
function executeCommand(command, options = {}) {
  try {
    const defaultOptions = { 
      cwd: REPO_ROOT, 
      stdio: 'pipe',
      encoding: 'utf8'
    };
    const mergedOptions = { ...defaultOptions, ...options };
    return execSync(command, mergedOptions).toString().trim();
  } catch (error) {
    if (options.ignoreError) {
      return error.stdout ? error.stdout.toString().trim() : '';
    }
    console.error(`\x1b[31mCommand failed: ${command}\x1b[0m`);
    console.error(error.message);
    if (error.stdout) console.log(error.stdout.toString());
    if (error.stderr) console.error(error.stderr.toString());
    process.exit(1);
  }
}

/**
 * Check if the repository is a git repository
 * @returns {boolean} - True if the repository is a git repository
 */
function isGitRepository() {
  try {
    return fs.existsSync(path.join(REPO_ROOT, '.git'));
  } catch (error) {
    return false;
  }
}

/**
 * Initialize git repository if not already initialized
 */
function initializeGitIfNeeded() {
  if (!isGitRepository()) {
    console.log('Initializing git repository...');
    executeCommand('git init -b main');
    executeCommand('git add .');
    executeCommand('git commit -m "Initial commit"');
  } else {
    // Check if we're on a branch, if not create main branch
    try {
      const currentBranch = executeCommand('git rev-parse --abbrev-ref HEAD');
      if (currentBranch === 'HEAD') {
        console.log('Not on any branch. Creating main branch...');
        executeCommand('git checkout -b main');
      }
    } catch (error) {
      console.log('Creating main branch...');
      executeCommand('git checkout -b main');
    }
  }
}

/**
 * Check if remote already exists
 * @param {string} remoteName - Name of the remote
 * @returns {boolean} - True if remote exists
 */
function remoteExists(remoteName) {
  try {
    const remotes = executeCommand('git remote');
    return remotes.split('\n').includes(remoteName);
  } catch (error) {
    return false;
  }
}

/**
 * Push to Bitbucket
 */
async function pushToBitbucket() {
  console.log('\x1b[36m=== Pushing to Bitbucket ===\x1b[0m');
  
  // Initialize git if needed
  initializeGitIfNeeded();
  
  // Construct the Bitbucket repository URL with access token
  const repoUrl = `https://x-token-auth:${BITBUCKET_ACCESS_TOKEN}@bitbucket.org/${BITBUCKET_WORKSPACE}/${BITBUCKET_REPO_NAME}.git`;
  
  // Check if remote exists
  const remoteName = 'bitbucket';
  if (remoteExists(remoteName)) {
    console.log(`Remote '${remoteName}' already exists. Updating URL...`);
    executeCommand(`git remote set-url ${remoteName} ${repoUrl}`);
  } else {
    console.log(`Adding remote '${remoteName}'...`);
    executeCommand(`git remote add ${remoteName} ${repoUrl}`);
  }
  
  // Check if there are any changes to commit
  const status = executeCommand('git status --porcelain');
  if (status) {
    // Ask user if they want to commit changes
    const { commitChanges } = await inquirer.prompt([{
      type: 'confirm',
      name: 'commitChanges',
      message: 'There are uncommitted changes. Do you want to commit them?',
      default: true
    }]);
    
    if (commitChanges) {
      // Ask for commit message
      const { commitMessage } = await inquirer.prompt([{
        type: 'input',
        name: 'commitMessage',
        message: 'Enter commit message:',
        default: 'Update plugin files'
      }]);
      
      console.log('Committing changes...');
      executeCommand('git add .');
      executeCommand(`git commit -m "${commitMessage}"`);
    }
  }
  
  // Push to Bitbucket
  console.log(`Pushing to Bitbucket (${BITBUCKET_WORKSPACE}/${BITBUCKET_REPO_NAME})...`);
  
  // Ask which branch to push
  const branches = executeCommand('git branch --format="%(refname:short)"').split('\n');
  const currentBranch = executeCommand('git rev-parse --abbrev-ref HEAD');
  
  let branchToPush = currentBranch;
  
  // If we're not on main branch but main exists, ask which branch to push
  if (branches.length > 1) {
    // Set default branch to 'main' if it exists, otherwise use current branch
    const defaultBranch = branches.includes('main') ? 'main' : currentBranch;
    
    const { selectedBranch } = await inquirer.prompt([{
      type: 'list',
      name: 'selectedBranch',
      message: 'Which branch do you want to push?',
      choices: branches,
      default: defaultBranch
    }]);
    branchToPush = selectedBranch;
  }
  
  // Push to Bitbucket
  try {
    executeCommand(`git push -u ${remoteName} ${branchToPush}`);
    console.log('\x1b[32mSuccessfully pushed to Bitbucket!\x1b[0m');
    console.log(`Repository URL: https://bitbucket.org/${BITBUCKET_WORKSPACE}/${BITBUCKET_REPO_NAME}`);
  } catch (error) {
    console.error('\x1b[31mFailed to push to Bitbucket.\x1b[0m');
    console.error('This could be because:');
    console.error('1. The repository does not exist on Bitbucket');
    console.error('2. You do not have permission to push to this repository');
    console.error('3. There are conflicts that need to be resolved');
    process.exit(1);
  }
}

// Run the script
pushToBitbucket().catch(error => {
  console.error('\x1b[31mError:\x1b[0m', error);
  process.exit(1);
});
