<?php

namespace Radle\Modules\Publish;

use Radle\Modules\Reddit\Reddit_API;

class publish {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_publish_to_reddit_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_publish_to_reddit_meta_box() {
        add_meta_box(
            'radle_publish_meta_box',
            __('Publish to Reddit','radle-demo'),
            [$this, 'render_publish_to_reddit_meta_box'],
            'post',
            'side',
            'low'
        );
    }

    public function render_publish_to_reddit_meta_box($post) {
        $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
        $yoast_active = $this->is_yoast_active();
        $default_post_type = get_option('radle_default_post_type', 'link');
        $default_title_template = get_option('radle_default_title_template', '{post_title}');
        $default_content_template = get_option('radle_default_content_template', "{post_excerpt}\n\n{post_permalink}");



        $redditAPI = Reddit_API::getInstance();
        if (!$redditAPI->is_connected()) {
            $settings_url = admin_url('admin.php?page=radle-settings');
            /** translators: %1$s: Opening link tag to settings page, %2$s: Closing link tag */
            ?><div class="radle-notice api-not-connected"><p><?php printf(
                    __('Failed to connect to the Reddit API. Please visit the %1$s Radle settings page%2$s to authorize the application.', 'radle-demo'),
                    '<a href="' . esc_url($settings_url) . '">',
                    '</a>'
                ); ?></p></div><?php
            return;
        }


        if ($reddit_post_id) {
            $reddit_url = 'https://www.reddit.com/' . $reddit_post_id;
            echo '<p>' . esc_html__('This post is already published on Reddit. ','radle-demo') . '<a href="' . esc_url($reddit_url) . '" target="_blank">' . esc_html__('View on Reddit','radle-demo') . '</a></p>';
            echo '<button type="button" id="radle-delete-reddit-id-button" class="button">' . esc_html__('Delete Reddit Connection','radle-demo') . '</button>';
        } else {
            ?>
            <style>
                .radle-options {
                    margin-bottom: 10px;
                }
                .radle-options label {
                    font-weight: bold;
                    display: block;
                    margin-bottom: 5px;
                }
                .radle-options input[type="text"],
                .radle-options textarea {
                    width: 100%;
                    box-sizing: border-box;
                }
                .radle-options textarea {
                    height: 60px;
                }
                .radle-tokens {
                    font-size: 12px;
                    color: #666;
                }
                .radle-tokens code {
                    background: #f4f4f4;
                    padding: 2px 4px;
                    margin-right: 5px;
                    cursor: pointer;
                }
                .radle-radio-inline {
                    display: inline-block;
                    margin-right: 10px;
                }
            </style>
            <div class="radle-options-container">
                <p class="radle-options">
                    <span class="radle-radio-inline">
                        <input type="radio" id="radle_post_type_self" name="radle_post_type" value="self" <?php checked($default_post_type, 'self'); ?>>
                        <label for="radle_post_type_self"><?php esc_html_e('Post','radle-demo'); ?></label>
                    </span>
                    <span class="radle-radio-inline">
                        <input type="radio" id="radle_post_type_link" name="radle_post_type" value="link" <?php checked($default_post_type, 'link'); ?>>
                        <label for="radle_post_type_link"><?php esc_html_e('Link','radle-demo'); ?></label>
                    </span>
                </p>
                <div id="radle_self_options" class="radle-options">
                    <p>
                        <label for="radle_post_title"><?php esc_html_e('Title:','radle-demo'); ?></label>
                        <input type="text" id="radle_post_title" name="radle_post_title" value="<?php echo esc_attr($default_title_template); ?>" />
                    </p>
                    <p class="content-template">
                        <label for="radle_post_content"><?php esc_html_e('Content:','radle-demo'); ?></label>
                        <textarea id="radle_post_content" name="radle_post_content"><?php echo esc_textarea($default_content_template); ?></textarea>
                    </p>
                    <p class="radle-tokens">
                        <strong><?php esc_html_e('Available Tokens:','radle-demo'); ?></strong><br>
                        <?php if ($yoast_active) : ?>
                            <code data-token="{yoast_meta_description}">{yoast_meta_description}</code> - <?php esc_html_e('Yoast SEO Meta Description','radle-demo'); ?><br>
                            <code data-token="{yoast_meta_title}">{yoast_meta_title}</code> - <?php esc_html_e('Yoast SEO Meta Title','radle-demo'); ?><br>
                        <?php endif; ?>
                        <code data-token="{post_excerpt}">{post_excerpt}</code> - <?php esc_html_e('Post Excerpt','radle-demo'); ?><br>
                        <code data-token="{post_title}">{post_title}</code> - <?php esc_html_e('Post Title','radle-demo'); ?><br>
                        <code data-token="{post_permalink}">{post_permalink}</code> - <?php esc_html_e('Post Permalink','radle-demo'); ?><br>
                    </p>
                </div>
                <button type="button" id="radle-preview-post-button" class="button" style="display: none;"><?php esc_html_e('Preview Post','radle-demo'); ?></button>
                <button type="button" id="radle-publish-reddit-button" class="button"><?php esc_html_e('Publish to Reddit','radle-demo'); ?></button>
            </div>
            <?php
        }
    }

    public function enqueue_scripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }
        wp_enqueue_script('radle-publish',RADLE_PLUGIN_URL .'/modules/publish/js/publish.js', ['jquery', 'jquery-ui-dialog'], RADLE_VERSION, true);
        wp_localize_script('radle-publish', 'radlePublishingSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'post_id' => get_the_ID(),
            'success_message' => __('Post published successfully','radle-demo'),
            'failedApiConnection' => __('Failed to connect to the Reddit API. Please try refreshing the page.','radle-demo'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
        wp_enqueue_style('wp-jquery-ui-dialog');
    }



    private function is_yoast_active() {
        return defined('WPSEO_VERSION');
    }
}
