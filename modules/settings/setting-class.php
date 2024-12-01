<?php

namespace Radle\Modules\Settings;

class Setting_Class {

    protected $settings_page;
    protected $settings_option_group;
    protected $settings_section;

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        // To be implemented by subclasses
    }

    protected function render_text_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<input type="text" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" />';
    }

    protected function render_textarea_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<textarea name="' . esc_attr($option_name) . '" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';
    }

    protected function render_select_field($option_name, $options, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
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
    }
}
