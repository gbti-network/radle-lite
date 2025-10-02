<?php

namespace Radle\Modules\Settings;

class Publishing_Settings extends Setting_Class {

    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_publishing_settings';
        $this->settings_section = 'radle_publishing_settings_section';

        parent::__construct();
    }

    public function register_settings() {
        register_setting($this->settings_option_group, 'radle_default_post_type', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_post_type']
        ]);
        register_setting($this->settings_option_group, 'radle_default_title_template', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_title_template'],
        ]);
        register_setting($this->settings_option_group, 'radle_default_content_template', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_content_template'],
        ]);

        add_settings_section(
            $this->settings_section,
            __('General Settings','radle-lite'),
            null,
            'radle-settings-publishing'
        );

        add_settings_field(
            'radle_enable_rate_limit_monitoring',
            __('Rate Limit Monitoring','radle-lite'), // This will be used as the label in the `th` element
            [$this, 'render_enable_rate_limit_monitoring_field'],
            'radle-settings-publishing',
            $this->settings_section
        );

        add_settings_field(
            'radle_default_post_type',
            __('Default Post Type','radle-lite'),
            function() { $this->render_select_field('radle_default_post_type', [
                'self' => __('Post','radle-lite'),
                'link' => __('Link','radle-lite'),
                'image' => __('Images','radle-lite')
            ], 'image'); },
            'radle-settings-publishing',
            $this->settings_section
        );

        add_settings_field(
            'radle_default_title_template',
            __('Default Title Template','radle-lite'),
            function() { $this->render_text_field('radle_default_title_template', '{post_title}'); },
            'radle-settings-publishing',
            $this->settings_section
        );

        add_settings_field(
            'radle_default_content_template',
            __('Default Content Template','radle-lite'),
            function() { $this->render_textarea_field('radle_default_content_template', "{post_excerpt}\n\n[{post_title_escaped}]({post_permalink})"); },
            'radle-settings-publishing',
            $this->settings_section
        );

        add_settings_field(
            'radle_available_tokens',
            __('Available Tokens','radle-lite'),
            [$this, 'render_available_tokens'],
            'radle-settings-publishing',
            $this->settings_section
        );
    }

    public function render_text_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        echo '<input type="text" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" />';

        $description = $this->get_field_description($option_name);
        if ($description) {
            $this->render_help_icon($description);
        }
    }

    public function render_textarea_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<textarea name="' . esc_attr($option_name) . '" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';

        $description = $this->get_field_description($option_name);
        if ($description) {
            $this->render_help_icon($description);
        }
    }

    public function render_select_field($option_name, $options, $default_value = '') {
        $value = get_option($option_name, $default_value);
        echo '<select name="' . esc_attr($option_name) . '">';
        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        echo '</select>';

        $description = $this->get_field_description($option_name);
        if ($description) {
            $this->render_help_icon($description);
        }
    }

    private function get_field_description($option_name) {
        $descriptions = [
            'radle_subreddit' => __('The target subreddit where posts will be published. Accepts the subreddit name (e.g., gbti_network), a full URL to the subreddit (e.g., https://www.reddit.com/r/GBTI_network/).','radle-lite'),
            'radle_default_post_type' => __('Choose the default Reddit post type: text post, link post, or images. If post is selected, the Default Content Template below will control the default message. Images mode allows you to publish one or more images to Reddit. This can be edited manually before publishing.','radle-lite'),
            /* translators: {post_title} is a template placeholder token that will be replaced with the actual post title */
            'radle_default_title_template' => __('The default template for the Reddit post title. You can use placeholders like {post_title}. All available token placeholders should be listed below. If you have a SEO management plugin installed, there may be support for special title and description tokens.','radle-lite'),
            /* translators: {post_excerpt} and {post_permalink} are template placeholder tokens that will be replaced with actual post data */
            'radle_default_content_template' => __('The default template for the Reddit post content. You can use placeholders like {post_excerpt} and {post_permalink}. All available token placeholders should be listed below. If you have a SEO management plugin installed, there may be support for special title and description tokens. Please check below for the available tokens.','radle-lite'),
        ];

        return isset($descriptions[$option_name]) ? $descriptions[$option_name] : '';
    }

    private function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    public function render_available_tokens() {
        echo '<p style="font-style: italic;">' . esc_html__('The following tokens are supported inside templates:','radle-lite') . '</p>';

        // Get base Lite tokens
        $lite_tokens = [
            '{post_excerpt}' => __('Post Excerpt', 'radle-lite'),
            '{post_title}' => __('Post Title', 'radle-lite'),
            '{post_title_escaped}' => __('Post Title (escaped for markdown links)', 'radle-lite'),
            '{post_permalink}' => __('Post Permalink', 'radle-lite'),
            '{featured_image_url}' => __('Featured Image URL', 'radle-lite'),
        ];

        // Apply filter to get all available tokens (Pro will add its tokens here)
        $all_tokens = apply_filters('radle_available_tokens', $lite_tokens, false);

        // Separate Lite tokens from Pro tokens
        $pro_tokens = array_diff_key($all_tokens, $lite_tokens);

        // Display Lite tokens
        echo '<ul>';
        foreach ($lite_tokens as $token => $description) {
            echo '<li><code>' . esc_html($token) . '</code> - ' . esc_html($description) . '</li>';
        }
        echo '</ul>';

        // Show markdown formatting example
        echo '<p style="font-style: italic; color: #666; margin-top: 15px; margin-bottom: 5px;">';
        echo esc_html__('💡 Tip: Create Reddit markdown links by combining tokens:', 'radle-lite');
        echo '</p>';
        echo '<div style="background: #f9f9f9; padding: 10px; border-left: 3px solid #2271b1; margin-bottom: 15px;">';
        echo '<code>[Read more]({post_permalink})</code><br>';
        echo '<code>[{post_title_escaped}]({post_permalink})</code> ' . esc_html__('← Use this if title has [brackets]', 'radle-lite') . '<br>';
        echo '<code>[Visit our site]({post_permalink})</code>';
        echo '</div>';

        // Display Pro tokens if any are available
        if (!empty($pro_tokens)) {
            echo '<p style="font-style: italic; color: #2271b1; font-weight: 600; margin-top: 15px;">';
            echo esc_html__('Additional tokens from Radle Pro:', 'radle-lite');
            echo '</p>';
            echo '<ul style="color: #2271b1;">';
            foreach ($pro_tokens as $token => $description) {
                echo '<li><strong>' . esc_html($token) . '</strong> - ' . esc_html($description) . '</li>';
            }
            echo '</ul>';
        } else {
            // Show what Pro offers when not active (only if Pro is installed)
            if (defined('RADLE_PRO_VERSION')) {
                echo '<p style="font-style: italic; color: #666; margin-top: 15px;">';
                echo esc_html__('Additional SEO tokens available when you install:', 'radle-lite');
                echo '</p>';
                echo '<ul style="color: #666;">';
                if (!defined('WPSEO_VERSION')) {
                    echo '<li>' . esc_html__('Yoast SEO: {yoast_meta_title}, {yoast_meta_description}', 'radle-lite') . '</li>';
                }
                if (!defined('RANK_MATH_VERSION')) {
                    echo '<li>' . esc_html__('Rank Math: {rankmath_meta_title}, {rankmath_meta_description}', 'radle-lite') . '</li>';
                }
                echo '</ul>';
            } else {
                // Show what Pro offers when Pro is not active
                echo '<p style="font-style: italic; color: #666; margin-top: 15px;">';
                echo esc_html__('Additional tokens available with Radle Pro:', 'radle-lite');
                echo '</p>';
                echo '<ul style="color: #666;">';
                echo '<li>' . esc_html__('With Yoast SEO: {yoast_meta_title}, {yoast_meta_description}', 'radle-lite') . '</li>';
                echo '<li>' . esc_html__('With Rank Math: {rankmath_meta_title}, {rankmath_meta_description}', 'radle-lite') . '</li>';
                echo '</ul>';
            }
        }
    }

    public function render_enable_rate_limit_monitoring_field() {
        $value = get_option('radle_enable_rate_limit_monitoring', 'yes');
        ?>
        <select name="radle_enable_rate_limit_monitoring" id="radle_enable_rate_limit_monitoring" style="min-width: 150px;">
            <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html__('Yes','radle-lite'); ?></option>
            <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html__('No','radle-lite'); ?></option>
        </select>
        <?php $this->render_help_icon(esc_html__('Enable or disable rate limit monitoring. When enabled, Radle will track API usage and warn you when approaching Reddit API limits.','radle-lite')); ?>
        <?php
    }

    /**
     * Sanitize the post type setting.
     *
     * @param string $value The value to sanitize
     * @return string Sanitized post type ('link', 'self', or 'image')
     */
    public function sanitize_post_type($value) {
        $value = sanitize_text_field($value);
        return in_array($value, ['link', 'self', 'image']) ? $value : 'image';
    }

    public function sanitize_title_template($value) {
        // If empty, return the default template
        if (empty($value)) {
            return '{post_title}';
        }
        
        // Sanitize while preserving line breaks
        $value = sanitize_textarea_field($value);
        
        // Ensure the template contains at least one token
        if (strpos($value, '{') === false || strpos($value, '}') === false) {
            add_settings_error(
                'radle_default_title_template',
                'invalid_template',
                __('Title template must contain at least one token (e.g., {post_title})','radle-lite')
            );
            return '{post_title}';
        }
        
        return $value;
    }

    public function sanitize_content_template($value) {
        // If empty, return the default template
        if (empty($value)) {
            return "{post_excerpt}\n\n[{post_title_escaped}]({post_permalink})";
        }
        
        // Sanitize while preserving line breaks
        $value = sanitize_textarea_field($value);
        
        // Ensure the template contains at least one token
        if (strpos($value, '{') === false || strpos($value, '}') === false) {
            add_settings_error(
                'radle_default_content_template',
                'invalid_template',
                __('Content template must contain at least one token (e.g., {post_excerpt})','radle-lite')
            );
            return "{post_excerpt}\n\n{post_permalink}";
        }
        
        return $value;
    }

    public function sanitize_subreddit($subreddit) {
        if (filter_var($subreddit, FILTER_VALIDATE_URL)) {
            $parsed_url = wp_parse_url($subreddit);
            $path_parts = explode('/', trim($parsed_url['path'], '/'));
            $subreddit = end($path_parts);
        }
        return sanitize_text_field($subreddit);
    }
}
