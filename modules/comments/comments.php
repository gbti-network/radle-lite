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
     * Initialize the comments module and set up necessary hooks.
     * 
     * Sets up the comment system based on plugin settings and adds
     * required WordPress action hooks for comments functionality.
     */
    public function __construct() {
        $this->comment_system = get_option('radle_comment_system', 'wordpress');
        $show_menu = get_option('radle_show_comments_menu', 'yes');

        add_action('init', [$this, 'handle_comment_system']);
        add_action('add_meta_boxes', [$this, 'add_comments_meta_box']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'] , 11);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Hide comments menu only if explicitly set to 'no'
        if ($show_menu === 'no') {
            add_action('admin_menu', [$this, 'hide_comments_menu']);
        }
    }

    /**
     * Handle the comment system based on plugin settings.
     * 
     * Switches between WordPress, Reddit, and disabled comment systems.
     */
    public function handle_comment_system() {
        switch ($this->comment_system) {
            case 'radle':
                $this->enable_radle_comments();
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
            return $metadata;
        });

        // Add editor styles for Radle comments
        add_action('enqueue_block_editor_assets', function() {
            wp_enqueue_style(
                'radle-editor-style',
                RADLE_PLUGIN_URL . 'modules/comments/css/editor-style.css',
                [],
                RADLE_VERSION
            );
        });
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
        if ($this->comment_system !== 'radle' || !is_singular('post')) {
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
     * Add the comments meta box to the post edit screen.
     */
    public function add_comments_meta_box() {
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
        // Check for theme-specific template
        $theme_template = self::get_theme_template('comments-template.php');

        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template
        return RADLE_PLUGIN_DIR . 'modules/comments/templates/comments-template.php';
    }

    /**
     * Render the filter UI for comments.
     * 
     * Displays a dropdown to filter comments by newest, oldest, or most popular.
     */
    public static function render_filter_ui() {
        $search_disabled = get_option('radle_disable_search', 'no') === 'yes';
        $filter_class = $search_disabled ? 'radle-comments-filter search-disabled' : 'radle-comments-filter';
        ?>
        <div class="<?php echo esc_attr($filter_class); ?>">
            <select id="radle-comments-sort">
                <option value="newest"><?php esc_html_e('Newest', 'radle-lite'); ?></option>
                <option value="most_popular"><?php esc_html_e('Most Popular', 'radle-lite'); ?></option>
                <option value="oldest"><?php esc_html_e('Oldest', 'radle-lite'); ?></option>
            </select>
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
