// Import the release module
const release = require('./release');

// Main async function to handle the release process
async function runRelease() {
    try {
        console.log('\n🚀 Starting release process...');
        
        // Step 1: Update versions
        console.log('\nStep 1: Updating versions');
        const newVersion = await release.handleVersionUpdate();
        console.log(`\n✅ Version updated to ${newVersion}`);
        
        // Step 2: Test systems before release
        console.log('\nStep 2: Testing systems before release');
        
        release.testAllSystems(function(err) {
            if (err) {
                console.error('\n❌ System tests failed:', err);
                process.exit(1);
            }
            
            console.log('\n✅ System tests passed');
            
            // Step 3: Handle Git branches for release
            console.log('\nStep 3: Preparing Git branches');
            release.handleGitBranches(function(err) {
                if (err) {
                    console.error('\n❌ Failed to prepare Git branches:', err);
                    process.exit(1);
                }
                
                console.log('\n✅ Git branches prepared');
                
                // Step 4: Create combined release
                console.log('\nStep 4: Creating release');
                release.createCombinedRelease(null, function(err) {
                    if (err) {
                        console.error('\n❌ Failed to create release:', err);
                        process.exit(1);
                    }
                    
                    console.log('\n🎉 Release completed successfully!');
                });
            });
        });
    } catch (error) {
        console.error('\n❌ Release process failed:', error);
        process.exit(1);
    }
}

// Run the release process
runRelease();
