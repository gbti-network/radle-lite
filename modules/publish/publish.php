<?php

namespace Radle\Modules\Publish;

use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles WordPress post publishing functionality to Reddit.
 * 
 * This class manages the integration between WordPress posts and Reddit submissions by:
 * - Adding a meta box to the post editor for Reddit publishing options
 * - Managing post types (link/self) for Reddit submissions
 * - Handling content templating with dynamic tokens
 * - Supporting Yoast SEO integration for meta descriptions
 * - Managing Reddit post connections and viewing options
 * 
 * Features:
 * - Publish WordPress posts as either link or text posts on Reddit
 * - Customizable title and content templates with dynamic tokens
 * - Integration with Yoast SEO for meta descriptions
 * - Preview functionality before publishing
 * - View and manage Reddit post connections
 */
class publish {

    /**
     * Initialize the publish module and set up necessary hooks.
     * 
     * Sets up meta boxes and enqueues required scripts for the post editor.
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_publish_to_reddit_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add the "Publish to Reddit" meta box to the post editor.
     * 
     * Creates a meta box in the sidebar of the post editor that contains
     * Reddit publishing options and controls.
     */
    public function add_publish_to_reddit_meta_box() {
        add_meta_box(
            'radle_publish_meta_box',
            __('Publish to Reddit','radle-lite'),
            [$this, 'render_publish_to_reddit_meta_box'],
            'post',
            'side',
            'low'
        );
    }

    /**
     * Render the content of the "Publish to Reddit" meta box.
     * 
     * Displays:
     * - Reddit connection status
     * - Post type selection (link/self)
     * - Title and content templates with token support
     * - Available tokens list
     * - Publishing controls
     * 
     * If a post is already published to Reddit:
     * - Shows the Reddit post link
     * - Provides option to delete the Reddit connection
     * 
     * @param WP_Post $post The post being edited
     */
    public function render_publish_to_reddit_meta_box($post) {
        $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
        $yoast_active = $this->is_yoast_active();
        $default_post_type = get_option('radle_default_post_type', 'link');
        $default_title_template = get_option('radle_default_title_template', '{post_title}');
        $default_content_template = get_option('radle_default_content_template', "{post_excerpt}\n\n{post_permalink}");



        $redditAPI = Reddit_API::getInstance();
        if (!$redditAPI->is_connected()) {
            $settings_url = admin_url('admin.php?page=radle-settings');
            ?><div class="radle-notice api-not-connected"><p><?php 
            printf(
                    /* translators: %1$s: Opening link tag to settings page, %2$s: Closing link tag */
                    esc_html__('Failed to connect to the Reddit API. Please visit the %1$s Radle settings page%2$s to authorize the application.', 'radle-lite'),
                    '<a href="' . esc_url($settings_url) . '">',
                    '</a>'
                ); ?></p></div><?php
            return;
        }


        if ($reddit_post_id) {
            $reddit_url = 'https://www.reddit.com/' . $reddit_post_id;
            echo '<p>' . esc_html__('This post is already published on Reddit. ','radle-lite') . '<a href="' . esc_url($reddit_url) . '" target="_blank">' . esc_html__('View on Reddit','radle-lite') . '</a></p>';
            echo '<button type="button" id="radle-delete-reddit-id-button" class="button">' . esc_html__('Delete Reddit Connection','radle-lite') . '</button>';
        } else {
            ?>
            <div class="radle-options-container">
                <p class="radle-options">
                    <span class="radle-radio-inline">
                        <input type="radio" id="radle_post_type_self" name="radle_post_type" value="self" <?php checked($default_post_type, 'self'); ?>>
                        <label for="radle_post_type_self"><?php esc_html_e('Post','radle-lite'); ?></label>
                    </span>
                    <span class="radle-radio-inline">
                        <input type="radio" id="radle_post_type_link" name="radle_post_type" value="link" <?php checked($default_post_type, 'link'); ?>>
                        <label for="radle_post_type_link"><?php esc_html_e('Link','radle-lite'); ?></label>
                    </span>
                </p>
                <div id="radle_self_options" class="radle-options">
                    <p>
                        <label for="radle_post_title"><?php esc_html_e('Title:','radle-lite'); ?></label>
                        <input type="text" id="radle_post_title" name="radle_post_title" value="<?php echo esc_attr($default_title_template); ?>" />
                    </p>
                    <p class="content-template">
                        <label for="radle_post_content"><?php esc_html_e('Content:','radle-lite'); ?></label>
                        <textarea id="radle_post_content" name="radle_post_content"><?php echo esc_textarea($default_content_template); ?></textarea>
                    </p>
                    <p class="radle-tokens">
                        <strong><?php esc_html_e('Available Tokens:','radle-lite'); ?></strong><br>
                        <?php if ($yoast_active) : ?>
                            <code data-token="{yoast_meta_description}">{yoast_meta_description}</code> - <?php esc_html_e('Yoast SEO Meta Description','radle-lite'); ?><br>
                            <code data-token="{yoast_meta_title}">{yoast_meta_title}</code> - <?php esc_html_e('Yoast SEO Meta Title','radle-lite'); ?><br>
                        <?php endif; ?>
                        <code data-token="{post_excerpt}">{post_excerpt}</code> - <?php esc_html_e('Post Excerpt','radle-lite'); ?><br>
                        <code data-token="{post_title}">{post_title}</code> - <?php esc_html_e('Post Title','radle-lite'); ?><br>
                        <code data-token="{post_permalink}">{post_permalink}</code> - <?php esc_html_e('Post Permalink','radle-lite'); ?><br>
                    </p>
                </div>
                <button type="button" id="radle-preview-post-button" class="button" style="display: none;"><?php esc_html_e('Preview Post','radle-lite'); ?></button>
                <button type="button" id="radle-publish-reddit-button" class="button"><?php esc_html_e('Publish to Reddit','radle-lite'); ?></button>
            </div>
            <?php
        }
    }

    /**
     * Enqueue necessary scripts and styles for the publish functionality.
     * 
     * Loads:
     * - Publishing JavaScript
     * - jQuery UI Dialog
     * - Localized script data for AJAX and REST API
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        wp_enqueue_script('radle-publish',RADLE_PLUGIN_URL .'/modules/publish/js/publish.js', ['jquery', 'jquery-ui-dialog'], RADLE_VERSION, true);
        wp_localize_script('radle-publish', 'radlePublishingSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'post_id' => get_the_ID(),
            'success_message' => __('Post published successfully','radle-lite'),
            'failedApiConnection' => __('Failed to connect to the Reddit API. Please try refreshing the page.','radle-lite'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);

        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('radle-publish', RADLE_PLUGIN_URL . 'modules/publish/css/publish.css', [], RADLE_VERSION);
    }



    /**
     * Check if Yoast SEO plugin is active.
     * 
     * Used to determine whether to show Yoast-specific tokens
     * in the template editor.
     * 
     * @return bool True if Yoast SEO is active
     * @access private
     */
    private function is_yoast_active() {
        return defined('WPSEO_VERSION');
    }
}
