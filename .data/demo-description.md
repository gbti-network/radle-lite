# Radle Demo Plugin

Radle is a powerful WordPress plugin that seamlessly integrates Reddit's discussion platform into your WordPress site. By connecting your site with a subreddit, Radle creates a vibrant community hub where your content and discussions thrive in both ecosystems.

## Core Features

### Reddit-Powered Comments
- Replace WordPress's native comments with Reddit discussions
- Synchronize comments between your website and subreddit
- Maintain consistent discussion experience across platforms
- Support for nested comment threads
- Real-time comment updates from Reddit

### Smart Content Publishing
- One-click publishing to your connected subreddit
- Support for both text posts and rich link embeds
- Customizable post templates
- Automatic post synchronization
- Intelligent rate limit monitoring

### Advanced Integration
- Secure Reddit API authentication
- Efficient comment caching system
- Rate limit monitoring and optimization
- Clean, modern comment UI
- Mobile-responsive design

## Pro Features
Upgrade to Radle Pro to unlock:

- Custom comment thread depth (beyond 2 levels)
- Expanded sibling comments (beyond 5 replies)
- Advanced caching controls
- Comment search functionality
- User badges and flair support
- Enhanced moderation tools
- Priority support

## Getting Started
1. Install and activate the plugin
2. Connect your Reddit account
3. Select your target subreddit
4. Configure your publishing preferences
5. Enable the Reddit comments system

## Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Reddit account with API access
- Moderator access to your target subreddit

## Overview Tab Refactoring Plan

### Current State
- GBTI Network tab needs to be renamed to Overview
- Contains GitHub OAuth sections that need to be removed
- Has Latest Releases section that needs to be replaced

### Planned Changes
1. Rename "GBTI Network" tab to "Overview"
2. Remove GitHub OAuth sections
3. Replace Latest Releases with Demo Version Information

## Implementation Plan

### Phase 1: Welcome Process Updates 
1. Welcome Module Updates
   - [x] Modify total steps constant in welcome-module.php (from 9 to 7)
   - [x] Update step progression logic
   - [x] Remove GitHub-related class properties and methods
   - [x] Add attribution option property
   - [x] Fix namespace issues with Usage_Tracking
   - [x] Implement proper error handling

2. Welcome JavaScript Updates
   - [x] Remove GitHub authorization functions
   - [x] Remove sponsor check functions
   - [x] Remove usage data related code
   - [x] Update step navigation logic for new flow
   - [x] Fix subreddit selection dropdown
   - [x] Correct endpoint URLs
   - [x] Add proper debug logging

### Phase 2: Backend Configuration
1. Fixed Settings Implementation
   - [ ] Set cache duration to 5 minutes
     - File: modules/settings/sections/cache-settings.php
     - Remove UI controls, set fixed value
   
   - [ ] Set comment depth to 2 levels
     - File: modules/settings/sections/comments-settings.php
     - Remove depth controls, implement fixed value
   
   - [ ] Set sibling limit to 5
     - File: modules/settings/sections/comments-settings.php
     - Remove sibling controls, implement fixed value

2. Feature Restrictions
   - [ ] Disable search functionality
     - Remove search UI elements
     - Return early from search functions
   
   - [ ] Hide comments from admin menu
     - Update admin menu registration
   
   - [ ] Disable badge display options
     - Remove badge settings
     - Update comment template

### Phase 3: Overview Tab Updates
1. Tab Renaming
   - [ ] Update menu registration from "GBTI Network" to "Overview"
   - [ ] Update related template files
   - [ ] Update any hardcoded references

2. Content Updates
   - [ ] Remove GitHub OAuth sections
   - [ ] Create new Demo Version section
     - Add feature comparison table
     - Add upgrade CTA
     - Include dashicons for feature status

3. Feature Matrix Display
   - [ ] Create feature comparison template
   - [ ] Implement dashicon indicators
   - [ ] Add feature descriptions
   - [ ] Style comparison table

### Phase 4: Documentation & Final Testing
1. Help Text Updates
   - [ ] Update all help tooltips
   - [ ] Add pro feature indicators
   - [ ] Update error messages

2. User Messages
   - [ ] Add upgrade prompts
   - [ ] Update limitation notices
   - [ ] Create pro feature tooltips

3. Final Testing
   - [ ] Test complete welcome flow
   - [ ] Verify all fixed configurations
   - [ ] Check feature restrictions
   - [ ] Test upgrade CTAs

## File Changes Required

### Core Files
- `welcome-module.php`
- `welcome.js`
- `settings-container.php`
- `comments-settings.php`
- `cache-settings.php`

### Templates
- `welcome/*.php` (all welcome step templates)
- `settings/sections/*.php`
- `overview-tab.php` (renamed from gbti-network.php)

### JavaScript
- `welcome.js`
- `settings.js`

### CSS
- `welcome.css`
- `settings.css`
- `overview.css`

## Fixed Configuration Values
- Cache Duration: 5 minutes
- Comment Depth: 2 levels
- Sibling Limit: 5 replies
- Search: Disabled
- Badges: Disabled
- Admin Menu: Comments hidden

## Notes
- Keep settings UI for pro features but disable functionality
- Add clear indicators for pro-only features
- Include upgrade CTAs where appropriate
- Maintain code structure for easy pro version upgrades
- Welcome process should focus on Reddit integration only
- Remove all GitHub/sponsor dependencies
- Add attribution settings for "Powered by Radle" link

## Execution Order
1. Welcome process modifications ( Completed)
2. Implement fixed configurations (Next)
3. Update Overview tab
4. Update documentation and messaging
5. Final testing and verification