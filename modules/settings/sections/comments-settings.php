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
        register_setting($this->settings_option_group, 'radle_comment_system');
        register_setting($this->settings_option_group, 'radle_max_depth_level');
        register_setting($this->settings_option_group, 'radle_max_siblings');
        register_setting($this->settings_option_group, 'radle_cache_duration');
        register_setting($this->settings_option_group, 'radle_disable_search');
        register_setting($this->settings_option_group, 'radle_hide_comments_menu');
        register_setting($this->settings_option_group, 'radle_display_badges');
        register_setting($this->settings_option_group, 'radle_button_position');
        register_setting($this->settings_option_group, 'radle_show_powered_by');

        add_settings_section(
            $this->settings_section,
            __('Comment Settings', 'radle'),
            null,
            'radle-settings-comments'
        );

        add_settings_field(
            'radle_comment_system',
            __('Comment System', 'radle'),
            [$this, 'render_comment_system_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_depth_level',
            __('Max Depth Level', 'radle'),
            [$this, 'render_max_depth_level_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_max_siblings',
            __('Max Siblings', 'radle'),
            [$this, 'render_max_siblings_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_cache_duration',
            __('Cache Settings', 'radle'),
            [$this, 'render_cache_duration_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_disable_search',
            __('Disable Search Feature', 'radle'),
            [$this, 'render_disable_search_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_hide_comments_menu',
            __('Hide Comments Menu', 'radle'),
            [$this, 'render_hide_comments_menu_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_display_badges',
            __('Display Badges', 'radle'),
            [$this, 'render_display_badges_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_button_position',
            __('Add Comment Button', 'radle'),
            [$this, 'render_button_position_field'],
            'radle-settings-comments',
            $this->settings_section
        );

        add_settings_field(
            'radle_show_powered_by',
            __('Show Powered By', 'radle'),
            [$this, 'render_show_powered_by_field'],
            'radle-settings-comments',
            $this->settings_section
        );
    }

    public function render_comment_system_field() {
        $value = get_option('radle_comment_system', 'wordpress');
        $options = [
            'wordpress' => __('WordPress', 'radle'),
            'radle' => __('Radle', 'radle'),
            'disabled' => __('Disable All', 'radle'),
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
        $this->render_help_icon(__('Choose which commenting system to use on your site. By default this is set to use the WordPress comments. If Reddit Comments are selected, however, the WordPress comment system will be replaced with the Radle comments system.', 'radle'));
    }

    public function render_hide_comments_menu_field() {
        $value = get_option('radle_hide_comments_menu', 'no');
        echo '<select name="radle_hide_comments_menu">';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No', 'radle') . '</option>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes', 'radle') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose whether to hide the Comments menu item from the WordPress administration sidebar.', 'radle'));
    }

    public function render_display_badges_field() {
        $value = get_option('radle_display_badges', 'yes');
        echo '<select name="radle_display_badges">';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes', 'radle') . '</option>';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No', 'radle') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose whether to display badges (OP, MOD) next to usernames in comments.', 'radle'));
    }

    public function render_button_position_field() {
        $value = get_option('radle_button_position', 'below');
        echo '<select name="radle_button_position">';
        echo '<option value="above" ' . selected($value, 'above', false) . '>' . __('Above Comments', 'radle') . '</option>';
        echo '<option value="below" ' . selected($value, 'below', false) . '>' . __('Below Comments', 'radle') . '</option>';
        echo '<option value="both" ' . selected($value, 'both', false) . '>' . __('Both Above and Below', 'radle') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose where to display the "Add a comment" button relative to the comments section. Please note that if both is selected, we will only show both if there are more than 5 comments. ', 'radle'));
    }

    public function render_max_depth_level_field() {
        $value = get_option('radle_max_depth_level', 3);
        echo '<input type="number" name="radle_max_depth_level" value="' . esc_attr($value) . '" min="1" />';
        $this->render_help_icon(__('The maximum depth level for nested comments. This controls how deep the comment threads will be displayed. Default is 3.', 'radle'));
    }

    public function render_max_siblings_field() {
        $value = get_option('radle_max_siblings', 10);
        echo '<input type="number" name="radle_max_siblings" value="' . esc_attr($value) . '" min="1" />';
        $this->render_help_icon(__('The maximum number of sibling comments to display at each level. This controls how many comments are shown before a "load more" option appears. Default is 10 per level.', 'radle'));
    }

    public function render_cache_duration_field() {
        $options = [
            '0' => __('No caching', 'radle'),
            '300' => __('5 minutes', 'radle'),
            '600' => __('10 minutes', 'radle'),
            '900' => __('15 minutes', 'radle'),
            '1200' => __('20 minutes', 'radle'),
            '1500' => __('25 minutes', 'radle'),
            '1800' => __('30 minutes', 'radle'),
            '3600' => __('1 hour', 'radle'),
            '10800' => __('3 hours', 'radle'),
            '21600' => __('6 hours', 'radle'),
            '43200' => __('12 hours', 'radle'),
        ];

        $value = get_option('radle_cache_duration', '300');
        echo '<select name="radle_cache_duration">';
        foreach ($options as $seconds => $label) {
            echo '<option value="' . esc_attr($seconds) . '" ' . selected($value, $seconds, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        $this->render_help_icon(__('Radle leverages transients to temporarily cache results from the Reddit comments API. Use this setting to determine how long to cache results. This feature is enabled by default. The filter and search functionality will still operate normally with caching enabled, as each unique request will have its own cached data. Please be aware of your Reddit API usage and how it may scale with your own site traffic. For higher traffic sites, in order to remain within Reddit rate limits, consider setting a longer cache duration.', 'radle'));
    }

    public function render_disable_search_field() {
        $value = get_option('radle_disable_search', 'no');
        echo '<select name="radle_disable_search">';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No', 'radle') . '</option>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes', 'radle') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Disable the search feature for comments. On high-traffic sites, the search feature may not behave reliably due to Reddit API limiting searches to 1 every two seconds. Disabling search can help prevent rate limit issues.', 'radle'));
    }

    public function render_show_powered_by_field() {
        $value = get_option('radle_show_powered_by', 'no');
        echo '<select name="radle_show_powered_by">';
        echo '<option value="no" ' . selected($value, 'no', false) . '>' . __('No', 'radle') . '</option>';
        echo '<option value="yes" ' . selected($value, 'yes', false) . '>' . __('Yes', 'radle') . '</option>';
        echo '</select>';
        $this->render_help_icon(__('Choose whether to display the "Powered by Radle" container at the bottom of the comments section.', 'radle'));
    }

    private function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }
}