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

        register_setting($this->settings_option_group, 'radle_comment_approval_filter', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_max_depth_level', [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_max_depth_level']
        ]);

        register_setting($this->settings_option_group, 'radle_max_siblings', [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_max_siblings']
        ]);

        register_setting($this->settings_option_group, 'radle_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_cache_duration']
        ]);

        register_setting($this->settings_option_group, 'radle_enable_search', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_yes_no']
        ]);

        register_setting($this->settings_option_group, 'radle_show_badges', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_yes_no']
        ]);

        register_setting($this->settings_option_group, 'radle_show_comments_menu', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_yes_no']
        ]);

        register_setting($this->settings_option_group, 'radle_default_sort', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_default_sort']
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
            'radle_comment_approval_filter',
            esc_html__('Comment Approval Filter','radle-lite'),
            [$this, 'render_comment_approval_filter_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_default_sort',
            esc_html__('Default Comment Sort','radle-lite'),
            [$this, 'render_default_sort_field'],
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
     * Clamp the max comment depth to the supported 1-10 range.
     */
    public function sanitize_max_depth_level($value) {
        $value = absint($value);
        return min(10, max(1, $value));
    }

    /**
     * Clamp the max sibling count to the supported 5-30 range.
     */
    public function sanitize_max_siblings($value) {
        $value = absint($value);
        return min(30, max(5, $value));
    }

    /**
     * Restrict cache duration to the offered values (0 = disabled).
     */
    public function sanitize_cache_duration($value) {
        $value = absint($value);
        $allowed = [0, 300, 600, 1800, 3600, 7200, 21600, 43200, 86400];
        return in_array($value, $allowed, true) ? $value : 0;
    }

    /**
     * Restrict a value to 'yes' or 'no'.
     */
    public function sanitize_yes_no($value) {
        return $value === 'yes' ? 'yes' : 'no';
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

    public function render_comment_approval_filter_field() {
        $value = get_option('radle_comment_approval_filter', 'show_all');
        echo '<select name="radle_comment_approval_filter">';
        echo '<option value="show_all" ' . selected($value, 'show_all', false) . '>' . esc_html__('Show All Comments','radle-lite') . '</option>';
        echo '<option value="approved_only" ' . selected($value, 'approved_only', false) . '>' . esc_html__('Show Only Approved Comments','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Choose which comments to display based on moderator approval status. "Show All Comments" displays all comments including those pending approval. This is the default setting. "Show Only Approved Comments" displays only comments that have been explicitly approved by a subreddit moderator, plus comments from the original poster and moderators (which do not require approval). Note: Comments that have been removed or banned by moderators will never be shown regardless of this setting.','radle-lite'));
    }

    public function render_default_sort_field() {
        $value = get_option('radle_default_sort', 'newest');

        // Sorting options.
        $sort_options = [
            'newest' => __('Newest', 'radle-lite'),
            'most_popular' => __('Most Popular', 'radle-lite'),
            'oldest' => __('Oldest', 'radle-lite'),
            'least_popular' => __('Least Popular', 'radle-lite'),
            'most_engaged' => __('Most Engaged', 'radle-lite'),
            'most_balanced' => __('Most Balanced', 'radle-lite'),
            'qa' => __('Q&A', 'radle-lite'),
        ];

        /**
         * Filter to add or remove sorting options.
         *
         * @param array $sort_options Array of sort value => label pairs
         */
        $sort_options = apply_filters('radle_comment_sort_options', $sort_options);

        echo '<select name="radle_default_sort">';
        foreach ($sort_options as $sort_value => $sort_label) {
            echo '<option value="' . esc_attr($sort_value) . '" ' . selected($value, $sort_value, false) . '>' . esc_html($sort_label) . '</option>';
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Choose the default sort order for comments when they first load. Users can still change the sort order using the dropdown menu. "Newest" shows most recent comments first, "Oldest" shows original comments first, and "Most Popular" shows highest voted comments first.','radle-lite'));
    }

    public function render_max_depth_level_field() {
        $value = (int) get_option('radle_max_depth_level', 2);
        echo '<select name="radle_max_depth_level">';
        for ($i = 1; $i <= 10; $i++) {
            printf(
                '<option value="%d" %s>%d</option>',
                absint($i),
                selected($value, $i, false),
                absint($i)
            );
        }
        echo '</select>';
        $this->render_help_icon(esc_html__('Maximum nesting depth for comment threads (1-10 levels). When this limit is reached, a "View More Nested Replies on Reddit" link is shown.','radle-lite'));
    }

    public function render_max_siblings_field() {
        $value = (int) get_option('radle_max_siblings', 10);
        echo '<select name="radle_max_siblings">';
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
        $this->render_help_icon(esc_html__('Maximum number of sibling comments (at the same level) to display (5-30). When this limit is exceeded, a "View More Replies on Reddit" link is shown.','radle-lite'));
    }

    public function render_cache_duration_field() {
        $value = (int) get_option('radle_cache_duration', 0);
        echo '<select name="radle_cache_duration">';

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
        $this->render_help_icon(esc_html__('Cache duration for Reddit comments. Caching from 5 minutes to 24 hours reduces Reddit API calls and improves performance. Set to "None" to disable caching.','radle-lite'));
    }

    public function render_enable_search_field() {
        $value = get_option('radle_enable_search', 'no');
        echo '<select name="radle_enable_search">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Enable comment search functionality, allowing visitors to search through comments to find specific discussions.','radle-lite'));
    }

    public function render_show_badges_field() {
        $value = get_option('radle_show_badges', 'no');
        echo '<select name="radle_show_badges">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show author badges next to comments (Original Poster, Moderator, and Pinned) for added context.','radle-lite'));
    }

    public function render_show_comments_menu_field() {
        $value = get_option('radle_show_comments_menu', 'yes');
        echo '<select name="radle_show_comments_menu">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . esc_html__('Yes','radle-lite') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . esc_html__('No','radle-lite') . '</option>';
        echo '</select>';
        $this->render_help_icon(esc_html__('Show the WordPress legacy comments menu in the admin bar. Set to "No" to remove this menu item from your wp-admin UI. Note: this affects native comments for all post types, not just Reddit.','radle-lite'));
    }

    public function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    // Configuration getters. These read the saved admin settings directly.
    // The apply_filters() calls are retained for forward-compatible extension.
    public static function get_max_depth_level() {
        /**
         * Filter max comment depth level (1-10).
         *
         * @since 1.2.0
         * @param int $depth Maximum depth level
         */
        return (int) apply_filters('radle_max_depth_level', (int) get_option('radle_max_depth_level', 2));
    }

    public static function get_max_siblings() {
        /**
         * Filter max sibling comments (5-30).
         *
         * @since 1.2.0
         * @param int $siblings Maximum siblings
         */
        return (int) apply_filters('radle_max_siblings', (int) get_option('radle_max_siblings', 10));
    }

    public static function get_cache_duration() {
        /**
         * Filter cache duration in seconds (0 = disabled).
         *
         * @since 1.2.0
         * @param int $duration Cache duration in seconds
         */
        return (int) apply_filters('radle_cache_duration', (int) get_option('radle_cache_duration', 0));
    }

    public static function is_search_enabled() {
        /**
         * Filter search enabled status.
         *
         * @since 1.2.0
         * @param bool $enabled Whether search is enabled
         */
        return apply_filters('radle_enable_search', get_option('radle_enable_search', 'no') === 'yes');
    }

    public static function show_badges() {
        /**
         * Filter show badges status.
         *
         * @since 1.2.0
         * @param bool $show Whether to show badges
         */
        return apply_filters('radle_show_badges', get_option('radle_show_badges', 'no') === 'yes');
    }

    public static function show_comments_menu() {
        /**
         * Filter show comments menu status.
         *
         * @since 1.2.0
         * @param bool $show Whether to show comments menu
         */
        return apply_filters('radle_show_comments_menu', get_option('radle_show_comments_menu', 'yes') === 'yes');
    }

    public static function get_comment_approval_filter() {
        /**
         * Get comment approval filter setting
         *
         * @since 1.2.2
         * @return string 'show_all' or 'approved_only' (default: 'show_all')
         */
        return get_option('radle_comment_approval_filter', 'show_all');
    }

    public function sanitize_default_sort($value) {
        $value = sanitize_text_field($value);
        $allowed_values = ['newest', 'oldest', 'most_popular', 'least_popular', 'most_engaged', 'most_balanced', 'qa'];

        /**
         * Filter allowed default sort values.
         *
         * @param array $allowed_values Array of allowed sort values
         */
        $allowed_values = apply_filters('radle_default_sort_allowed_values', $allowed_values);

        return in_array($value, $allowed_values, true) ? $value : 'newest';
    }

    public static function get_default_sort() {
        /**
         * Get default comment sort setting
         *
         * @since 1.2.2
         * @return string 'newest', 'oldest', or 'most_popular' (default: 'newest')
         */
        return get_option('radle_default_sort', 'newest');
    }
}