/**
 * Script to push the repository to Codeberg
 * 
 * This script will:
 * 1. Check if the repository exists on Codeberg
 * 2. If not, create it
 * 3. Push the local repository to Codeberg
 */

// Load environment variables from .env file in scripts directory
const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '.env') });
const { execSync } = require('child_process');
const fs = require('fs-extra');
const inquirer = require('inquirer');

// Configuration from environment variables
const CODEBERG_ACCESS_TOKEN = process.env.CODEBERG_ACCESS_TOKEN;
const CODEBERG_USERNAME = process.env.CODEBERG_USERNAME || 'gbti-network';
const CODEBERG_REPO_NAME = process.env.CODEBERG_REPO_NAME || 'radle-lite';
const REPO_ROOT = path.resolve(__dirname, '..');

// Check if Codeberg credentials are configured
if (!CODEBERG_ACCESS_TOKEN) {
  console.error('\x1b[31mError: Codeberg credentials are not configured in .env file\x1b[0m');
  console.log('Please add the following to your .env file:');
  console.log('CODEBERG_ACCESS_TOKEN=your_codeberg_access_token');
  console.log('CODEBERG_USERNAME=gbti-network (or your preferred username)');
  console.log('CODEBERG_REPO_NAME=radle-lite (or your preferred repository name)');
  console.log('\nTo create an Access Token:');
  console.log('1. Go to Codeberg > Settings > Applications');
  console.log('2. Generate a new token');
  console.log('3. Give it a description and select the appropriate scopes (repo)');
  console.log('4. Copy the token (it will only be shown once)');
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
  }
}

/**
 * Ensure main branch exists
 */
function ensureMainBranchExists() {
  try {
    // Get list of branches
    const branches = executeCommand('git branch').split('\n').map(b => b.trim().replace('* ', ''));
    
    // Check if main branch exists
    if (!branches.includes('main')) {
      console.log('Creating main branch...');
      
      // Create main branch
      // First, get current branch
      const currentBranch = executeCommand('git rev-parse --abbrev-ref HEAD');
      
      if (currentBranch === 'master' || currentBranch === 'develop') {
        // Create main from current branch
        executeCommand('git checkout -b main');
        console.log(`Created main branch from ${currentBranch}`);
      } else {
        // Create and checkout main branch
        executeCommand('git branch main');
        executeCommand('git checkout main');
        console.log('Created and checked out main branch');
      }
    } else {
      // Make sure we're on main branch
      const currentBranch = executeCommand('git rev-parse --abbrev-ref HEAD');
      if (currentBranch !== 'main') {
        console.log(`Switching from ${currentBranch} to main branch...`);
        executeCommand('git checkout main');
      }
    }
  } catch (error) {
    console.error('Error ensuring main branch exists:', error);
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
 * Push to Codeberg
 */
async function pushToCodeberg() {
  console.log('\x1b[36m=== Pushing to Codeberg ===\x1b[0m');
  
  // Initialize git if needed
  initializeGitIfNeeded();
  
  // Ensure main branch exists
  ensureMainBranchExists();
  
  // Construct the Codeberg repository URL with access token
  // For Codeberg, we use the format: https://USERNAME:TOKEN@codeberg.org/USERNAME/REPO.git
  const repoUrl = `https://${CODEBERG_USERNAME}:${CODEBERG_ACCESS_TOKEN}@codeberg.org/${CODEBERG_USERNAME}/${CODEBERG_REPO_NAME}.git`;
  
  // Check if remote exists
  const remoteName = 'codeberg';
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
  
  // Push to Codeberg
  console.log(`Pushing to Codeberg (${CODEBERG_USERNAME}/${CODEBERG_REPO_NAME})...`);
  
  // Get current branch
  const currentBranch = executeCommand('git rev-parse --abbrev-ref HEAD');
  
  // Default to main branch
  let branchToPush = 'main';
  
  // If we're not on the main branch, ask which branch to push
  if (currentBranch !== 'main') {
    console.log(`Currently on branch: ${currentBranch}`);
    console.log('The default branch for Codeberg is: main');
    
    const { pushCurrentBranch } = await inquirer.prompt([{
      type: 'confirm',
      name: 'pushCurrentBranch',
      message: `Do you want to push the current branch (${currentBranch}) instead of main?`,
      default: false
    }]);
    
    if (pushCurrentBranch) {
      branchToPush = currentBranch;
    } else {
      // Make sure we're on main branch before pushing
      if (currentBranch !== 'main') {
        console.log('Switching to main branch...');
        executeCommand('git checkout main');
      }
    }
  }
  
  // Push to Codeberg
  try {
    executeCommand(`git push -u ${remoteName} ${branchToPush}`);
    console.log('\x1b[32mSuccessfully pushed to Codeberg!\x1b[0m');
    console.log(`Repository URL: https://codeberg.org/${CODEBERG_USERNAME}/${CODEBERG_REPO_NAME}`);
  } catch (error) {
    console.error('\x1b[31mFailed to push to Codeberg.\x1b[0m');
    console.error('This could be because:');
    console.error('1. The repository does not exist on Codeberg');
    console.error('2. You do not have permission to push to this repository');
    console.error('3. There are conflicts that need to be resolved');
    process.exit(1);
  }
}

// Run the script
pushToCodeberg().catch(error => {
  console.error('\x1b[31mError:\x1b[0m', error);
  process.exit(1);
});
