<?php

namespace Radle\Modules\Settings;

/**
 * Base class for handling WordPress settings fields.
 * 
 * This abstract class provides the foundation for creating
 * and managing WordPress settings fields. It includes:
 * - Basic field rendering methods
 * - Settings registration framework
 * - Common utility functions
 * 
 * Subclasses should implement specific settings sections
 * by extending this class and implementing the register_settings method.
 */
class Setting_Class {

    /**
     * Settings page identifier
     * @var string
     */
    protected $settings_page;

    /**
     * Settings option group name
     * @var string
     */
    protected $settings_option_group;

    /**
     * Settings section identifier
     * @var string
     */
    protected $settings_section;

    /**
     * Initialize the settings class.
     * 
     * Sets up WordPress hooks for registering settings.
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register settings with WordPress.
     * 
     * This method should be implemented by subclasses to register
     * their specific settings fields and sections.
     */
    public function register_settings() {
        // To be implemented by subclasses
    }

    /**
     * Render a text input field.
     * 
     * @param string $option_name Name of the option to store the value
     * @param string $default_value Default value for the field
     * @access protected
     */
    protected function render_text_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<input type="text" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" />';
    }

    /**
     * Render a textarea field.
     * 
     * @param string $option_name Name of the option to store the value
     * @param string $default_value Default value for the field
     * @access protected
     */
    protected function render_textarea_field($option_name, $default_value = '') {
        $value = get_option($option_name, $default_value);
        if (empty($value)) {
            $value = $default_value;
        }
        echo '<textarea name="' . esc_attr($option_name) . '" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';
    }

    /**
     * Render a select dropdown field.
     * 
     * @param string $option_name Name of the option to store the value
     * @param array $options Array of options (value => label)
     * @param string $default_value Default selected value
     * @access protected
     */
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
