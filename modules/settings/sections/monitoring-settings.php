<?php

namespace Radle\Modules\Settings;

class Monitoring_Settings extends Setting_Class {

    public function __construct() {
        $this->settings_page = 'radle-settings';
        $this->settings_option_group = 'radle_settings';
        $this->settings_section = 'radle_monitoring_settings_section';

        parent::__construct();

    }

    public function register_settings() {


        add_settings_section(
            $this->settings_section,
            '',
            null,
            'radle-settings-monitoring'
        );


        add_settings_field(
            'radle_rate_limit_graph',
            __('Rate Limit Usage Graph','radle-lite'),
            [$this, 'render_rate_limit_graph'],
            'radle-settings-monitoring',
            $this->settings_section,
            ['class' => 'radle_rate_limit_graph']
        );
    }


    public function render_rate_limit_graph() {

        echo '<div class="radle-full-width-container">';
        echo '<div id="radle-graph-actions">';
        echo '<span id="radle-graph-refresh" class="button" title="' . esc_html__('Refresh Data','radle-lite') . '"><span class="dashicons dashicons-update"></span> </span>';
        echo '<span id="radle-graph-delete-data" class="button button-secondary" title="' . esc_html__('Delete All Data','radle-lite') . '"><span class="dashicons dashicons-trash"></span> </span>';
        echo '</div>';
        echo '<canvas id="radle-rate-limit-chart"></canvas>';
        echo '<div id="radle-graph-controls">';
        echo '<span id="radle-graph-last-hour" class="button"><span class="dashicons dashicons-clock"></span><span class="button-text">' . esc_html__('Last Hour','radle-lite') . '</span></span>';
        echo '<span id="radle-graph-24h" class="button"><span class="dashicons dashicons-calendar-alt"></span><span class="button-text">' . esc_html__('Last 24 Hours','radle-lite') . '</span></span>';
        echo '<span id="radle-graph-7d" class="button"><span class="dashicons dashicons-calendar"></span><span class="button-text">' . esc_html__('Last 7 Days','radle-lite') . '</span></span>';
        echo '<span id="radle-graph-30d" class="button"><span class="dashicons dashicons-calendar"></span><span class="button-text">' . esc_html__('Last 30 Days','radle-lite') . '</span></span>';
        echo '</div>';
        echo '</div>';
    }

}