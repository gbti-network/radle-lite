<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;
use Radle\Modules\Settings\Comment_Settings;

/**
 * Handles REST API endpoints for retrieving and managing Reddit comments.
 * 
 * This class provides functionality to fetch, sort, and filter Reddit comments
 * for WordPress posts. It includes features for:
 * - Comment depth limiting
 * - Sibling count limiting
 * - Multiple sorting options
 * - Hidden comment management
 * 
 * @since 1.0.0
 */
class Comments_Endpoint extends WP_REST_Controller {

    /**
     * Maximum depth level for nested comments.
     * @var int
     */
    private $maxDepthLevel;

    /**
     * Maximum number of sibling comments to display.
     * @var int
     */
    private $maxSiblings;

    /**
     * Default sorting method for comments.
     * @var string
     */
    private static $defaultSort = 'newest';

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for retrieving Reddit comments with
     * configurable sorting options and proper validation.
     */
    public function __construct() {
        $this->maxDepthLevel = Comment_Settings::get_max_depth_level();
        $this->maxSiblings = Comment_Settings::get_max_siblings();

        $namespace = 'radle/v1';
        $base = 'reddit/comments';

        $args = [
            'post_id' => [
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ],
            'sort' => [
                'default' => self::$defaultSort,
                'enum' => ['newest', 'most_popular', 'oldest']
            ]
        ];

        /**
         * Filter REST API endpoint arguments
         *
         * Allows Pro plugin to add additional parameters like 'search'
         *
         * @param array $args Endpoint arguments
         * @param string $namespace REST namespace
         * @param string $base REST base path
         */
        $args = apply_filters('radle_rest_comments_args', $args, $namespace, $base);

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_comments'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => $args
            ],
        ]);
    }

    /**
     * Check if the current user has permission to view comments.
     * 
     * GET requests are allowed for all users.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True for GET requests.
     */
    public function permissions_check($request) {
        if ($request->get_method() === 'GET') {
            return true;
        }
    }

    /**
     * Retrieve and process Reddit comments for a WordPress post.
     * 
     * Fetches comments from Reddit, applies sorting, depth limits,
     * and handles hidden comments based on user permissions.
     * 
     * @param \WP_REST_Request $request Request object containing post_id and sort parameters.
     * @return \WP_REST_Response|\WP_Error Response with comments or error if fetch fails.
     */
    public function get_comments($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');
        $reddit_post_id = get_post_meta($post_id, '_reddit_post_id', true);

        // Verify Reddit post association
        if (!$reddit_post_id) {
            $radleLogs->log("No Reddit post associated with WordPress post ID: $post_id", 'comments');
            return new WP_Error(
                'no_reddit_post',
                __('No Reddit post associated with this WordPress post.', 'radle-lite'),
                ['status' => 404]
            );
        }

        $radleLogs->log("Loading comments for : $post_id from reddit post $reddit_post_id", 'comments');

        // Get sort parameter, fallback to user's default setting
        $default_sort = \Radle\Modules\Settings\Comment_Settings::get_default_sort();
        $sort = $request->get_param('sort') ?? $default_sort;
        $is_admin = $request->get_param('is_admin') ?? false;
        $can_edit_post = current_user_can('edit_post', $post_id);

        // Fetch comments from Reddit
        $redditAPI = Reddit_API::getInstance();
        $comments_data = $redditAPI->get_reddit_comments($reddit_post_id, $sort);

        if (empty($comments_data['comments'])) {
            $radleLogs->log("No comments found for Reddit post ID: $reddit_post_id", 'comments');
            return rest_ensure_response([]);
        }

        $radleLogs->log("Retrieved comments for Reddit post ID: $reddit_post_id", 'comments');

        /**
         * Filter comments data before processing
         *
         * Allows Pro plugin to add search filtering
         *
         * @param array $comments_data Comment data with 'comments' array
         * @param \WP_REST_Request $request Request object
         */
        $comments_data = apply_filters('radle_comments_data', $comments_data, $request);

        // Process and sort comments
        $sorted_comments = $this->sort_comments($comments_data['comments'], $sort);
        $radleLogs->log("Applied sorting: $sort", 'comments');

        // Apply depth and sibling limits
        $limited_comments = $this->apply_limits($sorted_comments, 0);
        $radleLogs->log("Applied depth and sibling limits", 'comments');

        // Handle hidden comments
        $hidden_comments = get_post_meta($post_id, '_radle_hidden_comments', true);
        if (!is_array($hidden_comments)) {
            $hidden_comments = [];
        }

        $limited_comments = $this->process_hidden_comments(
            $limited_comments,
            $hidden_comments,
            !($is_admin || $can_edit_post)
        );
        $radleLogs->log("Processed hidden comments", 'comments');

        return rest_ensure_response([
            'comments' => $limited_comments,
            'subreddit' => $comments_data['subreddit'],
            'reddit_post_id' => $reddit_post_id,
            'original_poster' => $comments_data['original_poster']
        ]);
    }

    /**
     * Process and filter hidden comments based on user permissions.
     * 
     * @param array $comments Array of comments to process.
     * @param array $hidden_comments List of hidden comment IDs.
     * @param bool $hide_for_frontend Whether to hide comments for frontend users.
     * @return array Processed comments with hidden state applied.
     */
    private function process_hidden_comments($comments, $hidden_comments, $hide_for_frontend) {
        global $radleLogs;
        $processed_comments = array_map(function($comment) use ($hidden_comments, $hide_for_frontend, $radleLogs) {
            if (isset($comment['more_replies'])) {
                $comment['is_hidden'] = false;
                return $comment;
            }

            if (!isset($comment['id'])) {
                $radleLogs->log("Comment without ID encountered: " . wp_json_encode($comment), 'comments');
            }

            $is_hidden = in_array($comment['id'], $hidden_comments);
            if ($hide_for_frontend && $is_hidden) {
                return null;
            }
            $comment['is_hidden'] = $is_hidden;
            if (!empty($comment['children'])) {
                $comment['children'] = $this->process_hidden_comments(
                    $comment['children'],
                    $hidden_comments,
                    $hide_for_frontend
                );
            }
            return $comment;
        }, $comments);

        // Remove null values and re-index the array
        return array_values(array_filter($processed_comments));
    }

    /**
     * Sort comments recursively based on specified criteria.
     * 
     * @param array $comments Array of comments to sort.
     * @param string $sort Sort method to apply.
     * @return array Sorted comments.
     */
    private function sort_comments($comments, $sort) {
        $sort_function = $this->get_sort_function($sort);

        // Apply sorting to the top-level comments
        usort($comments, $sort_function);

        // Recursively sort child comments
        foreach ($comments as &$comment) {
            if (!empty($comment['children'])) {
                $comment['children'] = $this->sort_comments($comment['children'], $sort);
            }
        }

        return $comments;
    }

    /**
     * Get the appropriate sorting function based on sort method.
     * 
     * Available sort methods:
     * - newest: Sort by creation time, newest first
     * - oldest: Sort by creation time, oldest first
     * - most_popular: Sort by upvotes
     * - least_popular: Sort by score (ups - downs)
     * - most_engaged: Sort by total engagement (votes + replies)
     * - most_balanced: Sort by vote balance
     * - qa: Q&A style sorting (currently same as newest)
     * 
     * @param string $sort Sort method to use.
     * @return callable Sorting function.
     */
    private function get_sort_function($sort) {
        switch ($sort) {
            case 'newest':
                return function($a, $b) {
                    return ($b['created_utc'] ?? 0) - ($a['created_utc'] ?? 0);
                };
            case 'oldest':
                return function($a, $b) {
                    return ($a['created_utc'] ?? 0) - ($b['created_utc'] ?? 0);
                };
            case 'most_popular':
                return function($a, $b) {
                    return $b['ups'] - $a['ups'];
                };
            case 'least_popular':
                return function($a, $b) {
                    $a_score = ($a['ups'] ?? 0) - ($a['downs'] ?? 0);
                    $b_score = ($b['ups'] ?? 0) - ($b['downs'] ?? 0);
                    return $a_score - $b_score;
                };
            case 'most_engaged':
                return function($a, $b) {
                    $a_engagement = ($a['ups'] + $a['downs']) + count($a['children']);
                    $b_engagement = ($b['ups'] + $b['downs']) + count($b['children']);
                    return $b_engagement - $a_engagement;
                };
            case 'most_balanced':
                return function($a, $b) {
                    $a_balance = abs($a['ups'] - $a['downs']);
                    $b_balance = abs($b['ups'] - $b['downs']);
                    return $a_balance - $b_balance;
                };
            case 'qa':
                // Implement Q&A sorting logic here
                // For now, we'll just use the default "newest" sorting
                return function($a, $b) {
                    return ($b['created_utc'] ?? 0) - ($a['created_utc'] ?? 0);
                };
            default:
                // Default to 'newest' if an unknown sort option is provided
                return function($a, $b) {
                    return ($b['created_utc'] ?? 0) - ($a['created_utc'] ?? 0);
                };
        }
    }

    /**
     * Apply depth and sibling limits to comment threads.
     * 
     * Limits the number of visible comments based on:
     * - Maximum depth level (nested comments)
     * - Maximum number of siblings (comments at same level)
     * 
     * @param array $comments Comments to process.
     * @param int $depth Current depth level.
     * @param string $parent_permalink Parent comment permalink for more replies link.
     * @return array Limited comments with more replies indicators.
     */
    private function apply_limits($comments, $depth, $parent_permalink = '') {
        $limited_comments = [];
        $count = 0;
        
        foreach ($comments as $comment) {
            $count++;

            if ($count > $this->maxSiblings) {
                $limited_comments[] = [
                    'more_replies' => true,
                    'parent_permalink' => $parent_permalink ?: $comment['permalink'],
                    'count' => count($comments) - $this->maxSiblings
                ];
                break;
            }
            
            // Check if this is a search result - bypass depth limits for search
            $is_search_result = isset($comment['search_match']) || isset($comment['search_result_parent']);

            if ($depth >= ($this->maxDepthLevel - 1) && !empty($comment['children']) && !$is_search_result) {
                $comment['more_nested_replies'] = true;
                $comment['children'] = [];
            } elseif (!empty($comment['children'])) {
                $comment['children'] = $this->apply_limits(
                    $comment['children'],
                    $depth + 1,
                    $comment['permalink']
                );
            }

            $limited_comments[] = $comment;
        }

        return $limited_comments;
    }
}