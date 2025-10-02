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
            'radle_above_wordpress' => esc_html__('Radle Above WordPress','radle-lite'),
            'radle_below_wordpress' => esc_html__('Radle Below WordPress','radle-lite'),
            'shortcode' => esc_html__('Shortcode','radle-lite'),
            'disabled' => esc_html__('Disable All','radle-lite'),
        ];
        echo '<select name="radle_comment_system" id="radle_comment_system">';
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<div id="radle-shortcode-notice" style="display:none; margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
        echo '<strong>' . esc_html__('Shortcode:', 'radle-lite') . '</strong> <code>[radle_comments]</code><br>';
        echo '<span style="color: #646970;">' . esc_html__('Use this shortcode in your theme templates or page content to display Reddit comments.', 'radle-lite') . '</span>';
        echo '</div>';
        $this->render_help_icon(esc_html__('Choose which commenting system to use on your site. WordPress: Native WordPress comments. Radle: Replace WordPress comments with Reddit comments. Radle Above WordPress: Show Reddit comments above WordPress comments. Radle Below WordPress: Show Reddit comments below WordPress comments. Shortcode: Use [radle_comments] shortcode to manually place comments. Disable All: Turn off all comments.','radle-lite'));
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
        // Use saved value if Pro, otherwise use Lite default
        $value = $this->is_pro_field('radle_max_depth_level') ? 2 : get_option('radle_max_depth_level', 2);
        $pro_class = $this->get_pro_field_class('radle_max_depth_level');
        $disabled = $this->get_pro_field_disabled('radle_max_depth_level');
        echo '<select name="radle_max_depth_level" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';
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
        // Use saved value if Pro, otherwise use Lite default
        $value = $this->is_pro_field('radle_max_siblings') ? 5 : get_option('radle_max_siblings', 5);
        $pro_class = $this->get_pro_field_class('radle_max_siblings');
        $disabled = $this->get_pro_field_disabled('radle_max_siblings');
        echo '<select name="radle_max_siblings" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';
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
        // Use saved value if Pro, otherwise use Lite default (0 = disabled)
        $value = $this->is_pro_field('radle_cache_duration') ? 0 : get_option('radle_cache_duration', 0);
        $pro_class = $this->get_pro_field_class('radle_cache_duration');
        $disabled = $this->get_pro_field_disabled('radle_cache_duration');
        echo '<select name="radle_cache_duration" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';

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
        // Use saved value if Pro, otherwise use Lite default (no)
        $value = $this->is_pro_field('radle_enable_search') ? 'no' : get_option('radle_enable_search', 'no');
        $pro_class = $this->get_pro_field_class('radle_enable_search');
        $disabled = $this->get_pro_field_disabled('radle_enable_search');
        echo '<select name="radle_enable_search" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Enable comment search functionality. Pro version allows visitors to search through comments to find specific discussions.','radle-lite'));
    }

    public function render_show_badges_field() {
        // Use saved value if Pro, otherwise use Lite default (no)
        $value = $this->is_pro_field('radle_show_badges') ? 'no' : get_option('radle_show_badges', 'no');
        $pro_class = $this->get_pro_field_class('radle_show_badges');
        $disabled = $this->get_pro_field_disabled('radle_show_badges');
        echo '<select name="radle_show_badges" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show Reddit user badges and flair. Pro version displays Reddit user achievements and karma levels for added context.','radle-lite'));
    }

    public function render_show_comments_menu_field() {
        // Use saved value if Pro, otherwise use Lite default (yes)
        $value = $this->is_pro_field('radle_show_comments_menu') ? 'yes' : get_option('radle_show_comments_menu', 'yes');
        $pro_class = $this->get_pro_field_class('radle_show_comments_menu');
        $disabled = $this->get_pro_field_disabled('radle_show_comments_menu');
        echo '<select name="radle_show_comments_menu" class="' . esc_attr($pro_class) . '" ' . $disabled . '>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show WordPress legacy comments menu in admin bar. Pro version adds option to remove this menu item from your wp-admin UI.','radle-lite'));
    }

    public function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    /**
     * Check if a field should be marked as Pro
     *
     * @since 1.2.0
     * @param string $field_name Field name to check
     * @return bool True if field should be marked as Pro
     */
    private function is_pro_field($field_name) {
        /**
         * Filter whether a field should be marked as Pro-only
         *
         * Lite: Returns true for all Pro fields (shows "Pro Version Only" notice)
         * Pro: Returns false for all fields (removes Pro restrictions)
         *
         * @since 1.2.0
         * @param bool $is_pro Whether field should be marked as Pro
         * @param string $field_name Name of the field being checked
         */
        return apply_filters('radle_is_pro_field', true, $field_name);
    }

    /**
     * Get Pro field class
     *
     * Returns 'radle-pro-field' class if field is Pro-only, empty string otherwise
     *
     * @since 1.2.0
     * @param string $field_name Field name
     * @return string CSS class or empty string
     */
    private function get_pro_field_class($field_name) {
        return $this->is_pro_field($field_name) ? 'radle-pro-field' : '';
    }

    /**
     * Get Pro field disabled attribute
     *
     * Returns 'disabled' attribute if field is Pro-only, empty string otherwise
     *
     * @since 1.2.0
     * @param string $field_name Field name
     * @return string Disabled attribute or empty string
     */
    private function get_pro_field_disabled($field_name) {
        return $this->is_pro_field($field_name) ? 'disabled' : '';
    }

    // Fixed configuration getters for lite version
    // Pro extension can override these via filters
    public static function get_max_depth_level() {
        /**
         * Filter max comment depth level
         *
         * Lite: Fixed at 2 levels
         * Pro: Returns user's setting (1-10 levels)
         *
         * @since 1.2.0
         * @param int $depth Maximum depth level
         */
        return apply_filters('radle_max_depth_level', 2);
    }

    public static function get_max_siblings() {
        /**
         * Filter max sibling comments
         *
         * Lite: Fixed at 5 siblings
         * Pro: Returns user's setting (5-30 siblings)
         *
         * @since 1.2.0
         * @param int $siblings Maximum siblings
         */
        return apply_filters('radle_max_siblings', 5);
    }

    public static function get_cache_duration() {
        /**
         * Filter cache duration
         *
         * Lite: Fixed at 0 (disabled)
         * Pro: Returns user's setting (0-86400 seconds)
         *
         * @since 1.2.0
         * @param int $duration Cache duration in seconds
         */
        return apply_filters('radle_cache_duration', 0);
    }

    public static function is_search_enabled() {
        /**
         * Filter search enabled status
         *
         * Lite: Fixed at false
         * Pro: Returns user's setting
         *
         * @since 1.2.0
         * @param bool $enabled Whether search is enabled
         */
        return apply_filters('radle_enable_search', false);
    }

    public static function show_badges() {
        /**
         * Filter show badges status
         *
         * Lite: Fixed at false
         * Pro: Returns user's setting
         *
         * @since 1.2.0
         * @param bool $show Whether to show badges
         */
        return apply_filters('radle_show_badges', false);
    }

    public static function show_comments_menu() {
        /**
         * Filter show comments menu status
         *
         * Lite: Fixed at true
         * Pro: Returns user's setting
         *
         * @since 1.2.0
         * @param bool $show Whether to show comments menu
         */
        return apply_filters('radle_show_comments_menu', true);
    }
}