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
                'link' => __('Link','radle-lite')
            ], 'link'); },
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
            function() { $this->render_textarea_field('radle_default_content_template', "{post_excerpt}\n\n{post_permalink}"); },
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
            'radle_default_post_type' => __('Choose whether to post as a text post or a link post on Reddit. If post is selected, the Default Content Template below will control the default message sent to reddit when posting. This message can be edited manually before publishing.','radle-lite'),
            'radle_default_title_template' => __('The default template for the Reddit post title. You can use placeholders like {post_title}. All available token placeholders should be listed below. If you have a SEO management plugin installed, there may be support for special title and description tokens.','radle-lite'),
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
        ?>
        <ul>
            <li><?php esc_html_e('{post_title} - Post Title','radle-lite'); ?></li>
            <li><?php esc_html_e('{post_excerpt} - Post Excerpt','radle-lite'); ?></li>
            <li><?php esc_html_e('{post_permalink} - Post Permalink','radle-lite'); ?></li>
        </ul>
        <p style="font-style: italic; color: #666;">
            <?php esc_html_e('Additional tokens available in Radle Pro:','radle-lite'); ?>
        </p>
        <ul style="color: #666;">
            <li><?php esc_html_e('{yoast_meta_title} - Yoast SEO Meta Title','radle-lite'); ?></li>
            <li><?php esc_html_e('{yoast_meta_description} - Yoast SEO Meta Description','radle-lite'); ?></li>
        </ul>
        <?php
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
     * @return string Sanitized post type ('link' or 'self')
     */
    public function sanitize_post_type($value) {
        $value = sanitize_text_field($value);
        return in_array($value, ['link', 'self']) ? $value : 'link';
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
            return "{post_excerpt}\n\n{post_permalink}";
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
