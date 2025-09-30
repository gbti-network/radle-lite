// Import the release module
const release = require('./release');
const versionManager = require('./version');

// Store the original version for rollback
let originalVersion = null;

// Rollback helper function
async function rollbackOnFailure() {
    if (originalVersion) {
        console.log('\n⚠️  Attempting to rollback version changes...');
        try {
            await versionManager.rollbackVersion(originalVersion);
            console.log('✅ Version rollback successful');
        } catch (rollbackError) {
            console.error('❌ Version rollback failed:', rollbackError);
            console.log(`\n⚠️  Please manually revert version to ${originalVersion}`);
        }
    }
}

// Main async function to handle the release process
async function runRelease() {
    try {
        console.log('\n🚀 Starting release process...');

        // Capture original version before any changes
        originalVersion = await versionManager.getCurrentVersion();
        console.log(`\n📌 Current version: ${originalVersion}`);

        // Step 1: Update versions
        console.log('\nStep 1: Updating versions');
        const newVersion = await release.handleVersionUpdate();
        console.log(`\n✅ Version updated to ${newVersion}`);

        // Step 2: Test systems before release
        console.log('\nStep 2: Testing systems before release');

        release.testAllSystems(function(err) {
            if (err) {
                console.error('\n❌ System tests failed:', err);
                rollbackOnFailure().then(() => process.exit(1));
                return;
            }

            console.log('\n✅ System tests passed');

            // Step 3: Handle Git branches for release
            console.log('\nStep 3: Preparing Git branches');
            release.handleGitBranches(function(err) {
                if (err) {
                    console.error('\n❌ Failed to prepare Git branches:', err);
                    rollbackOnFailure().then(() => process.exit(1));
                    return;
                }

                console.log('\n✅ Git branches prepared');

                // Step 4: Create combined release
                console.log('\nStep 4: Creating release');
                release.createCombinedRelease(null, function(err) {
                    if (err) {
                        console.error('\n❌ Failed to create release:', err);
                        rollbackOnFailure().then(() => process.exit(1));
                        return;
                    }

                    console.log('\n🎉 Release completed successfully!');
                    // Clear originalVersion on success so we don't accidentally rollback
                    originalVersion = null;
                });
            });
        });
    } catch (error) {
        console.error('\n❌ Release process failed:', error);
        await rollbackOnFailure();
        process.exit(1);
    }
}

// Run the release process
runRelease();
