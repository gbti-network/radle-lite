<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;
use Radle\Modules\Settings\Comment_Settings;

class Comments_Endpoint extends WP_REST_Controller {

    private $maxDepthLevel;
    private $maxSiblings;
    private static $defaultSort = 'newest';

    public function __construct() {
        $this->maxDepthLevel = Comment_Settings::get_max_depth_level();
        $this->maxSiblings = Comment_Settings::get_max_siblings();

        $namespace = 'radle/v1';
        $base = 'reddit/comments';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_comments'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
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
                ]
            ],
        ]);
    }

    public function permissions_check($request) {
        if ($request->get_method() === 'GET') {
            return true;
        }
    }

    public function get_comments($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');
        $reddit_post_id = get_post_meta($post_id, '_reddit_post_id', true);

        if (!$reddit_post_id) {
            $radleLogs->log("No Reddit post associated with WordPress post ID: $post_id", 'comments');
            return new WP_Error('no_reddit_post', __('No Reddit post associated with this WordPress post.','radle-demo'), ['status' => 404]);
        }

        $radleLogs->log("Loading comments for : $post_id from reddit post $reddit_post_id", 'comments');

        $sort = $request->get_param('sort') ?? 'newest';
        $is_admin = $request->get_param('is_admin') ?? false;
        $can_edit_post = current_user_can('edit_post', $post_id);

        $redditAPI = Reddit_API::getInstance();
        $comments_data = $redditAPI->get_reddit_comments($reddit_post_id, $sort);

        if (empty($comments_data['comments'])) {
            $radleLogs->log("No comments found for Reddit post ID: $reddit_post_id", 'comments');
            return rest_ensure_response([]);
        }

        $radleLogs->log("Retrieved comments for Reddit post ID: $reddit_post_id", 'comments');

        // Apply sorting
        $sorted_comments = $this->sort_comments($comments_data['comments'], $sort);
        $radleLogs->log("Applied sorting: $sort", 'comments');

        // Apply depth and sibling limits
        $limited_comments = $this->apply_limits($sorted_comments, 0);
        $radleLogs->log("Applied depth and sibling limits", 'comments');

        // Retrieve hidden comments
        $hidden_comments = get_post_meta($post_id, '_radle_hidden_comments', true);
        if (!is_array($hidden_comments)) {
            $hidden_comments = [];
        }

        // Process hidden comments
        $limited_comments = $this->process_hidden_comments($limited_comments, $hidden_comments, !($is_admin || $can_edit_post));
        $radleLogs->log("Processed hidden comments", 'comments');

        return rest_ensure_response([
            'comments' => $limited_comments,
            'subreddit' => $comments_data['subreddit'],
            'reddit_post_id' => $reddit_post_id,
            'original_poster' => $comments_data['original_poster']
        ]);
    }

    private function process_hidden_comments($comments, $hidden_comments, $hide_for_frontend) {
        global $radleLogs;
        $processed_comments = array_map(function($comment) use ($hidden_comments, $hide_for_frontend, $radleLogs) {
            if (isset($comment['more_replies'])) {
                $comment['is_hidden'] = false;
                return $comment;
            }

            if (!isset($comment['id'])) {
                $radleLogs->log("Comment without ID encountered: " . print_r($comment, true), 'comments');
            }

            $is_hidden = in_array($comment['id'], $hidden_comments);
            if ($hide_for_frontend && $is_hidden) {
                return null;
            }
            $comment['is_hidden'] = $is_hidden;
            if (!empty($comment['children'])) {
                $comment['children'] = $this->process_hidden_comments($comment['children'], $hidden_comments, $hide_for_frontend);
            }
            return $comment;
        }, $comments);

        // Remove null values and re-index the array
        $processed_comments = array_values(array_filter($processed_comments));

        return $processed_comments;
    }

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
            
            if ($depth >= ( $this->maxDepthLevel -1 ) && !empty($comment['children'])) {
                $comment['more_nested_replies'] = true;
                $comment['children'] = [];
            } elseif (!empty($comment['children'])) {
                $comment['children'] = $this->apply_limits($comment['children'], $depth + 1, $comment['permalink']);
            }

            $limited_comments[] = $comment;
        }

        return $limited_comments;
    }
}