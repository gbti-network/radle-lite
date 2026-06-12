=== Radle Lite – A Reddit Comments Engine ===
Contributors: GBTI, Hudson Atwell
Tags: reddit, social media, comments, publishing, discussion
Requires at least: 5.9.0
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 2.0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://gbti.network/?ref=atwellpub&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=donate

Seamlessly integrate Reddit discussions and publishing capabilities into your WordPress site. Every Radle feature is now free and open source.

== Description ==

Radle Lite is a service integration plugin that connects your WordPress site with Reddit's platform. It enables two-way communication between WordPress and Reddit's servers, allowing you to publish posts to Reddit and display Reddit's comment threads directly on your WordPress posts.

= Account Requirements =

To use this plugin, you need:
1. A Reddit account
2. Reddit API credentials (Client ID and Client Secret)
3. Authorization via Reddit's OAuth system

The plugin guides you through obtaining these credentials during setup.

= Key Features =

As of version 2.0.0, every feature that was previously part of Radle Pro is now built into Radle Lite — free, with no license, no account gate, and no upsell.

* Quick publishing to a subreddit or your Reddit user profile
* Per-post destination override (publish individual posts to a different subreddit or your profile)
* Reddit comments integration with configurable thread depth (up to 10 levels) and expanded replies (up to 30 per level)
* Real-time comment search
* Seven comment sort modes: Newest, Most Popular, Oldest, Least Popular, Most Engaged, Most Balanced, and Q&A
* Author badges for Original Poster, Moderator, and Pinned comments
* Pinned (stickied) comments surfaced at the top of the thread
* Configurable comment caching (5 minutes to 24 hours) with automatic cache clearing
* Customizable publishing templates with SEO meta tokens for Yoast SEO and Rank Math
* Per-post comment system override
* Option to hide the legacy WordPress comments menu
* Rate limit monitoring
* Comprehensive settings management
* User-friendly setup wizard
* Available in 15 languages

= Data Exchange =

The plugin communicates with Reddit's servers to:
* Fetch and display comment threads
* Retrieve user profile information (including avatars)
* Submit posts to Reddit
* Monitor API usage and rate limits

All Reddit data is served directly from Reddit's infrastructure, ensuring real-time updates and compliance with Reddit's platform requirements.

= Installation =

1. Upload the plugin files to the `/wp-content/plugins/radle-lite` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the Radle Lite settings page to configure your Reddit API credentials.
4. Follow the setup wizard to complete the configuration.

== Frequently Asked Questions ==

= Do I need a Reddit account? =

Yes, you need a Reddit account and Reddit API credentials to use this plugin. The setup wizard will guide you through the process of obtaining these credentials.

= Can I customize how my posts appear on Reddit? =

Yes, you can customize the post title and content templates in the Publishing Settings section.

= Is Radle really completely free now? =

Yes. Radle 2.0 folds every former Radle Pro feature into the free, open-source plugin. There is no paid tier, no license key, and no sponsor check — all features are available to everyone.


== Join the GBTI Network ==

Radle is built and maintained by the [GBTI Network](https://gbti.network/?ref=atwellpub&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=community), a professional community and co-op for WordPress developers, builders, and site owners.

Membership is about people, not paywalls. As a member you get:

* A private Discord community of working professionals
* Direct input into Radle's roadmap and other GBTI open-source projects
* Early access to tools, betas, and member-built resources
* A collaborative, co-op model where members share work, knowledge, and opportunities

[Join the GBTI Network](https://gbti.network/?ref=atwellpub&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=community)

== Need Custom Development? ==

Want Radle (or any WordPress project) tailored to your needs? Hire a vetted expert through [Codeable](https://codeable.io/?ref=99TG1&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=customizations) for a free, no-obligation estimate.


== Service Integration ==

This plugin connects to two external services:

= Reddit API Service =

* Service Provider: Reddit
* Purpose: Fetch comments and publish content (text, links, images, galleries)
* Endpoints Used:
  - https://oauth.reddit.com/r/subreddit/about/moderators (Moderator info)
  - https://oauth.reddit.com/subreddits/mine/moderator (Moderated subreddits)
  - https://oauth.reddit.com/subreddits/mine/subscriber (Subscribed subreddits)
  - https://oauth.reddit.com/api/submit (Submit posts - text, link, image)
  - https://oauth.reddit.com/api/submit_gallery_post.json (Submit multi-image gallery posts)
  - https://oauth.reddit.com/api/media/asset.json (Request image upload credentials)
  - https://reddit-uploaded-media.s3-accelerate.amazonaws.com/ (Upload images to Reddit CDN)
  - wss://reddit.com/... (WebSocket connections for real-time post processing status)
  - https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png (Default avatar)
* Data Transmitted:
  - Post content (title, text, links) when publishing to Reddit
  - Images (JPEG, PNG, GIF, WebP, AVIF) when publishing image/gallery posts
  - OAuth credentials for authentication
  - API requests for fetching comments and user data
  - User profile information for displaying comments
* When Data is Sent:
  - During initial OAuth authentication
  - When publishing posts to Reddit (text, link, image, or gallery)
  - When uploading images to Reddit's CDN
  - When monitoring post processing status via WebSocket
  - When loading Reddit comments on posts
  - When fetching user profile information
* Terms of Service: [Reddit Terms](https://www.redditinc.com/policies/user-agreement)
* Privacy Policy: [Reddit Privacy Policy](https://www.reddit.com/policies/privacy-policy)

= GBTI Network Service (Optional) =

* Service Provider: GBTI Network
* Purpose: Anonymous usage tracking (plugin updates are delivered through WordPress.org)
* Endpoints Used:
  - https://gbti.network/wp-json/github-product-manager/v1/product-events (Anonymous usage tracking)
* Data Transmitted:
  - Plugin version
  - WordPress version
  - PHP version
  - Site domain or anonymous ID (based on setup preference)
  - Basic usage statistics
* When Data is Sent:
  - On plugin activation/deactivation
  - Weekly usage pings if opted in
* Can be disabled during plugin setup

= Translations =

Radle Lite is available in the following languages:
* Arabic (ar)
* German (de_DE)
* Greek (el)
* Spanish (es_ES)
* French (fr_FR)
* Hebrew (he_IL)
* Hindi (hi_IN)
* Italian (it_IT)
* Japanese (ja_JP)
* Korean (ko_KR)
* Dutch (nl_NL)
* Polish (pl_PL)
* Portuguese (pt_PT)
* Russian (ru_RU)
* Swedish (sv_SE)

Want to help translate Radle Lite into your language? Visit our [GitHub repository](https://github.com/gbti-network/radle-lite) to contribute.

== Screenshots ==

1. Viewing Comments In Post Edit Screen
2. Viewing Comments In Post Frontend
3. Viewing Quick Posting Metabox
4. Viewing Settings Page: Reddit Connection
5. Viewing Settings Page: Publishing Configuration
6. Viewing Settings Page: API Monitoring

== Changelog ==

= 2.0.1 =
* MAJOR: Radle Pro has been sunset and all of its features are now built into Radle Lite — free, with no license, sponsor check, or upsell.
* NEW: Configurable comment thread depth (1-10 levels) and expanded sibling replies (5-30 per level).
* NEW: Real-time comment search.
* NEW: Four additional comment sort modes — Least Popular, Most Engaged, Most Balanced, and Q&A.
* NEW: Configurable comment caching (5 minutes to 24 hours) with automatic cache clearing when comment settings change.
* NEW: Author badges for Original Poster and Moderator comments.
* NEW: Pinned (stickied) Reddit comments are sorted to the top of the thread and flagged with a "Pinned" badge, matching Reddit's behavior — always shown, even when author badges are turned off.
* NEW: Per-post destination override — publish individual posts to a different subreddit or your Reddit profile.
* NEW: Per-post comment system override.
* NEW: SEO meta tokens for Yoast SEO and Rank Math ({yoast_meta_title}, {yoast_meta_description}, {rankmath_meta_title}, {rankmath_meta_description}).
* NEW: Option to hide the legacy WordPress comments menu.
* SECURITY: Per-post destination overrides are validated server-side against the subreddits you moderate (or your own profile); destination input is sanitized.
* IMPROVEMENT: Removed the GBTI sponsor/OAuth licensing layer and the separate update server; updates are delivered through WordPress.org.

= 1.4.5 =
* FIX: User-deleted Reddit comments (showing "[deleted]") were visible to all site visitors. Deleted leaf comments are now fully removed. Deleted comments with replies are replaced with a minimal [deleted] placeholder to preserve thread context.

= 1.4.4 =
* FIX: Reddit blockquotes (lines starting with >) now render properly as styled blockquotes instead of displaying as literal text.

= 1.4.3 =
* NEW: Adding ability to set default filter mode
* IMPROVEMENT: Increasing user avatar cache from 1 hour to 6 hours. Adding in 2 minute cache for rate limited avatar/user calls to prevent excessive polling when being rate limited. 

= 1.4.2 =
* FIX: Welcome screen would not allow authorization if user was not a moderator of any subreddits.
* FIX: Adding ability to select "User Profile" as destination type during welcome screen.

= 1.4.0 =
* NEW: Adding option to hide unapproved comments.
* FIX: Hiding unapproved comments in comment list.
* FIX: Adding support for Reddit uploaded images (includes lightbox support)
* POTENTIAL FIX: Attempting to address sorting inconsistencies.

= 1.3.0 =
* FIX: Recent markdown conversion methods broke our Link support for importing links from Reddit comments. This is fixed. 
* FIX: Comment share button was not doing anything. Completed this feature and now it copies the comment link to clipboard. 
* NEW: Increased max siblings from 5 to 10 for free version
* NEW: Added support for GIFs in Reddit comments. 


= 1.2.4 =
* FIX: Post-level overwrite pro feature had broken artifact in lite feature.
* FIX: 5-minute default cache removed from lite.
* FIX: Issue with debugger causing JS errors on welcome screen and settings screen.
* FIX: Issue with debugger causing JS errors on welcome screen.

= 1.2.1 =
* NEW: Single and multi-image posts now work with proper Reddit API integration
* NEW: Added automatic post association via WebSocket when Reddit needs processing time
* NEW: Added ability to post to personal reddit profile. 
* NEW: Adding token support for markdown links. 
* IMPROVED: "Images" mode now default post type (was "Post")
* IMPROVED: Featured image automatically pre-populated in image gallery
* IMPROVED: Extended image format support - added WebP, AVIF, and GIF for Images mode.
* IMPROVED: Improving styling of dropdowns used within plugin
* TECHNICAL: Added /api/submit_gallery_post.json endpoint integration
* TECHNICAL: Added /api/submit with kind='image' for single images
* TECHNICAL: Created new associate endpoint for post linking after processing
* TECHNICAL: Preparing framework for Radle Pro extension. 

= 1.0.13 =
* Bumping supported WordPress tag to 6.8.1.

= 1.0.12 =
* Improving support for block based themes. 

= 1.0.11 =
* Develop deploy scripts.
* Improving readme.txt formatting for better readability.
* Addressing issue where link to published comment did not include subreddit.
* Improving translation files.

= 1.0.1 =
* Initial release of Radle Lite plugin.

== Upgrade Notice ==

= 2.0.1 =
Radle Pro is now free. This release merges every Pro feature into Radle Lite — deeper comment threading, search, advanced sorting, caching, author badges, per-post destination overrides, SEO meta tokens, and more — with no license or sponsor check. Your existing settings are preserved.

= 1.0.1 =
Initial release of Radle Lite plugin.

== Privacy Policy ==

Radle Lite connects to the Reddit API and stores the following data:

* Reddit API credentials (encrypted)
* Rate limit usage statistics
* Comment display preferences

For more information, visit our [privacy policy](https://gbti.network/privacy/?ref=atwellpub&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=privacy).
