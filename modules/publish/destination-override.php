<?php
/**
 * Radle - Destination Override Feature
 *
 * Adds per-post destination override functionality to the publish metabox.
 * Allows users to override the default destination (subreddit/profile) on a per-post basis.
 *
 * @package Radle
 */

namespace Radle\Modules\Publish;

if (!defined('ABSPATH')) {
    exit;
}

class Destination_Override {

    /**
     * Initialize destination override support.
     *
     * Registers all UI, filter, and asset hooks so that a bare
     * `new Destination_Override();` activates the feature.
     */
    public function __construct() {
        // Add destination override UI to publish metabox
        add_action('radle_before_post_type_selector', [$this, 'render_destination_override_ui'], 10, 1);
        add_action('radle_after_post_type_options', [$this, 'render_settings_icon'], 10, 1);

        // Hook into publish destination filter
        add_filter('radle_publish_destination', [$this, 'filter_publish_destination'], 10, 3);

        // Enqueue JavaScript and CSS for destination override
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Render destination override UI
     *
     * @param \WP_Post $post The current post object
     */
    public function render_destination_override_ui($post) {
        // Get Reddit username for user profile option
        $reddit_username = get_option('radle_reddit_username', '');

        ?>
        <div id="radle_destination_override" class="radle-destination-override" style="display: none; margin-bottom: 15px;">
            <p style="margin: 0 0 10px 0;">
                <label for="radle_override_destination" style="font-weight: 600;">
                    <?php esc_html_e('Destination:', 'radle-lite'); ?>
                </label>
            </p>
            <div class="radle-custom-dropdown">
                <div class="radle-dropdown-trigger" id="radle_override_destination_trigger">
                    <span class="radle-dropdown-selected"><?php esc_html_e('Use Default Destination', 'radle-lite'); ?></span>
                    <span class="radle-dropdown-arrow">▼</span>
                </div>
                <div class="radle-dropdown-content" id="radle_override_destination_content">
                    <a href="#" data-value="" class="radle-dropdown-option active">
                        <?php esc_html_e('Use Default Destination', 'radle-lite'); ?>
                    </a>
                    <?php if (!empty($reddit_username)): ?>
                        <a href="#" data-value="u_<?php echo esc_attr($reddit_username); ?>" class="radle-dropdown-option radle-user-profile">
                            <?php
                            /* translators: %s: Reddit username */
                            printf(esc_html__('User Profile (u/%s)', 'radle-lite'), esc_html($reddit_username));
                            ?>
                        </a>
                    <?php endif; ?>
                    <div class="radle-dropdown-divider"></div>
                    <div class="radle-dropdown-group" id="radle_subreddit_group">
                        <div class="radle-dropdown-group-label"><?php esc_html_e('Loading subreddits...', 'radle-lite'); ?></div>
                        <!-- Subreddits will be loaded via JavaScript -->
                    </div>
                </div>
                <input type="hidden" name="radle_override_destination" id="radle_override_destination" value="">
            </div>
        </div>
        <?php
    }

    /**
     * Render settings icon next to post type selector
     *
     * @param \WP_Post $post The current post object
     */
    public function render_settings_icon($post) {
        ?>
        <span class="dashicons dashicons-admin-generic radle-destination-settings-icon" id="radle_destination_settings_toggle" title="<?php esc_attr_e('Destination Settings', 'radle-lite'); ?>" style="cursor: pointer; color: #2271b1;"></span>
        <?php
    }

    /**
     * Enqueue JavaScript and CSS for destination override
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        wp_enqueue_script(
            'radle-destination-override',
            RADLE_PLUGIN_URL . 'modules/publish/js/destination-override.js',
            ['jquery', 'radle-publish'],
            RADLE_VERSION,
            true
        );

        wp_enqueue_style(
            'radle-destination-override',
            RADLE_PLUGIN_URL . 'modules/publish/css/destination-override.css',
            ['radle-publish'],
            RADLE_VERSION
        );
    }

    /**
     * Filter publish destination based on per-post override
     *
     * @param array $destination Default destination from Lite
     * @param \WP_REST_Request $request REST request object
     * @param int $post_id Post ID being published
     * @return array Modified destination
     */
    public function filter_publish_destination($destination, $request, $post_id) {
        global $radleLogs;

        // Check for override parameters
        $override_destination_type = $request->get_param('override_destination_type');
        $override_subreddit = $request->get_param('override_subreddit');

        // If override is provided, use it
        if (!empty($override_destination_type) || !empty($override_subreddit)) {

            if (!empty($override_destination_type)) {
                $destination['type'] = sanitize_text_field($override_destination_type);
            }

            if (!empty($override_subreddit)) {
                // Profile destinations arrive prefixed with "u_"; strip that prefix for
                // name validation, then re-apply it so the endpoint handles profiles correctly.
                $raw = sanitize_text_field($override_subreddit);
                $has_user_prefix = (strpos($raw, 'u_') === 0);
                $name = $has_user_prefix ? substr($raw, 2) : $raw;

                // 1) Shape guard: reject anything that is not a well-formed Reddit name.
                if (!preg_match('/^[A-Za-z0-9_]{2,21}$/', $name)) {
                    if (isset($radleLogs)) {
                        $radleLogs->log('Rejected malformed override subreddit supplied to destination override', 'radle-publish');
                    }
                    return $destination;
                }

                // 2) Authorization guard: only allow the user's own profile, or a
                // subreddit they actually moderate. Prevents an edit_posts user from
                // targeting an arbitrary destination.
                if ($has_user_prefix) {
                    $own_username = (string) get_option('radle_reddit_username', '');
                    if ($own_username === '' || strcasecmp($name, $own_username) !== 0) {
                        if (isset($radleLogs)) {
                            $radleLogs->log('Rejected profile override that does not match the authenticated user', 'radle-publish');
                        }
                        return $destination;
                    }
                    $destination['type'] = 'profile';
                    $destination['subreddit'] = 'u_' . $own_username;
                } else {
                    $moderated = \Radle\Modules\Reddit\Reddit_API::getInstance()->get_moderated_subreddits();
                    $is_moderated = false;
                    foreach ((array) $moderated as $mod_sub) {
                        if (strcasecmp($mod_sub, $name) === 0) {
                            $is_moderated = true;
                            break;
                        }
                    }
                    if (!$is_moderated) {
                        if (isset($radleLogs)) {
                            $radleLogs->log('Rejected override subreddit the user does not moderate: ' . $name, 'radle-publish');
                        }
                        return $destination;
                    }
                    $destination['type'] = 'subreddit';
                    $destination['subreddit'] = $name;
                }
            }
        }

        return $destination;
    }
}
