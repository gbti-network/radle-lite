<?php

namespace Radle\Modules\Settings;

use Radle\Modules\Reddit\Reddit_API;

class Reddit_Api_Settings extends Setting_Class {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_settings';
        $this->settings_section = 'radle_reddit_settings_section';

        parent::__construct();
    }

    /**
     * Register the settings with WordPress.
     */
    public function register_settings() {

        register_setting($this->settings_option_group, 'radle_client_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_client_secret', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting($this->settings_option_group, 'radle_enable_rate_limit_monitoring', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting($this->settings_option_group, 'radle_destination_type', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_destination_type'],
        ]);

        add_settings_section(
            $this->settings_section,
            '',
            null,
            'radle-settings-reddit'
        );

        add_settings_field(
            'radle_client_id',
            __('Client ID','radle-lite'),
            function() { $this->render_text_field('radle_client_id'); },
            'radle-settings-reddit',
            $this->settings_section
        );

        add_settings_field(
            'radle_client_secret',
            __('Client Secret','radle-lite'),
            function() { $this->render_text_field('radle_client_secret'); },
            'radle-settings-reddit',
            $this->settings_section
        );

        add_settings_field(
            'radle_authorize',
            __('Authorize with Reddit','radle-lite'),
            function() { $this->render_authorize_button(); },
            'radle-settings-reddit',
            $this->settings_section
        );

        add_settings_field(
            'radle_reddit_documentation',
            __('Reddit API Documentation','radle-lite'),
            function() { $this->render_reddit_documentation(); },
            'radle-settings-reddit',
            $this->settings_section
        );

    }

    public function render_text_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<input type="text" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" />';

        $description = '';
        switch ($option_name) {
            case 'radle_client_id':
                $description = __('Your Reddit application\'s client ID.','radle-lite');
                break;
            case 'radle_client_secret':
                $description = __('Your Reddit application\'s client secret.','radle-lite');
                break;
        }

        if ($description) {
            $this->render_help_icon($description);
        }
    }

    private function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    public function render_authorize_button() {
        $client_id = get_option('radle_client_id');
        $client_secret = get_option('radle_client_secret');

        if ($client_id && $client_secret) {
            $redditAPI = Reddit_API::getInstance();
            $auth_url = $redditAPI->get_authorization_url('settings');
            echo '<a href="' . esc_url($auth_url) . '" class="button button-primary" id="radle-authorize-button">' . esc_html__('Authorize','radle-lite') . '</a>';
        } else {
            echo '<p class="reddit-authorize-prompt">' . esc_html__('Please fill in all the API settings to authorize.','radle-lite') . '</p>';
        }
    }

    public function render_reddit_documentation() {
        $redirect_uri = rest_url('radle/v1/reddit/oauth-callback');
        ?>
        <div id="reddit-api-documentation">
            <p><?php esc_html_e('To get your Reddit API keys, follow these steps:','radle-lite'); ?></p>
            <ol>
                <li><?php esc_html_e('Go to the Reddit App Preferences page: ','radle-lite'); ?><a href="https://www.reddit.com/prefs/apps" target="_blank"><?php esc_html_e('Reddit App Preferences','radle-lite'); ?></a></li>
                <li><?php esc_html_e('Scroll down to the "Developed Applications" section and click on "Create App" or "Create Another App".','radle-lite'); ?></li>
                <li><?php esc_html_e('Fill in the required fields. For "Redirect URI", use the following URL:','radle-lite'); ?></li>
                <code><?php echo esc_url($redirect_uri); ?></code>
                <li><?php esc_html_e('After creating the app, you will see your "Client ID" and "Client Secret". Copy these values.','radle-lite'); ?></li>
            </ol>
            <p><?php esc_html_e('Enter your Client ID, Client Secret into the settings area above.','radle-lite'); ?></p>
            <p><?php esc_html_e('To set the Reddit community (subreddit) to publish to, simply enter the subreddit name (e.g., GBTI_network) in the field provided.','radle-lite'); ?></p>
        </div>
        <?php
    }

    public function render_destination_type_field() {
        $value = get_option('radle_destination_type', 'subreddit');
        ?>
        <select name="radle_destination_type" id="radle_destination_type" style="min-width: 200px;">
            <option value="subreddit" <?php selected($value, 'subreddit'); ?>><?php esc_html_e('Subreddit', 'radle-lite'); ?></option>
            <option value="profile" <?php selected($value, 'profile'); ?>><?php esc_html_e('User Profile', 'radle-lite'); ?></option>
        </select>
        <?php
        $this->render_help_icon(__('Choose where posts should be published by default. Subreddit posts go to a community (requires subreddit name in welcome screen). Profile posts go to your personal Reddit profile (u/username).', 'radle-lite'));
    }

    public function sanitize_destination_type($value) {
        $value = sanitize_text_field($value);
        return in_array($value, ['subreddit', 'profile']) ? $value : 'subreddit';
    }

}
