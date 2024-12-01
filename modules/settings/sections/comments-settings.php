<?php

namespace Radle\Modules\Settings;

class Comment_Settings extends Setting_Class {

    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_settings';
        $this->settings_section = 'radle_comment_settings_section';

        parent::__construct();
    }

    public function register_settings() {
        // Register core settings
        register_setting($this->settings_option_group, 'radle_comment_system');
        register_setting($this->settings_option_group, 'radle_button_position');
        register_setting($this->settings_option_group, 'radle_show_powered_by');

        // Register pro settings with sanitization that enforces default values
        register_setting($this->settings_option_group, 'radle_max_depth_level', [
            'sanitize_callback' => [$this, 'enforce_pro_default_number'],
            'default' => 2
        ]);
        register_setting($this->settings_option_group, 'radle_max_siblings', [
            'sanitize_callback' => [$this, 'enforce_pro_default_number'],
            'default' => 5
        ]);
        register_setting($this->settings_option_group, 'radle_cache_duration', [
            'sanitize_callback' => [$this, 'enforce_pro_default_number'],
            'default' => 0
        ]);
        register_setting($this->settings_option_group, 'radle_enable_search', [
            'sanitize_callback' => [$this, 'enforce_pro_default_bool'],
            'default' => 'no'
        ]);
        register_setting($this->settings_option_group, 'radle_show_badges', [
            'sanitize_callback' => [$this, 'enforce_pro_default_bool'],
            'default' => 'no'
        ]);
        register_setting($this->settings_option_group, 'radle_show_comments_menu', [
            'sanitize_callback' => [$this, 'enforce_pro_default_bool'],
            'default' => 'yes'
        ]);

        add_settings_section(
            $this->settings_section,
            __('Comment Settings','radle-demo'),
            null,
            'radle-settings-comments'
        );

        add_settings_field(
            'radle_comment_system',
            __('Comment System','radle-demo'),
            [$this, 'render_comment_system_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_button_position',
            __('Add Comment Button','radle-demo'),
            [$this, 'render_button_position_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_powered_by',
            __('Show Powered By','radle-demo'),
            [$this, 'render_show_powered_by_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_depth_level',
            __('Max Comment Depth','radle-demo'),
            [$this, 'render_max_depth_level_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_siblings',
            __('Max Sibling Comments','radle-demo'),
            [$this, 'render_max_siblings_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_cache_duration',
            __('Cache Duration (seconds)','radle-demo'),
            [$this, 'render_cache_duration_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_enable_search',
            __('Enable Comment Search','radle-demo'),
            [$this, 'render_enable_search_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_badges',
            __('Show User Badges','radle-demo'),
            [$this, 'render_show_badges_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_comments_menu',
            __('Show Legacy Comments Menu','radle-demo'),
            [$this, 'render_show_comments_menu_field'],
            'radle-settings-comments',
            $this->settings_section
        );
    }

    /**
     * Enforces default value for pro numeric settings
     */
    public function enforce_pro_default_number($value) {
        $defaults = [
            'radle_max_depth_level' => 2,
            'radle_max_siblings' => 5,
            'radle_cache_duration' => 0
        ];
        
        $option_name = current_filter();
        $option_name = str_replace('sanitize_option_', '', $option_name);
        
        return $defaults[$option_name] ?? $value;
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
            'wordpress' => __('WordPress','radle-demo'),
            'radle' => __('Radle','radle-demo'),
            'disabled' => __('Disable All','radle-demo'),
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
        $this->render_help_icon(__('Choose which commenting system to use on your site. By default this is set to use the WordPress comments. If Reddit Comments is selected, the WordPress comment system will be replaced with the Radle comments system.','radle-demo'));
    }

    public function render_button_position_field() {
        $value = get_option('radle_button_position', 'below');
        echo '<select name="radle_button_position">';
        echo '<option value="above" ' . selected($value, 'above', false) . '>' . __('Above Comments','radle-demo') . '</option>';
        echo '<option value="below" ' . selected($value, 'below', false) . '>' . __('Below Comments','radle-demo') . '</option>';
        echo '<option value="both" ' . selected($value, 'both', false) . '>' . __('Both','radle-demo') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose where to display the "Add a comment" button relative to the comments section. Please note that if both is selected, we will only show both if there are more than 5 comments.','radle-demo'));
    }

    public function render_show_powered_by_field() {
        $value = get_option('radle_show_powered_by', 'yes');
        echo '<select name="radle_show_powered_by">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes','radle-demo') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No','radle-demo') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose whether to display "Powered by Radle" link in the comments section.','radle-demo'));
    }

    public function render_max_depth_level_field() {
        $value = get_option('radle_max_depth_level', 2);
        echo '<select name="radle_max_depth_level" class="radle-pro-field">';
        for ($i = 1; $i <= 10; $i++) {
            printf(
                '<option value="%d" %s>%d</option>',
                $i,
                selected($value, $i, false),
                $i
            );
        }
        echo '</select>';
        $this->render_help_icon(__('Maximum depth level for nested comments. In Pro version, you can set this from 1-10 levels. Higher values allow for deeper comment threads and more complex discussions.','radle-demo'));
    }

    public function render_max_siblings_field() {
        $value = get_option('radle_max_siblings', 5);
        echo '<select name="radle_max_siblings" class="radle-pro-field">';
        $options = [5, 10, 15, 20, 25, 30];
        foreach ($options as $option) {
            printf(
                '<option value="%d" %s>%d</option>',
                $option,
                selected($value, $option, false),
                $option
            );
        }
        echo '</select>';
        $this->render_help_icon(__('Maximum number of sibling comments to show before pagination. Pro version allows 5-30 comments per level for better discussion visibility.','radle-demo'));
    }

    public function render_cache_duration_field() {
        $value = get_option('radle_cache_duration', 0);
        echo '<select name="radle_cache_duration" class="radle-pro-field">';
        
        // Add None/Disabled option first
        echo '<option value="0"' . selected($value, 0, false) . '>' . __('None (Disabled)','radle-demo') . '</option>';
        
        $options = [
            300 => __('5 minutes','radle-demo'),
            600 => __('10 minutes','radle-demo'),
            1800 => __('30 minutes','radle-demo'),
            3600 => __('1 hour','radle-demo'),
            7200 => __('2 hours','radle-demo'),
            21600 => __('6 hours','radle-demo'),
            43200 => __('12 hours','radle-demo'),
            86400 => __('24 hours','radle-demo'),
        ];
        foreach ($options as $seconds => $label) {
            printf(
                '<option value="%d" %s>%s</option>',
                $seconds,
                selected($value, $seconds, false),
                esc_html($label)
            );
        }
        echo '</select>';
        $this->render_help_icon(__('Cache duration for Reddit comments. Pro version offers flexible caching from 5 minutes to 24 hours to improve performance. Demo version has caching disabled.','radle-demo'));
    }

    public function render_enable_search_field() {
        $value = get_option('radle_enable_search', 'no');
        echo '<select name="radle_enable_search" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes','radle-demo') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No','radle-demo') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Enable comment search functionality. Pro version allows visitors to search through comments to find specific discussions.','radle-demo'));
    }

    public function render_show_badges_field() {
        $value = get_option('radle_show_badges', 'no');
        echo '<select name="radle_show_badges" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes','radle-demo') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No','radle-demo') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Show Reddit user badges and flair. Pro version displays Reddit user achievements and karma levels for added context.','radle-demo'));
    }

    public function render_show_comments_menu_field() {
        $value = get_option('radle_show_comments_menu', 'yes');
        echo '<select name="radle_show_comments_menu" class="radle-pro-field">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes','radle-demo') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No','radle-demo') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Show WordPress legacy comments menu in admin bar. Pro version adds option to remove this menu item from your wp-admin UI.','radle-demo'));
    }

    public function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    // Fixed configuration getters for demo version
    public static function get_max_depth_level() {
        return 2; // Fixed at 2 levels for demo
    }

    public static function get_max_siblings() {
        return 5; // Fixed at 5 siblings for demo
    }

    public static function get_cache_duration() {
        return 0; // Fixed at 0 seconds (disabled) for demo
    }

    public static function is_search_enabled() {
        return false; // Search disabled in demo
    }

    public static function show_badges() {
        return false; // Badges disabled in demo
    }

    public static function show_comments_menu() {
        return true; // Always show legacy comments menu in demo
    }
}