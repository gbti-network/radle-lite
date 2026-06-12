<?php
/**
 * Radle Comments Search
 *
 * Extends the comments endpoint to add search functionality.
 * Hooks into WordPress filters to add a search parameter and filtering.
 *
 * @package Radle
 */

namespace Radle\Modules\Comments;

if (!defined('ABSPATH')) {
    exit;
}

class Comments_Search {

    /**
     * Initialize the comments search extension and register hooks.
     */
    public function __construct() {
        // Hook into REST API to add search parameter
        add_filter('radle_rest_comments_args', [$this, 'add_search_param'], 10, 3);

        // Hook into comments retrieval to apply search filter
        add_filter('radle_comments_data', [$this, 'apply_search_filter'], 10, 2);
    }

    /**
     * Add search parameter to REST API endpoint
     *
     * @param array $args Existing endpoint arguments
     * @param string $namespace REST namespace
     * @param string $base REST base path
     * @return array Modified arguments
     */
    public function add_search_param($args, $namespace, $base) {
        $args['search'] = [
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'description' => 'Search comments by text content'
        ];

        $this->log('Added search parameter to REST API endpoint');

        return $args;
    }

    /**
     * Apply search filter to comments
     *
     * Filters comments recursively based on search term.
     * Keeps parent comments if any child matches.
     *
     * @param array $comments_data Comment data with 'comments' array
     * @param \WP_REST_Request $request Request object
     * @return array Filtered comments data
     */
    public function apply_search_filter($comments_data, $request) {
        $search = trim($request->get_param('search') ?? '');
        $this->log("Search parameter: '{$search}'");

        // If no search term, return unfiltered
        if (empty($search)) {
            $this->log("Empty search, returning unfiltered comments");
            return $comments_data;
        }

        $original_count = count($comments_data['comments']);

        // Apply recursive search filter
        $comments_data['comments'] = $this->search_comments($comments_data['comments'], $search);

        $filtered_count = count($comments_data['comments']);
        $this->log("Applied search filter: '{$search}' - filtered from {$original_count} to {$filtered_count} results");

        return $comments_data;
    }

    /**
     * Recursively search comments
     *
     * Filters comments by search term. If a child comment matches,
     * the entire parent thread is kept to maintain context.
     * Marks matched comments with 'search_match' flag to bypass depth limits.
     *
     * @param array $comments Array of comments
     * @param string $search Search term
     * @param bool $parent_matched Whether parent was matched (for marking children)
     * @return array Filtered comments
     */
    private function search_comments($comments, $search, $parent_matched = false) {
        $search = strtolower($search);
        $filtered = [];

        foreach ($comments as &$comment) {
            // Skip "more replies" indicators
            if (isset($comment['more_replies'])) {
                $filtered[] = $comment;
                continue;
            }

            // Check if current comment matches
            $current_match = isset($comment['body']) &&
                           strpos(strtolower($comment['body']), $search) !== false;

            // Mark this comment as a search match so depth limits are bypassed
            if ($current_match) {
                $comment['search_match'] = true;
            }

            // Check children recursively
            $children_match = false;
            if (!empty($comment['children'])) {
                // Pass down match status so children know they're in a matched thread
                $filtered_children = $this->search_comments($comment['children'], $search, $current_match || $parent_matched);
                $children_match = !empty($filtered_children);

                // Update children with filtered results
                $comment['children'] = $filtered_children;

                // If any child matches, mark this comment to keep thread visible
                if ($children_match) {
                    $comment['search_result_parent'] = true;
                }
            }

            // Keep comment if it or any child matches
            if ($current_match || $children_match) {
                $filtered[] = $comment;
            }
        }

        return $filtered;
    }

    /**
     * Helper function for logging
     *
     * @param string $message Message to log
     */
    private function log($message) {
        if (defined('RADLE_LOGGING_ENABLED') && RADLE_LOGGING_ENABLED) {
            global $radleLogs;
            if ($radleLogs) {
                $radleLogs->log($message, 'comments');
            }
        }
    }
}
