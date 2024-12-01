<?php

namespace Radle\Modules\Settings;

class Settings_Container {

    protected $settings_page = 'radle-settings';
    protected $settings_title;
    protected $settings_menu;
    protected $tabs = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_radle_reset_authorization', [$this, 'reset_authorization']);

        $this->tabs = [
            'overview' => __('Overview','radle-demo'),
            'reddit' => __('Reddit Connection','radle-demo'),
            'publishing' => __('General Settings','radle-demo'),
            'comments' => __('Comment Management','radle-demo'),
            'monitoring' => __('Monitoring','radle-demo')
        ];

        $this->settings_title = __('Radle Settings','radle-demo');
        $this->settings_menu = __('Radle Settings','radle-demo');
    }

    public function create_settings_page() {
        add_options_page(
            $this->settings_title,
            $this->settings_menu,
            'manage_options',
            $this->settings_page,
            [$this, 'settings_page_content']
        );
    }

    public function settings_page_content() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->settings_title); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php
                foreach ($this->tabs as $tab_key => $tab_caption) {
                    $active = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
                    echo sprintf(
                        '<a href="?page=%s&tab=%s" class="nav-tab%s" data-tab="%s">%s</a>',
                        esc_attr($this->settings_page),
                        esc_attr($tab_key),
                        esc_attr($active),
                        esc_attr($tab_key),
                        esc_html($tab_caption)
                    );
                }
                ?>
            </h2>
            <style>
                .radle-settings-sections {
                    position: relative;
                }
                .radle-settings-section {
                    position: absolute;
                    width: 100%;
                    left: 0;
                    top: 0;
                }
                .radle-settings-section.active {
                    position: relative;
                }
            </style>
            <div class="radle-settings-sections">
            <?php
            // Create all tab sections upfront but hide them
            foreach ($this->tabs as $tab_key => $tab_caption) {
                $style = ($current_tab === $tab_key) ? '' : 'style="display: none;"';
                echo '<div id="radle-settings-' . esc_attr($tab_key) . '" class="radle-settings-section ' . 
                    esc_attr($current_tab === $tab_key ? 'active' : '') . '" ' . 
                    esc_attr($style) . '>';
                
                if ($tab_key === 'overview') {
                    $this->render_overview_tab();
                } else {
                    settings_errors();
                    echo '<form method="post" action="options.php">';
                    do_settings_sections($this->settings_page . '-' . $tab_key);
                    settings_fields('radle_settings');
                    if ($tab_key !== 'monitoring') {
                        submit_button();
                    }
                    echo '</form>';
                }
                
                echo '</div>';
            }
            ?>
            </div>
        </div>
        <?php
    }

    private function render_overview_tab() {
        ?>
        <div class="radle-overview-container">
            <div class="radle-overview-section">
                <h2><?php esc_html_e('Welcome to Radle Demo','radle-demo'); ?></h2>
                <p><?php esc_html_e('Radle integrates Reddit\'s discussion platform into your WordPress site, creating a vibrant community hub where your content and discussions thrive in both ecosystems.','radle-demo'); ?></p>
                
                <div class="radle-feature-matrix">
                    <h3><?php esc_html_e('Demo Features','radle-demo'); ?></h3>
                    <ul class="radle-feature-list">
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Reddit-Powered Comments System','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('One-Click Content Publishing','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Real-Time Comment Synchronization','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Essential Comment Sorting (Newest, Most Popular, Oldest)','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Basic Comment Threading (2 levels)','radle-demo'); ?>
                        </li>
                    </ul>

                    <h3><?php esc_html_e('Pro Features (Members Only)','radle-demo'); ?></h3>
                    <ul class="radle-feature-list pro-features">
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Custom Thread Depth & Expanded Replies','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Advanced Caching Controls','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Comment Search & Advanced Sorting','radle-demo'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('User Badges & Flair Support','radle-demo'); ?>
                        </li>
                    </ul>
                </div>

                <div class="radle-upgrade-cta">
                    <h3><?php esc_html_e('Join the GBTI Network','radle-demo'); ?></h3>
                    <p><?php 
                        printf(
                            /* translators: %s: URL to Radle Pro product page */
                            esc_html__('<a href="%s" target="_blank">Radle Pro</a> is available to all GBTI Network members. Sponsor our project on GitHub to become a network member and get access to all our premium plugins and tools.','radle-demo'),
                            esc_url('https://gbti.network/products/radle/')
                        );
                    ?></p>
                    <a href="https://github.com/sponsors/gbti-network" class="button button-primary radle-sponsor-button" target="_blank">
                        <svg class="radle-heart-icon" viewBox="0 0 16 16" width="16" height="16">
                            <path fill-rule="evenodd" d="M4.25 2.5c-1.336 0-2.75 1.164-2.75 3 0 2.15 1.58 4.144 3.365 5.682A20.565 20.565 0 008 13.393a20.561 20.561 0 003.135-2.211C12.92 9.644 14.5 7.65 14.5 5.5c0-1.836-1.414-3-2.75-3-1.373 0-2.609.986-3.029 2.456a.75.75 0 01-1.442 0C6.859 3.486 5.623 2.5 4.25 2.5z"></path>
                        </svg>
                        <?php esc_html_e('Become a Sponsor','radle-demo'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, $this->settings_page) === false) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_script(
            'chart-js',
            RADLE_PLUGIN_URL . 'modules/settings/js/chart.min.js',
            [],
            RADLE_VERSION,
            true
        );

        wp_enqueue_script(
            'radle-monitoring',
            RADLE_PLUGIN_URL . 'modules/settings/js/monitoring.js',
            ['jquery', 'chart-js'],
            RADLE_VERSION,
            true
        );

        wp_localize_script('radle-monitoring', 'radleMonitoring', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'timezone' => wp_timezone_string(),
            'i18n' => [
                'minutes' => esc_html__('Minutes','radle-demo'),
                'hours' => esc_html__('Hours','radle-demo'),
                'daysOfWeek' => esc_html__('Days of the Week','radle-demo'),
                'days' => esc_html__('Days','radle-demo'),
                'numberOfCalls' => esc_html__('Number of Calls','radle-demo'),
                'apiRateLimitData' => esc_html__('API Rate Limit Data','radle-demo'),
                'numberOfApiCalls' => esc_html__('Number of API calls','radle-demo'),
                'breachesOf90CallsPerMin' => esc_html__('Breaches of 90 calls/min','radle-demo'),
                'failedCallsDueToRateLimits' => esc_html__('Failed calls due to rate limits','radle-demo'),
                'confirmDeleteData' => esc_html__('Are you sure you want to delete all collected data? This action cannot be undone.','radle-demo'),
                'dataDeleted' => esc_html__('All data has been deleted.','radle-demo'),
                'errorDeletingData' => esc_html__('An error occurred while deleting the data. Please try again.','radle-demo'),
                'breachesAndFailures' => esc_html__('Breaches/Failures.','radle-demo'),
            ]
        ]);

        wp_enqueue_style('radle-admin-styles', RADLE_PLUGIN_URL . 'modules/settings/css/settings.css', [], RADLE_VERSION);
        wp_enqueue_script('radle-admin-scripts', RADLE_PLUGIN_URL . 'modules/settings/js/settings.js', ['jquery', 'wp-api' , 'radle-monitoring', 'radle-debug'], RADLE_VERSION, true);
        wp_localize_script('radle-admin-scripts', 'radleSettings', [
            'i18n' => [
                'noCompany' => __('No company','radle-demo'),
                'resetAuthorization' => __('Reset Authorization','radle-demo'),
                'latestReleases' => __('Latest Releases','radle-demo'),
                'updatesDisabled' => __('Plugin Updates are Currently Disabled','radle-demo'),
                'sponsorshipRequired' => __('This plugin requires an active GitHub sponsorship to receive updates.','radle-demo'),
                'becomeGitHubSponsor' => __('Become a GitHub Sponsor','radle-demo'),
                'sponsorCheckError' => __('Unable to verify sponsor status','radle-demo'),
                'sponsorCheckErrorDetail' => __('There was an error checking your GitHub sponsor status. Please try again later.','radle-demo'),     
                'AuthorizationReset' => __('Authorization has been reset.','radle-demo'),
                'redditFailedApiConnection' => __('Failed to connect to the Reddit API.','radle-demo'),
                'githubFailedApiConnection' => __('Connect to GBTI Network through GitHub to enable automatic updates','radle-demo'),
                'releasesFetchFail' => __('Failed to fetch releases:','radle-demo'),
                'resetWelcomeConfirm' => __('Are you sure you want to reset the welcome process?','radle-demo'),
                /* translators: %s: Click here text or link */
                'resetWelcomeProcess' => __('To reset the Radle welcome process, %s','radle-demo'),
                'welcomeResetSuccess' => __('Welcome process has been successfully reset.','radle-demo'),
                'welcomeResetFailed' => __('Failed to reset the welcome process.','radle-demo'),
                'welcomeResetError' => __('An error occurred while trying to reset the welcome process.','radle-demo'),
                'visitUserProfile' => __('Visit User Profile','radle-demo'),
                'recentPosts' => __('Recent Posts','radle-demo'),
                'selectSubreddit' => __('Select a subreddit','radle-demo'),
                'mustConnectSubreddit' => __('You must connect to a subreddit.','radle-demo'),
                'loadingEntries' => __('Loading entries...','radle-demo'),
                'noEntriesFound' => __('No entries found.','radle-demo'),
                'failedToLoadEntries' => __('Failed to load entries.','radle-demo'),
                'subredditUpdateFailed' => __('Subreddit selection failed','radle-demo'),
                'clickHere' => __('click here','radle-demo'),
                'raiseIssues' => __('Raise Issues','radle-demo'),
                'requestCustomizations' => __('Request Customizations','radle-demo'),
                'myGBTIAccount' => __('My GBTI Account','radle-demo'),
                'enjoyingRadle' => __('Enjoying Radle Demo?','radle-demo'),
            ],
            'redditOAuthUrl' => rest_url('radle/v1/reddit/oauth-callback'),
            'pluginUrl' => RADLE_PLUGIN_URL,
            'gbtiServerUri' => RADLE_GBTI_API_SERVER,
            'repoName' => RADLE_GITHUB_REPO,
            'githubToken' => get_option('radle_github_access_token'),
        ]);
    }

    public function reset_authorization() {
        check_ajax_referer('radle_nonce', '_wpnonce');

        delete_option('radle_reddit_access_token');
        delete_option('radle_raddit_refresh_token');

        wp_send_json_success(['message' => __('Authorization reset successfully.','radle-demo')]);
    }
}