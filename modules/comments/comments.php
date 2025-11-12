<?php
namespace Radle\Modules\Comments;

/**
 * Manages the WordPress-Reddit comment integration system.
 * 
 * This class provides functionality to:
 * 1. Replace WordPress native comments with Reddit comments
 * 2. Disable comments completely
 * 3. Keep WordPress default commenting system
 * 
 * The class handles:
 * - Comment system switching between WordPress, Reddit, and disabled states
 * - Reddit comment display and rendering
 * - Comment-related assets (CSS/JS) management
 * - Block editor integration for FSE themes
 * - Admin UI elements for comment management
 */
class comments {

    /**
     * Flag to control visibility of comments menu in admin
     * @var bool
     */
    private $hide_comments_menu;

    /**
     * Flag to control display of user badges in comments
     * @var bool
     */
    private $display_badges;

    /**
     * Position of the comment button ('above' or 'below')
     * @var string
     */
    static $button_position;

    /**
     * Selected comment system ('wordpress', 'radle', or 'disabled')
     * @var string
     */
    private $comment_system;

    /**
     * Flag to prevent duplicate rendering
     * @var bool
     */
    private $radle_comments_rendered = false;

    /**
     * Initialize the comments module and set up necessary hooks.
     *
     * Sets up the comment system based on plugin settings and adds
     * required WordPress action hooks for comments functionality.
     */
    public function __construct() {
        $this->comment_system = get_option('radle_comment_system', 'wordpress');
        $show_menu = get_option('radle_show_comments_menu', 'yes');

        // Handle comment system setup - check for post override on template_redirect when post context is available
        add_action('template_redirect', [$this, 'setup_comment_system'], 1);

        add_action('add_meta_boxes', [$this, 'add_comments_meta_box']);
        add_action('save_post', [$this, 'save_comment_system_override']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'] , 11);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Hide comments menu only if explicitly set to 'no'
        if ($show_menu === 'no') {
            add_action('admin_menu', [$this, 'hide_comments_menu']);
        }
    }

    /**
     * Setup the comment system based on plugin settings.
     *
     * Called on template_redirect to ensure post context is available.
     * Checks for per-post override first, then applies the appropriate system.
     */
    public function setup_comment_system() {
        // Check for per-post override when viewing a post
        if (is_singular('post')) {
            global $post;
            if ($post) {
                $post_override = get_post_meta($post->ID, '_radle_comment_system_override', true);
                if ($post_override && $post_override !== 'default') {
                    $this->comment_system = $post_override;
                }
            }
        }

        // Apply the comment system
        switch ($this->comment_system) {
            case 'radle':
                $this->enable_radle_comments();
                break;
            case 'radle_above_wordpress':
                $this->enable_radle_above_wordpress();
                break;
            case 'radle_below_wordpress':
                $this->enable_radle_below_wordpress();
                break;
            case 'shortcode':
                $this->enable_shortcode_mode();
                break;
            case 'disabled':
                $this->disable_all_comments();
                break;
            case 'wordpress':
            default:
                // Do nothing, WordPress default commenting system will be used
                break;
        }
    }

    /**
     * Enable Reddit comments and set up necessary hooks.
     * 
     * Handles traditional themes and FSE themes separately.
     */
    private function enable_radle_comments() {
        $this->display_badges = get_option('radle_display_badges', 'yes');
        self::$button_position = get_option('radle_button_position', 'below');

        // Handle traditional themes
        add_filter('comments_template', [$this, 'radle_comments_template'], 100);
        add_filter('comments_open', '__return_true');
        add_filter('pings_open', '__return_false');

        // Handle FSE themes
        add_filter('render_block', function($block_content, $block) {
            // Replace the core comments block with Radle comments
            if ($block['blockName'] === 'core/comments') {
                global $post;
                ob_start();
                $this->render_comments($post);
                return ob_get_clean();
            }
            
            // Handle comments-query-loop block (used in newer block themes)
            if ($block['blockName'] === 'core/comments-query-loop') {
                global $post;
                ob_start();
                $this->render_comments($post);
                return ob_get_clean();
            }

            // Replace comment count with Radle comment count
            if ($block['blockName'] === 'core/post-comments-count') {
                global $post;
                $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
                if ($reddit_post_id) {
                    // You may want to implement a proper comment count fetching function
                    return '<span class="radle-comment-count">0</span>';
                }
                return '';
            }

            // Remove default latest comments block
            if ($block['blockName'] === 'core/latest-comments') {
                return '';
            }

            return $block_content;
        }, 10, 2);

        // Register Radle blocks for the editor
        add_action('init', function() {
            register_block_type('radle/comments', [
                'render_callback' => [$this, 'render_block_radle_comments'],
                'attributes' => [
                    'className' => [
                        'type' => 'string',
                        'default' => ''
                    ],
                ],
            ]);
        });

        // Add Radle block variations
        add_filter('block_type_metadata', function($metadata) {
            if (!empty($metadata['name']) && $metadata['name'] === 'core/comments') {
                $metadata['variations'] = array_merge(
                    isset($metadata['variations']) ? $metadata['variations'] : [],
                    [
                        [
                            'name' => 'radle',
                            'title' => 'Radle Comments',
                            'description' => 'Display comments from Reddit',
                            'attributes' => ['className' => 'is-style-radle'],
                        ]
                    ]
                );
            }
            
            // Add variation for comments-query-loop block
            if (!empty($metadata['name']) && $metadata['name'] === 'core/comments-query-loop') {
                $metadata['variations'] = array_merge(
                    isset($metadata['variations']) ? $metadata['variations'] : [],
                    [
                        [
                            'name' => 'radle',
                            'title' => 'Radle Comments',
                            'description' => 'Display comments from Reddit',
                            'attributes' => ['className' => 'is-style-radle'],
                        ]
                    ]
                );
            }
            
            return $metadata;
        });

        /* 
        add_action('enqueue_block_editor_assets', function() {
            wp_enqueue_style(
                'radle-editor-style',
                RADLE_PLUGIN_URL . 'modules/comments/css/editor-style.css',
                [],
                RADLE_VERSION
            );
        });
        */
    }

    /**
     * Render callback for the Radle comments block.
     * 
     * Renders the comments block for the editor.
     * 
     * @param array $attributes Block attributes
     * @return string Rendered block content
     */
    public function render_block_radle_comments($attributes) {
        global $post;

        if (!$post) {
            return '';
        }

        ob_start();
        $this->render_comments($post);
        return ob_get_clean();
    }

    /**
     * Enqueue necessary scripts for Radle comments.
     *
     * Enqueues scripts for the front-end and admin area.
     */
    public function enqueue_scripts() {
        $radle_modes = ['radle', 'radle_above_wordpress', 'radle_below_wordpress', 'shortcode'];

        if (!in_array($this->comment_system, $radle_modes) || !is_singular('post')) {
            return;
        }

        $this->enqueue_radle_assets();
    }

    /**
     * Enqueue necessary scripts for Radle comments in the admin area.
     * 
     * Enqueues scripts for the admin area.
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        $this->enqueue_radle_assets();
    }

    /**
     * Enqueue Radle assets (CSS/JS).
     * 
     * Enqueues necessary CSS and JS files for Radle comments.
     */
    private function enqueue_radle_assets() {
        wp_enqueue_style('dashicons');

        // Check for theme-specific styles
        $comments_css_url = $this->get_theme_template('comments.css', true);

        if ($comments_css_url) {
            wp_enqueue_style(
                'radle-comments',
                $comments_css_url,
                null,
                RADLE_VERSION
            );
        } else {
            wp_enqueue_style(
                'radle-comments',
                RADLE_PLUGIN_URL . 'modules/comments/css/comments.css',
                null,
                RADLE_VERSION
            );
        }


        $commens_js_url = $this->get_theme_template('comments.js', true);

        if ($commens_js_url) {

            wp_enqueue_script(
                'radle-comments',
                $commens_js_url,
                ['jquery' , 'wp-embed'],
                RADLE_VERSION,
                true
            );
        } else {
            wp_enqueue_script(
                'radle-comments',
                RADLE_PLUGIN_URL . 'modules/comments/js/comments.js',
                ['jquery' , 'wp-embed', 'radle-debug'],
                RADLE_VERSION,
                true
            );
        }


        wp_localize_script('radle-comments', 'radleCommentsSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'post_id' => get_the_ID(),
            'isPostPage' => is_singular('post'),
            'isPostEditPage' => is_admin() && in_array(get_current_screen()->id, ['post', 'post-new']),
            'canEditPost' => current_user_can('edit_post', get_the_ID()),
            'displayBadges' => \Radle\Modules\Settings\Comment_Settings::show_badges(),
            'buttonPosition' => get_option('radle_button_position', 'below'),
            'defaultSort' => \Radle\Modules\Settings\Comment_Settings::get_default_sort(),
            'i18n' => [
                'open_on_reddit' => __('Open on Reddit', 'radle-lite'),
                'reply_on_reddit' => __('Reply on Reddit', 'radle-lite'),
                'failed_to_load_comments' => __('Failed to load comments', 'radle-lite'),
                'no_comments_found' => __('No comments found', 'radle-lite'),
                'view_more_replies' => __('View More Replies', 'radle-lite'),
                'view_more_nested_replies' => __('View More Nested Replies', 'radle-lite'),
                'share' => __('Share', 'radle-lite'),
                'reply' => __('Reply', 'radle-lite'),
                'hide_from_blog_post' => __('Hide', 'radle-lite'),
                'show_in_blog_post' => __('Show', 'radle-lite'),
                'copied' => __('Copied', 'radle-lite'),
                'comment_link_copied' => __('Comment link copied to clipboard', 'radle-lite'),
                'op_badge' => __('OP', 'radle-lite'),
                'mod_badge' => __('MOD', 'radle-lite'),
                'loadingVideo' => __('Loading video...', 'radle-lite')
            ]
        ]);
    }

    /**
     * Hide the comments menu in the admin area.
     */
    public function hide_comments_menu() {
        remove_menu_page('edit-comments.php');
    }

    /**
     * Disable all comments functionality.
     * 
     * Disables comments on the front-end, removes comment support from post types,
     * and hides comment-related UI elements.
     */
    private function disable_all_comments() {
        // Close comments on the front-end
        add_filter('comments_open', '__return_false', 1);
        add_filter('pings_open', '__return_false', 1);

        // Hide existing comments
        add_filter('comments_array', '__return_empty_array', 1);

        // Remove comment support from all post types
        add_action('init', function() {
            $post_types = get_post_types(['public' => true], 'names');
            foreach ($post_types as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        }, 1);

        // Handle FSE themes
        add_filter('render_block', function($block_content, $block) {
            // Remove Comments block
            if ($block['blockName'] === 'core/comments') {
                return '';
            }
            // Remove Post Comments Count block
            if ($block['blockName'] === 'core/post-comments-count') {
                return '';
            }
            // Remove Latest Comments block
            if ($block['blockName'] === 'core/latest-comments') {
                return '';
            }
            return $block_content;
        }, 10, 2);

        // Remove block variations and patterns related to comments
        add_filter('block_type_metadata', function($metadata) {
            if (!empty($metadata['name']) && (
                    strpos($metadata['name'], 'core/comments') === 0 ||
                    strpos($metadata['name'], 'core/post-comments') === 0 ||
                    strpos($metadata['name'], 'core/latest-comments') === 0
                )) {
                return false;
            }
            return $metadata;
        });

        // Hide comment-related UI elements
        add_action('admin_init', function() {
            // Disable comment status dropdown on edit pages
            add_filter('admin_comment_types_dropdown', '__return_empty_array');

            // Remove comments metabox from dashboard
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

            // Remove comments column from posts/pages list
            add_filter('manage_posts_columns', function($columns) {
                unset($columns['comments']);
                return $columns;
            });
            add_filter('manage_pages_columns', function($columns) {
                unset($columns['comments']);
                return $columns;
            });
        });

        // Remove comments from admin bar
        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
        });

        // Remove comments from admin menu
        add_action('admin_menu', function() {
            remove_menu_page('edit-comments.php');
        });

        // Disable comments feed
        add_filter('feed_links_show_comments_feed', '__return_false');

        // Remove comments from REST API
        add_filter('rest_endpoints', function($endpoints) {
            if (isset($endpoints['/wp/v2/comments'])) {
                unset($endpoints['/wp/v2/comments']);
            }
            if (isset($endpoints['/wp/v2/comments/(?P<id>[\d]+)'])) {
                unset($endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
            }
            return $endpoints;
        });
    }

    /**
     * Enable Radle comments above WordPress comments.
     *
     * Displays Reddit comments before WordPress native comments.
     */
    private function enable_radle_above_wordpress() {
        $this->display_badges = get_option('radle_display_badges', 'yes');
        self::$button_position = get_option('radle_button_position', 'below');

        // Handle FSE/block themes - inject at the beginning of comments block
        add_filter('render_block', function($block_content, $block) {
            // Target the comments title or comment template blocks to inject before them
            if ($block['blockName'] === 'core/comments-title' && !$this->radle_comments_rendered) {
                $this->radle_comments_rendered = true;
                global $post;
                ob_start();
                $this->render_comments($post);
                $radle_output = ob_get_clean();
                return '<div class="radle-comments-above">' . $radle_output . '</div>' . $block_content;
            }

            // Fallback: if there's a comment-template block but no title, inject before it
            if ($block['blockName'] === 'core/comment-template' && !$this->radle_comments_rendered) {
                $this->radle_comments_rendered = true;
                global $post;
                ob_start();
                $this->render_comments($post);
                $radle_output = ob_get_clean();
                return '<div class="radle-comments-above">' . $radle_output . '</div>' . $block_content;
            }

            // Final fallback: if comment form appears first and no comments exist
            if ($block['blockName'] === 'core/post-comments-form' && !$this->radle_comments_rendered) {
                $this->radle_comments_rendered = true;
                global $post;
                ob_start();
                $this->render_comments($post);
                $radle_output = ob_get_clean();
                return '<div class="radle-comments-above">' . $radle_output . '</div>' . $block_content;
            }

            return $block_content;
        }, 10, 2);

        // Handle legacy themes - inject at the very start of comments section
        add_action('comments_template_top', function() {
            if (!$this->radle_comments_rendered && is_singular('post')) {
                $this->radle_comments_rendered = true;
                global $post;
                echo '<div class="radle-comments-above">';
                $this->render_comments($post);
                echo '</div>';
            }
        }, 1);

        // Fallback for themes that don't use comments_template() - render before wp_list_comments() outputs
        add_filter('comments_array', function($comments, $post_id) {
            if (!$this->radle_comments_rendered && is_singular('post') && get_the_ID() == $post_id) {
                $this->radle_comments_rendered = true;
                global $post;
                echo '<div class="radle-comments-above">';
                $this->render_comments($post);
                echo '</div>';
            }
            return $comments;
        }, 1, 2);

        add_filter('comments_open', '__return_true');
    }

    /**
     * Enable Radle comments below WordPress comments.
     *
     * Displays Reddit comments after WordPress native comments.
     */
    private function enable_radle_below_wordpress() {
        $this->display_badges = get_option('radle_display_badges', 'yes');
        self::$button_position = get_option('radle_button_position', 'below');

        // Handle FSE/block themes - inject after the comment form
        add_filter('render_block', function($block_content, $block) {
            // Target the comment form block to inject after it
            if ($block['blockName'] === 'core/post-comments-form' && !$this->radle_comments_rendered) {
                $this->radle_comments_rendered = true;
                global $post;
                ob_start();
                $this->render_comments($post);
                $radle_output = ob_get_clean();
                return $block_content . '<div class="radle-comments-below">' . $radle_output . '</div>';
            }

            return $block_content;
        }, 10, 2);

        // Handle legacy themes - inject at the end of comments section
        add_action('comment_form_after', function() {
            if (!$this->radle_comments_rendered && is_singular('post')) {
                $this->radle_comments_rendered = true;
                global $post;
                echo '<div class="radle-comments-below">';
                $this->render_comments($post);
                echo '</div>';
            }
        }, 99);

        add_filter('comments_open', '__return_true');
    }

    /**
     * Enable shortcode mode for manual Radle comments placement.
     *
     * Registers [radle_comments] shortcode for manual placement.
     * Disables WordPress comments only when shortcode mode is active for current post.
     */
    private function enable_shortcode_mode() {
        $this->display_badges = get_option('radle_display_badges', 'yes');
        self::$button_position = get_option('radle_button_position', 'below');

        // Register shortcode
        add_shortcode('radle_comments', [$this, 'render_shortcode_comments']);

        // Conditionally disable WordPress comments based on per-post setting
        add_filter('comments_open', function($open, $post_id) {
            if (!$this->is_shortcode_mode_active($post_id)) {
                return $open;
            }
            return false;
        }, 1, 2);

        add_filter('pings_open', function($open, $post_id) {
            if (!$this->is_shortcode_mode_active($post_id)) {
                return $open;
            }
            return false;
        }, 1, 2);

        add_filter('comments_array', function($comments, $post_id) {
            if (!$this->is_shortcode_mode_active($post_id)) {
                return $comments;
            }
            return [];
        }, 1, 2);

        // Handle FSE themes - conditionally remove comment blocks
        add_filter('render_block', function($block_content, $block) {
            global $post;
            if (!$post || !$this->is_shortcode_mode_active($post->ID)) {
                return $block_content;
            }

            if ($block['blockName'] === 'core/comments' ||
                $block['blockName'] === 'core/post-comments-count' ||
                $block['blockName'] === 'core/latest-comments' ||
                $block['blockName'] === 'core/comments-query-loop') {
                return '';
            }
            return $block_content;
        }, 10, 2);
    }

    /**
     * Check if shortcode mode is active for a specific post.
     *
     * @param int $post_id Post ID
     * @return bool True if shortcode mode is active, false otherwise
     */
    private function is_shortcode_mode_active($post_id) {
        if (!$post_id) {
            return false;
        }

        $global_setting = get_option('radle_comment_system', 'wordpress');
        $post_override = get_post_meta($post_id, '_radle_comment_system_override', true);

        // Determine active setting (post override takes precedence)
        $active_setting = ($post_override && $post_override !== 'default') ? $post_override : $global_setting;

        return $active_setting === 'shortcode';
    }

    /**
     * Render Radle comments via shortcode.
     *
     * Only renders if shortcode mode is enabled at global or post level.
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered comments HTML
     */
    public function render_shortcode_comments($atts = []) {
        global $post;

        if (!$post) {
            return '';
        }

        // Check if shortcode mode is enabled
        $global_setting = get_option('radle_comment_system', 'wordpress');
        $post_override = get_post_meta($post->ID, '_radle_comment_system_override', true);

        // Determine active setting (post override takes precedence)
        $active_setting = ($post_override && $post_override !== 'default') ? $post_override : $global_setting;

        // Only render if shortcode mode is active
        if ($active_setting !== 'shortcode') {
            return '';
        }

        ob_start();
        $this->render_comments($post);
        return ob_get_clean();
    }


    /**
     * Add the comments meta box to the post edit screen.
     */
    public function add_comments_meta_box() {
        // Comment system override meta box
        add_meta_box(
            'radle_comment_system_override',
            __('Comments', 'radle-lite'),
            [$this, 'render_comment_system_override_meta_box'],
            'post',
            'side',
            'high'
        );

        // Comments preview meta box
        add_meta_box(
            'radle_comments_meta_box',
            __('Reddit Comments', 'radle-lite'),
            [$this, 'render_comments'],
            'post',
            'advanced',
            'low'
        );
    }

    /**
     * Render the comment system override meta box.
     *
     * Allows users to override the global comment system setting per post.
     *
     * @param WP_Post $post Current post object
     */
    public function render_comment_system_override_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('radle_comment_system_override_nonce', 'radle_comment_system_override_nonce');

        // Get current override value
        $current_override = get_post_meta($post->ID, '_radle_comment_system_override', true);
        if (empty($current_override)) {
            $current_override = 'default';
        }

        // Get global setting for display
        $global_setting = get_option('radle_comment_system', 'wordpress');
        $global_label_map = [
            'wordpress' => __('WordPress', 'radle-lite'),
            'radle' => __('Radle', 'radle-lite'),
            'radle_above_wordpress' => __('Radle Above WordPress', 'radle-lite'),
            'radle_below_wordpress' => __('Radle Below WordPress', 'radle-lite'),
            'shortcode' => __('Shortcode Only', 'radle-lite'),
            'disabled' => __('Disable All', 'radle-lite'),
        ];

        $options = [
            /* translators: %s: the current global comment system setting (e.g., 'Radle', 'WordPress', 'Disabled') */
            'default' => sprintf(__('Use Global Setting (%s)', 'radle-lite'), $global_label_map[$global_setting] ?? $global_setting),
            'wordpress' => __('WordPress', 'radle-lite'),
            'radle' => __('Radle', 'radle-lite'),
            'radle_above_wordpress' => __('Radle Above WordPress', 'radle-lite'),
            'radle_below_wordpress' => __('Radle Below WordPress', 'radle-lite'),
            'shortcode' => __('Shortcode Only', 'radle-lite'),
            'disabled' => __('Disable All', 'radle-lite'),
        ];

        // Check if this is a Pro feature
        $is_pro_feature = apply_filters('radle_is_comment_override_pro_feature', true);
        $pro_badge = $is_pro_feature ? ' <span class="radle-pro-badge">' . __('Pro Only', 'radle-lite') . '</span>' : '';

        // Get the label for the current selection
        $current_label = $options[$current_override] ?? $options['default'];
        ?>
        <div class="radle-comment-system-override">
            <div class="radle-custom-dropdown radle-comment-override-dropdown">
                <div class="radle-dropdown-trigger" id="radle_comment_override_trigger">
                    <span class="radle-dropdown-selected"><?php echo esc_html($current_label); ?></span>
                    <span class="radle-dropdown-arrow">▼</span>
                </div>
                <div class="radle-dropdown-content" id="radle_comment_override_content">
                    <?php foreach ($options as $value => $label) : ?>
                        <a href="#" data-value="<?php echo esc_attr($value); ?>" class="radle-dropdown-option <?php echo ($current_override === $value) ? 'active' : ''; ?>">
                            <?php echo esc_html($label); ?>
                            <?php if ($value !== 'default' && $is_pro_feature) : ?>
                                <span class="radle-pro-badge"><?php esc_html_e('Pro Only', 'radle-lite'); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="radle_comment_system_override" id="radle_comment_system_override_input" value="<?php echo esc_attr($current_override); ?>">
            </div>
            <p class="description">
                <?php esc_html_e('Override the global comment system setting for this post only.', 'radle-lite'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save the comment system override meta box data.
     *
     * @param int $post_id Post ID
     */
    public function save_comment_system_override($post_id) {
        // Check if nonce is set
        if (!isset($_POST['radle_comment_system_override_nonce'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['radle_comment_system_override_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['radle_comment_system_override_nonce'])), 'radle_comment_system_override_nonce')) {
            return;
        }

        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if our field is set
        if (!isset($_POST['radle_comment_system_override'])) {
            return;
        }

        // Sanitize and save (unslash first to remove WordPress added slashes)
        $override_value = sanitize_text_field(wp_unslash($_POST['radle_comment_system_override']));

        // Validate the value
        $valid_values = ['default', 'wordpress', 'radle', 'radle_above_wordpress', 'radle_below_wordpress', 'shortcode', 'disabled'];
        if (in_array($override_value, $valid_values)) {
            // Check if this is a Pro-only feature
            $is_pro_feature = apply_filters('radle_is_comment_override_pro_feature', true);

            // If Pro feature and value is not 'default', only allow if Pro is active
            if ($is_pro_feature && $override_value !== 'default') {
                // Pro not active - force to default
                $override_value = 'default';
            }

            update_post_meta($post_id, '_radle_comment_system_override', $override_value);
        }
    }

    /**
     * Render the comments meta box content.
     *
     * Displays the Reddit comments for the current post.
     *
     * @param WP_Post $post Current post object
     */
    public static function render_comments($post) {
        // Check if we're in the admin area
        $is_admin = is_admin();
        // Get the Reddit post ID
        $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);

        // If there's no Reddit post ID and we're in the admin area, show a message
        if (!$reddit_post_id && $is_admin) {
            echo '<p>' . esc_html__('No Reddit post associated with this WordPress post.', 'radle-lite') . '</p>';
            return;
        }

        // Load the comments template
        load_template(self::get_comments_template(), false, [
            'post' => $post
        ]);
    }

    /**
     * Get the comments template file path.
     * 
     * Checks for a theme-specific template and falls back to the plugin's template.
     * 
     * @return string Template file path
     */
    private static function get_comments_template() {
        // Check for theme-specific template (highest priority)
        $theme_template = self::get_theme_template('comments-template.php');

        if ($theme_template) {
            return $theme_template;
        }

        // Check for Pro plugin template (second priority)
        if (defined('RADLE_PRO_DIR')) {
            $pro_template = RADLE_PRO_DIR . 'templates/comments-template.php';
            if (file_exists($pro_template)) {
                return $pro_template;
            }
        }

        // Fall back to Lite plugin's template
        return RADLE_PLUGIN_DIR . 'modules/comments/templates/comments-template.php';
    }

    /**
     * Render the filter UI for comments.
     *
     * Displays a dropdown to filter comments by newest, oldest, or most popular.
     * Conditionally displays search input if enabled (Pro feature).
     */
    public static function render_filter_ui() {
        $search_enabled = \Radle\Modules\Settings\Comment_Settings::is_search_enabled();
        $filter_class = $search_enabled ? 'radle-comments-filter' : 'radle-comments-filter search-disabled';

        // Get default sort from settings
        $default_sort = \Radle\Modules\Settings\Comment_Settings::get_default_sort();

        // Default sorting options (Lite)
        $sort_options = [
            'newest' => __('Newest', 'radle-lite'),
            'most_popular' => __('Most Popular', 'radle-lite'),
            'oldest' => __('Oldest', 'radle-lite'),
        ];

        /**
         * Filter to add additional sorting options (Pro)
         *
         * @param array $sort_options Array of sort value => label pairs
         */
        $sort_options = apply_filters('radle_comment_sort_options', $sort_options);

        ?>
        <div class="<?php echo esc_attr($filter_class); ?>">
            <div class="radle-custom-dropdown radle-sort-dropdown">
                <div class="radle-dropdown-trigger" id="radle_comments_sort_trigger">
                    <span class="radle-dropdown-selected"><?php echo esc_html($sort_options[$default_sort] ?? __('Newest', 'radle-lite')); ?></span>
                    <span class="radle-dropdown-arrow">▼</span>
                </div>
                <div class="radle-dropdown-content" id="radle_comments_sort_content">
                    <?php foreach ($sort_options as $value => $label): ?>
                        <a href="#" data-value="<?php echo esc_attr($value); ?>" class="radle-dropdown-option <?php echo $value === $default_sort ? 'active' : ''; ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="radle-comments-sort" value="<?php echo esc_attr($default_sort); ?>">
            </div>
            <?php if ($search_enabled): ?>
                <input type="text" id="radle-comments-search" placeholder="<?php esc_attr_e('Search comments', 'radle-lite'); ?>">
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the add comment button.
     * 
     * Displays a button to add a new comment on Reddit.
     * 
     * @param string $reddit_post_url Reddit post URL
     */
    public static function render_add_comment_button($reddit_post_url) {
        ?>
        <a href="<?php echo esc_url($reddit_post_url); ?>" target="_blank" rel="nofollow" class="radle-add-comment-button">
            <?php esc_html_e('Add a comment', 'radle-lite'); ?>
        </a>
        <?php
    }

    /**
     * Get a theme-specific template file path.
     * 
     * Checks for a template file in the current theme and falls back to the parent theme.
     * 
     * @param string $filename Template file name
     * @param bool $return_url Whether to return the URL or file path
     * @return string Template file path or URL
     */
    private static function get_theme_template($filename, $return_url = false) {
        $child_theme_path = get_stylesheet_directory() . '/radle/' . $filename;
        $parent_theme_path = get_template_directory() . '/radle/' . $filename;

        if (file_exists($child_theme_path)) {
            return $return_url ? get_stylesheet_directory_uri() . '/radle/' . $filename : $child_theme_path;
        } elseif (file_exists($parent_theme_path)) {
            return $return_url ? get_template_directory_uri() . '/radle/' . $filename : $parent_theme_path;
        }

        return false;
    }

    /**
     * Filter the comments template to use the Radle comments template.
     * 
     * Replaces the WordPress default comments template with the Radle comments template.
     * 
     * @param string $comment_template Comments template file path
     * @return string Radle comments template file path
     */
    public function radle_comments_template($comment_template) {
        $comment_system = get_option('radle_comment_system', 'wordpress');

        if ($comment_system !== 'radle' || !is_singular('post')) {
            return $comment_template;
        }

        global $post;

        /*
        $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
        if (!$reddit_post_id) {
            return $comment_template;
        }*/

        // Check for theme-specific template
        $theme_template = $this->get_theme_template('comments-template.php');
        if ($theme_template) {
            return $theme_template;
        }

        return RADLE_PLUGIN_DIR . 'modules/comments/templates/comments-template.php';
    }

    /**
     * Sort comments by a specified criteria.
     * 
     * Sorts comments by newest, oldest, or most popular.
     * 
     * @param array $comments Comments array
     * @param string $sort Sort criteria (newest, oldest, most_popular)
     * @return array Sorted comments array
     */
    private function sort_comments($comments, $sort = 'newest') {
        switch ($sort) {
            case 'oldest':
                usort($comments, function($a, $b) {
                    return $a->created_utc - $b->created_utc;
                });
                break;
            case 'most_popular':
                usort($comments, function($a, $b) {
                    return $b->score - $a->score;
                });
                break;
            case 'newest':
            default:
                usort($comments, function($a, $b) {
                    return $b->created_utc - $a->created_utc;
                });
                break;
        }
        return $comments;
    }

}
