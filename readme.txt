=== Radle Lite ===
Contributors: GBTI, Hudson Atwell
Tags: reddit, social media, comments, publishing, discussion
Requires at least: 5.9.0
Requires PHP: 7.4
Tested up to: 6.7
Stable tag: 1.0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://github.com/sponsors/gbti-network

Seamlessly integrate Reddit discussions and publishing capabilities into your WordPress site.

== Description ==

Radle Lite is a service integration plugin that connects your WordPress site with Reddit's platform. It enables two-way communication between WordPress and Reddit's servers, allowing you to publish posts to Reddit and display Reddit's comment threads directly on your WordPress posts.

= Account Requirements =

To use this plugin, you need:
1. A Reddit account
2. Reddit API credentials (Client ID and Client Secret)
3. Authorization via Reddit's OAuth system

The plugin guides you through obtaining these credentials during setup.

= Key Features =

* Automatic Reddit post publishing
* Reddit comments integration
* Customizable publishing templates
* Rate limit monitoring
* Comprehensive settings management
* User-friendly setup wizard

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


== Service Integration ==

This plugin connects to two external services:

1. Reddit API Service (Required)
* Service Provider: Reddit, Inc.
* Purpose: Core functionality for comment integration and post publishing
* Endpoints Used:
  - https://oauth.reddit.com/api/v1/me (User authentication)
  - https://www.reddit.com/api/v1/authorize (OAuth authorization)
  - https://www.reddit.com/api/v1/access_token (Token management)
  - https://oauth.reddit.com/api/submit (Post submission)
  - https://oauth.reddit.com/comments (Comment retrieval)
  - https://oauth.reddit.com/user/[username]/about (User profiles)
  - https://oauth.reddit.com/r/[subreddit]/about/moderators (Moderator info)
  - https://oauth.reddit.com/subreddits/mine/moderator (Moderated subreddits)
  - https://oauth.reddit.com/subreddits/mine/subscriber (Subscribed subreddits)
  - https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png (Default avatar)
* Data Transmitted:
  - Post content when publishing to Reddit
  - OAuth credentials for authentication
  - API requests for fetching comments and user data
  - User profile information for displaying comments
* When Data is Sent:
  - During initial OAuth authentication
  - When publishing posts to Reddit
  - When loading Reddit comments on posts
  - When fetching user profile information
* Terms of Service: https://www.redditinc.com/policies/user-agreement
* Privacy Policy: https://www.reddit.com/policies/privacy-policy

2. GBTI Network Service (Optional)
* Service Provider: GBTI Network
* Purpose: Anonymous usage tracking and plugin updates
* Endpoints Used:
  - https://gbti.network/wp-json/github-product-manager/v1/product-events (Usage tracking and updates)
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
* Terms of Service: https://gbti.network/terms-of-service
* Privacy Policy: https://gbti.network/privacy

== Screenshots ==

1. Settings page
2. Publishing configuration
3. Comments display
4. Setup wizard

== Changelog ==

= 1.0.1 =
* Initial release of Radle Lite plugin.

== Upgrade Notice ==

= 1.0.1 =
Initial release of Radle Lite plugin.

== Privacy Policy ==

Radle Lite connects to the Reddit API and stores the following data:
* Reddit API credentials (encrypted)
* Rate limit usage statistics
* Comment display preferences

For more information, visit our [privacy policy](https://example.com/privacy).
