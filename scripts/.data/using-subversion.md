# Using Subversion (SVN) with WordPress Plugins

## Key Concepts

1. **SVN is a Release Repository**
   - Unlike Git, SVN is meant for releases, not daily development
   - Only push finished changes to avoid performance degradation
   - Each push triggers rebuild of all version zip files

2. **Directory Structure**
   - `/assets/` - Screenshots, plugin headers, and icons
   - `/tags/` - Released versions (e.g., 1.0, 1.1)
   - `/trunk/` - Latest development version
   - Note: `/branches/` is deprecated and no longer created by default

3. **Authentication**
   - Uses WordPress.org username (case-sensitive)
   - Requires SVN-specific password (set in Account Settings)
   - URL format: `https://plugins.svn.wordpress.org/plugin-name`

## Best Practices

1. **Development Workflow**
   - Don't use SVN for daily development
   - Keep trunk updated with latest code version
   - Always tag releases properly
   - Create tags from trunk, not direct uploads

2. **File Organization**
   - Don't put main plugin file in trunk subfolder
   - Keep screenshots in `/assets/`, not `/trunk/`
   - No zip files in repository
   - Don't include development files (e.g., .gitignore)

3. **Version Management**
   - Use semantic versioning
   - Tag all releases (e.g., `/tags/1.0/`)
   - Update stable tag in trunk's readme.txt
   - Version numbers in tag folders must match plugin version

## Basic Commands

1. **Initial Setup**
   ```bash
   mkdir my-local-dir
   svn co https://plugins.svn.wordpress.org/your-plugin-name my-local-dir
   ```

2. **Adding Files**
   ```bash
   svn add trunk/*
   svn ci -m 'Adding first version of my plugin'
   ```

3. **Updating Files**
   ```bash
   svn up                    # Update local copy
   svn stat                  # Check status
   svn diff                  # View changes
   svn ci -m "message"       # Commit changes
   ```

4. **Creating Tags**
   ```bash
   svn cp trunk tags/2.0
   svn ci -m "tagging version 2.0"
   ```

## Important Notes

1. **Release Process**
   - Update code in trunk
   - Update version numbers and stable tag
   - Create new tag from trunk
   - Commit changes

2. **Common Issues**
   - Username is case-sensitive
   - Access forbidden usually means auth issues
   - Large commits can take hours to process

3. **WordPress.org Integration**
   - Tags determine available versions
   - Assets are handled separately
   - Readme.txt must be properly formatted
   - Stable tag determines default download version