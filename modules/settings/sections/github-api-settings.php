<?php

namespace Radle\Modules\Settings;

class GitHub_Api_Settings extends Setting_Class {

    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_settings';
        $this->settings_section = 'radle_github_api_settings_section';

        parent::__construct();
    }

    public function register_settings() {

        add_settings_section(
            $this->settings_section,
            __('', 'radle'),
            null,
            'radle-settings-github'
        );

        add_settings_field(
            'radle_github_authorize',
            __('Authorize', 'radle'),
            function() { $this->render_github_authorize_button(); },
            'radle-settings-github',
            $this->settings_section
        );

    }

    private function render_help_icon($description) {
        echo '<span class="button button-secondary radle-help-icon"><span class="dashicons dashicons-welcome-learn-more"></span></span>';
        echo '<p class="radle-help-description" style="display: none;">' . esc_html($description) . '</p>';
    }

    public function render_github_authorize_button() {

        $auth_url = RADLE_GBTI_API_SERVER . '/initiate';
        echo '<a href="' . esc_url($auth_url) . '" class="button button-primary" id="radle-github-authorize-button">' . __('Authorize With Github', 'radle') . '</a>';

    }

}