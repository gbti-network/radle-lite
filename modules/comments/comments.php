<?php
namespace Radle\Modules\Comments;

class comments {

    private $hide_comments_menu;
    private $display_badges;
    static  $button_position;

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

    private function enable_radle_comments() {

        $this->display_badges = get_option('radle_display_badges', 'yes');
        self::$button_position = get_option('radle_button_position', 'below');

        add_filter('comments_template', [$this, 'radle_comments_template'], 100);
        add_filter('comments_open', '__return_true');
        add_filter('pings_open', '__return_false');
    }

    public function enqueue_scripts() {
        if ($this->comment_system !== 'radle' || !is_singular('post')) {
            return;
        }

        $this->enqueue_radle_assets();
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'post.php' && $hook != 'post-new.php') {
            return;
        }

        $this->enqueue_radle_assets();
    }

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
            'displayBadges' => get_option('radle_display_badges', 'yes') === 'yes',
            'buttonPosition' => get_option('radle_button_position', 'below'),
            'i18n' => [
                'open_on_reddit' => __('Open on Reddit', 'radle'),
                'reply_on_reddit' => __('Reply on Reddit', 'radle'),
                'failed_to_load_comments' => __('Failed to load comments', 'radle'),
                'no_comments_found' => __('No comments found', 'radle'),
                'view_more_replies' => __('View More Replies', 'radle'),
                'view_more_nested_replies' => __('View More Nested Replies', 'radle'),
                'share' => __('Share', 'radle'),
                'reply' => __('Reply', 'radle'),
                'hide_from_blog_post' => __('Hide', 'radle'),
                'show_in_blog_post' => __('Show', 'radle'),
                'copied' => __('Copied', 'radle'),
                'op_badge' => __('OP', 'radle'),
                'mod_badge' => __('MOD', 'radle'),
                'loadingVideo' => __('Loading video...', 'radle')
            ]
        ]);
    }

    public function hide_comments_menu() {
        remove_menu_page('edit-comments.php');
    }

    private function disable_all_comments() {
        // Close comments on the front-end
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);

        // Hide existing comments
        add_filter('comments_array', '__return_empty_array', 10, 2);

        // Remove comments links from admin bar
        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
        });
    }

    public function add_comments_meta_box() {
        add_meta_box(
            'radle_comments_meta_box',
            __('Reddit Comments', 'radle'),
            [$this, 'render_comments'],
            'post',
            'advanced',
            'low'
        );
    }

    public static function render_comments($post) {
        // Check if we're in the admin area
        $is_admin = is_admin();
        // Get the Reddit post ID
        $reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);

        // If there's no Reddit post ID and we're in the admin area, show a message
        if (!$reddit_post_id && $is_admin) {
            echo '<p>' . esc_html__('No Reddit post associated with this WordPress post.', 'radle') . '</p>';
            return;
        }

        // Load the comments template
        load_template(self::get_comments_template(), false, [
            'post' => $post
        ]);
    }

    private static function get_comments_template() {
        // Check for theme-specific template
        $theme_template = self::get_theme_template('comments-template.php');

        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template
        return RADLE_PLUGIN_DIR . 'modules/comments/templates/comments-template.php';
    }


    public static function render_filter_ui() {
        $search_disabled = get_option('radle_disable_search', 'no') === 'yes';
        $filter_class = $search_disabled ? 'radle-comments-filter search-disabled' : 'radle-comments-filter';
        ?>
        <div class="<?php echo esc_attr($filter_class); ?>">
            <select id="radle-comments-sort">
                <option value="newest"><?php esc_html_e('Newest', 'radle'); ?></option>
                <option value="most_popular"><?php esc_html_e('Most Popular', 'radle'); ?></option>
                <option value="oldest"><?php esc_html_e('Oldest', 'radle'); ?></option>
            </select>
        </div>
        <?php
    }


    public static function render_add_comment_button($reddit_post_url) {
        ?>
        <a href="<?php echo esc_url($reddit_post_url); ?>" target="_blank" rel="nofollow" class="radle-add-comment-button">
            <?php esc_html_e('Add a comment', 'radle'); ?>
        </a>
        <?php
    }

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
