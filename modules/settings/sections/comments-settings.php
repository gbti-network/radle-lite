<?php

namespace Radle\Modules\Settings;

class Comment_Settings extends Setting_Class {

    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_comment_settings';
        $this->settings_section = 'radle_comment_settings_section';

        parent::__construct();
    }

    public function register_settings() {

        register_setting($this->settings_option_group, 'radle_comment_system', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_button_position', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_show_powered_by', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_max_depth_level', [
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        ]);

        register_setting($this->settings_option_group, 'radle_max_siblings', [
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        ]);

        register_setting($this->settings_option_group, 'radle_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        ]);

        register_setting($this->settings_option_group, 'radle_enable_search', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_show_badges', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_show_comments_menu', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        

        add_settings_section(
            $this->settings_section,
            esc_html__('Comment Settings','radle-lite'),
            null,
            'radle-settings-comments'
        );

        add_settings_field(
            'radle_comment_system',
            esc_html__('Comment System','radle-lite'),
            [$this, 'render_comment_system_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_button_position',
            esc_html__('Add Comment Button','radle-lite'),
            [$this, 'render_button_position_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_powered_by',
            esc_html__('Show Powered By','radle-lite'),
            [$this, 'render_show_powered_by_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_depth_level',
            esc_html__('Max Comment Depth','radle-lite'),
            [$this, 'render_max_depth_level_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_siblings',
            esc_html__('Max Sibling Comments','radle-lite'),
            [$this, 'render_max_siblings_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_cache_duration',
            esc_html__('Cache Duration (seconds)','radle-lite'),
            [$this, 'render_cache_duration_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_enable_search',
            esc_html__('Enable Comment Search','radle-lite'),
            [$this, 'render_enable_search_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_badges',
            esc_html__('Show User Badges','radle-lite'),
            [$this, 'render_show_badges_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_comments_menu',
            esc_html__('Show Legacy Comments Menu','radle-lite'),
            [$this, 'render_show_comments_menu_field'],
            'radle-settings-comments',
            $this->settings_section
        );
    }

    /**
     * Enforces default value for pro boolean settings
     */
    public function enforce_pro_default_bool($value) {
        // Special case for legacy comments menu which defaults to yes
        $option_name = current_filter();
        $option_name = str_replace('sanitize_option_', '', $option_name);
        
        if ($option_name === 'radle_show_comments_menu') {
            return 'yes';
        }
        return 'no';
    }

    public function render_comment_system_field() {
        $value = get_option('radle_comment_system', 'wordpress');
        $options = [
            'wordpress' => esc_html__('WordPress','radle-lite'),
            'radle' => esc_html__('Radle','radle-lite'),
            'disabled' => esc_html__('Disable All','radle-lite'),
        ];
        echo '<select name="radle_comment_system">';
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Choose which commenting system to use on your site. By default this is set to use the WordPress comments. If Reddit Comments is selected, the WordPress comment system will be replaced with the Radle comments system.','radle-lite'));
    }

    public function render_button_position_field() {
        $value = get_option('radle_button_position', 'below');
        echo '<select name="radle_button_position">';
        echo '<option value="above" ' . selected($value, 'above', false) . '>' . esc_html__('Above Comments','radle-lite') . '</option>';
        echo '<option value="below" ' . selected($value, 'below', false) . '>' . esc_html__('Below Comments','radle-lite') . '</option>';
        echo '<option value="both" ' . selected($value, 'both', false) . '>' . esc_html__('Both','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Choose where to display the "Add a comment" button relative to the comments section. Please note that if both is selected, we will only show both if there are more than 5 comments.','radle-lite'));
    }

    public function render_show_powered_by_field() {
        $value = get_option('radle_show_powered_by', 'yes');
        echo '<select name="radle_show_powered_by">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Choose whether to display "Powered by Radle" link in the comments section.','radle-lite'));
    }

    public function render_max_depth_level_field() {
        $value = 2;
        echo '<select name="radle_max_depth_level" class="radle-pro-field">';
        for ($i = 1; $i <= 10; $i++) {
            printf(
                '<option value="%d" %s>%d</option>',
                absint($i),
                selected($value, $i, false),
                absint($i)
            );
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Maximum depth level for nested comments. In Pro version, you can set this from 1-10 levels. Higher values allow for deeper comment threads and more complex discussions.','radle-lite'));
    }

    public function render_max_siblings_field() {
        $value = 5;
        echo '<select name="radle_max_siblings" class="radle-pro-field">';
        $options = [5, 10, 15, 20, 25, 30];
        foreach ($options as $option) {
            printf(
                '<option value="%d" %s>%d</option>',
                absint($option),
                selected($value, $option, false),
                absint($option)
            );
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Maximum number of sibling comments to show before pagination. Pro version allows 5-30 comments per level for better discussion visibility.','radle-lite'));
    }

    public function render_cache_duration_field() {
        $value = get_option('radle_cache_duration', 0);
        echo '<select name="radle_cache_duration" class="radle-pro-field">';
        
        // Add None/Disabled option first
        echo '<option value="0"' . selected($value, 0, false) . '>' . esc_html__('None (Disabled)','radle-lite') . '</option>';
        
        $options = [
            300 => esc_html__('5 minutes','radle-lite'),
            600 => esc_html__('10 minutes','radle-lite'),
            1800 => esc_html__('30 minutes','radle-lite'),
            3600 => esc_html__('1 hour','radle-lite'),
            7200 => esc_html__('2 hours','radle-lite'),
            21600 => esc_html__('6 hours','radle-lite'),
            43200 => esc_html__('12 hours','radle-lite'),
            86400 => esc_html__('24 hours','radle-lite'),
        ];
        foreach ($options as $seconds => $label) {
            printf(
                '<option value="%d" %s>%s</option>',
                absint($seconds),
                selected($value, $seconds, false),
                esc_html($label)
            );
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Cache duration for Reddit comments. Pro version offers flexible caching from 5 minutes to 24 hours to improve performance. Lite version has caching disabled.','radle-lite'));
    }

    public function render_enable_search_field() {
        $value = get_option('radle_enable_search', 'no');
        echo '<select name="radle_enable_search" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Enable comment search functionality. Pro version allows visitors to search through comments to find specific discussions.','radle-lite'));
    }

    public function render_show_badges_field() {
        $value = get_option('radle_show_badges', 'no');
        echo '<select name="radle_show_badges" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show Reddit user badges and flair. Pro version displays Reddit user achievements and karma levels for added context.','radle-lite'));
    }

    public function render_show_comments_menu_field() {
        $value = get_option('radle_show_comments_menu', 'yes');
        echo '<select name="radle_show_comments_menu" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show WordPress legacy comments menu in admin bar. Pro version adds option to remove this menu item from your wp-admin UI.','radle-lite'));
    }

    public function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    // Fixed configuration getters for lite version
    public static function get_max_depth_level() {
        return 2; // Fixed at 2 levels for lite
    }

    public static function get_max_siblings() {
        return 5; // Fixed at 5 siblings for lite
    }

    public static function get_cache_duration() {
        return 0; // Fixed at 0 seconds (disabled) for lite
    }

    public static function is_search_enabled() {
        return false; // Search disabled in lite
    }

    public static function show_badges() {
        return false; // Badges disabled in lite
    }

    public static function show_comments_menu() {
        return true; // Always show legacy comments menu in lite
    }
}