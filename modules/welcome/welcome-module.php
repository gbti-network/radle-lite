<?php

namespace Radle\Modules\Welcome;

use WP_REST_Request;
use Radle\Modules\Usage\Usage_Tracking;
use Radle\Modules\Reddit\Reddit_API;

class Welcome_Module {
    private $current_step = 1;
    private $total_steps = 7;
    private $option_name = 'radle_demo_welcome_progress';
    private $attribution_option = 'radle_demo_show_attribution';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_welcome_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'check_welcome_redirect']);
    }

    public function add_welcome_page() {
        add_submenu_page(
            null,
            __('Welcome to Radle', 'radle'),
            __('Radle Welcome', 'radle'),
            'manage_options',
            'radle-welcome',
            [$this, 'render_welcome_page']
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'admin_page_radle-welcome') {
            return;
        }

        wp_enqueue_style('radle-welcome-css', RADLE_PLUGIN_URL . 'modules/welcome/css/welcome.css', [], RADLE_VERSION);
        wp_enqueue_script('radle-welcome-js', RADLE_PLUGIN_URL . 'modules/welcome/js/welcome.js', ['jquery', 'radle-debug'], RADLE_VERSION, true);

        wp_localize_script('radle-welcome-js', 'radleWelcome', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'redditOAuthUrl' => rest_url('radle/v1/reddit/oauth-callback'),
            'i18n' => array(
                'enter_both_credentials' => __('Please enter both Client ID and Client Secret.', 'radle'),
                'redditAuthorizationFailed' => __('Reddit authorization failed. Please try again.', 'radle'),
            )
        ]);
    }

    public function check_welcome_redirect() {
        // Check if we're on the admin page and it's the Radle settings page
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'radle-settings') {
            $progress = get_option($this->option_name);

            // Redirect if progress is empty or less than total steps
            if ($progress === false || intval($progress) < $this->total_steps) {
                wp_redirect(admin_url('admin.php?page=radle-welcome'));
                exit;
            }
        }
    }

    public function render_welcome_page() {
        $this->current_step = (int) get_option($this->option_name, 1);

        echo '<div class="wrap radle-welcome">';
        echo '<img src="' . RADLE_PLUGIN_URL . 'assets/images/radle-logo-pattern.webp" alt="' . esc_attr__('Radle Logo', 'radle') . '" class="welcome-logo">';

        if ($this->current_step > 1) {
            $this->render_progress_bar();
        }

        $this->render_current_step();

        echo '</div>';
    }

    private function render_progress_bar() {
        $progress = ($this->current_step - 1) / ($this->total_steps - 1) * 100;
        echo "<div class='progress-bar'><div class='progress' style='width: {$progress}%'></div></div>";
    }

    private function render_current_step() {
        switch ($this->current_step) {
            case 1:
                $this->render_step_1();
                break;
            case 2:
                $this->render_step_2();
                $this->render_reset();
                break;
            case 3:
                $this->render_step_3();
                break;
            case 4:
                $this->render_step_4();
                $this->render_reset();
                break;
            case 5:
                $this->render_step_5();
                $this->render_reset();
                break;
            case 6:
                $this->render_step_6();
                $this->render_reset();
                break;
            case 7:
                $this->render_step_7();
                break;
            default:
                echo '<p>' . esc_html__('Unknown step', 'radle') . '</p>';
        }
    }

    private function render_reset() {
        echo '<div class="reset-container">';
        echo '<a href="#" class="reset-welcome">' . esc_html__('Reset Welcome Process', 'radle') . '</a>';
        echo '</div>';
    }

    private function render_step_1() {
        echo '<div class="welcome-step step-1" data-step="1">';
        echo '<button class="button button-primary get-started" data-step="2">' . esc_html__('Get Started', 'radle') . '</button>';
        echo '</div>';
    }

    private function render_step_2() {
        echo '<div class="welcome-step step-2" data-step="2">';
        echo '<h2>' . esc_html__('Set Up Reddit API Keys', 'radle') . '</h2>';
        echo '<p>' . esc_html__('To use Radle, you need to create a Reddit application and obtain API credentials.', 'radle') . '</p>';

        echo '<div class="setup-steps">';
        echo '<h3>' . esc_html__('Follow these steps:', 'radle') . '</h3>';
        echo '<ol>';
        echo '<li>' . esc_html__('Go to', 'radle') . ' <a href="https://www.reddit.com/prefs/apps" target="_blank">Reddit App Preferences</a></li>';
        echo '<li>' . esc_html__('Click on "Create App" or "Create Another App"', 'radle') . '</li>';
        echo '<li>' . esc_html__('Fill in the required fields:', 'radle') . '
            <ul>
                <li>' . esc_html__('Name: Choose a name for your app', 'radle') . '</li>
                <li>' . esc_html__('App type: Choose "Web app"', 'radle') . '</li>
                <li>' . esc_html__('Description: Optional', 'radle') . '</li>
                <li>' . esc_html__('About URL:', 'radle') . ' <code>' . esc_html(get_site_url()) . '</code></li>
                <li>' . esc_html__('Redirect URI:', 'radle') . ' <code>' . esc_html(rest_url('radle/v1/reddit/oauth-callback')) . '</code></li>
            </ul>
          </li>';
        echo '<li>' . esc_html__('Click "Create app"', 'radle') . '</li>';
        echo '<li>' . esc_html__('Copy the Client ID and Client Secret', 'radle') . '</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<input type="text" id="reddit_client_id" name="reddit_client_id" value="" placeholder="' . esc_attr__('Enter Client ID', 'radle') . '">';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<input id="reddit_client_secret" name="reddit_client_secret" value="" placeholder="' . esc_attr__('Enter Client Secret', 'radle') . '">';
        echo '</div>';
        echo '<div class="welcome-navigation">';
        echo '<button class="button button-large prev-step" data-step="1">' . esc_html__('PREVIOUS', 'radle') . '</button>';
        echo '<button class="button button-primary button-large next-step" data-step="3">' . esc_html__('NEXT', 'radle') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_step_3() {
        echo '<div class="welcome-step step-3" data-step="3">';
        echo '<h2>' . esc_html__('Authorize with Reddit', 'radle') . '</h2>';

        $redditAPI = Reddit_API::getInstance();

        if ($redditAPI->is_connected()) {
            echo '<p class="success-message">' . esc_html__('Reddit authorization successful!', 'radle') . '</p>';
            echo '<script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                RadleWelcome.updateProgress(4);
            });
        </script>';
        } else {
            echo '<p>' . esc_html__('Click the button below to authorize Radle with your Reddit account:', 'radle') . '</p>';

            echo '<button class="button button-large prev-step" data-step="2">' . esc_html__('PREVIOUS', 'radle') . '</button>';
            $auth_url = $redditAPI->get_authorization_url('welcome');
            echo '<a href="' . esc_url($auth_url) . '" class="button button-primary reddit-auth">' . esc_html__('Authorize with Reddit', 'radle') . '</a>';
        }

        echo '<div class="welcome-navigation">';
        echo '</div>';
        echo '</div>';
    }

    private function render_step_4() {
        echo '<div class="welcome-step step-4" data-step="4">';
        echo '<h2>' . esc_html__('Connect Subreddit', 'radle') . '</h2>';
        echo '<p>' . esc_html__('Please select the subreddit you want to connect to Radle.', 'radle') . '</p>';
        echo '<p>' . esc_html__('This is required for the plugin to operate.', 'radle') . '</p>';

        echo '<select id="radle-subreddit-select">';
        echo '<option value="">' . esc_html__('Select a subreddit', 'radle') . '</option>';
        echo '</select>';

        echo '<div class="welcome-navigation">';
        echo '<button class="button button-primary button-large next-step" data-step="5" disabled>' . esc_html__('NEXT', 'radle') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_step_5() {
        echo '<div class="welcome-step step-5" data-step="5">';
        echo '<h2>' . esc_html__('Enable Radle Comments', 'radle') . '</h2>';
        echo '<p>' . esc_html__('Would you like to use Radle\'s Reddit-powered comment system?', 'radle') . '</p>';
        echo '<p>' . esc_html__('This will replace the default WordPress comments with Reddit comments from your connected subreddit.', 'radle') . '</p>';

        echo '<div class="comment-choice-buttons">';
        echo '<button class="button button-primary enable-comments">' . esc_html__('Enable Radle Comments', 'radle') . '</button>';
        echo '<button class="button skip-comments">' . esc_html__('Not Now', 'radle') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_step_6() {
        echo '<div class="welcome-step step-6" data-step="6">';
        echo '<h2>' . esc_html__('Attribution', 'radle') . '</h2>';
        echo '<p>' . esc_html__('Would you like to display attribution to Radle in your comments section?', 'radle') . '</p>';

        echo '<div class="attribution-choice-buttons">';
        echo '<button class="button button-primary enable-attribution">' . esc_html__('Enable Attribution', 'radle') . '</button>';
        echo '<button class="button disable-attribution">' . esc_html__('Disable Attribution', 'radle') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_step_7() {
        $this->send_activation_event();

        echo '<div class="welcome-step step-7" data-step="7">';
        echo '<h2>' . esc_html__('Congratulations!', 'radle') . '</h2>';
        echo '<p>' . esc_html__('You have successfully set up Radle.', 'radle') . '</p>';
        echo '<a href="' . admin_url('admin.php?page=radle-settings') . '" class="button button-primary">' . esc_html__('Go to Radle Settings', 'radle') . '</a>';
        echo '</div>';
        
        // Add confetti script from local assets
        wp_enqueue_script('canvas-confetti', plugins_url('assets/libraries/confetti.min.js', RADLE_PLUGIN_FILE), array(), '1.6.0', true);
    }

    public function reset_progress() {
        update_option($this->option_name, 1);
        // Clear any other relevant options here
        return rest_ensure_response(['success' => true]);
    }

    private function send_activation_event() {
        global $radleLogs;

        $usage_tracking = new Usage_Tracking (
            'radle-demo',
            'gbti.network',
            RADLE_VERSION
        );

        $additional_data = [
            'plugin_version' => RADLE_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ];

        $usage_tracking->send_product_event('activated', $additional_data);

        $radleLogs->log('Sending activation event to remote product server.', 'welcome');

    }

    private function generate_site_id() {
        $site_id = get_option('radle_site_id');
        if (!$site_id) {
            $site_id = wp_hash(site_url() . time());
            update_option('radle_site_id', $site_id);
        }
        return $site_id;
    }

}