<?php
/**
 * Bulk Update Comment Placement Override
 *
 * USAGE: Copy this entire code block into your theme's functions.php file. Remove the preceeding <?php and the following ?>
 *
 * STEP 1: Configure the settings below (TARGET_PLACEMENT, etc.)
 * STEP 2: Visit: wp-admin/?run-manual-overwrite-for-radle=1
 * STEP 3: Remove this code from functions.php after it runs successfully
 *
 * Available placement options:
 * - 'wordpress'             - WordPress comments only
 * - 'radle'                 - Radle comments only
 * - 'radle_above_wordpress' - Radle above WordPress
 * - 'radle_below_wordpress' - Radle below WordPress
 * - 'shortcode'             - Shortcode only
 * - 'disabled'              - Disable all comments
 *
 * @package Radle_Lite
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================================
// CONFIGURATION - Modify these settings
// ============================================================================

define( 'RADLE_BULK_TARGET_PLACEMENT', 'radle' );              // What to set it to
define( 'RADLE_BULK_POST_TYPES', 'post' );                     // Post types (comma-separated: 'post,page')
define( 'RADLE_BULK_SKIP_ALREADY_SET', true );                 // Skip posts with existing overrides?
define( 'RADLE_BULK_ONLY_WITH_REDDIT', false );                // Only posts published to Reddit?
define( 'RADLE_BULK_DRY_RUN', false );                         // Test mode? (true = don't actually update)

// ============================================================================
// Script - Don't modify below this line
// ============================================================================

add_action( 'admin_init', function() {
    // Only run when URL parameter is present
    if ( ! isset( $_GET['run-manual-overwrite-for-radle'] ) || $_GET['run-manual-overwrite-for-radle'] !== '1' ) {
        return;
    }

    // Security check - admin only
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have permission to run this script.' );
    }

    // Prevent running multiple times
    if ( get_transient( 'radle_bulk_update_running' ) ) {
        wp_die( 'Script is already running or was recently run. Please wait 5 minutes before running again.' );
    }
    set_transient( 'radle_bulk_update_running', true, 5 * MINUTE_IN_SECONDS );

    // Get configuration from constants
    $target_placement = RADLE_BULK_TARGET_PLACEMENT;
    $post_types = array_map( 'trim', explode( ',', RADLE_BULK_POST_TYPES ) );
    $skip_already_set = RADLE_BULK_SKIP_ALREADY_SET;
    $only_with_reddit = RADLE_BULK_ONLY_WITH_REDDIT;
    $dry_run = RADLE_BULK_DRY_RUN;

    // Validate placement
    $valid_placements = [ 'wordpress', 'radle', 'radle_above_wordpress', 'radle_below_wordpress', 'shortcode', 'disabled' ];
    if ( ! in_array( $target_placement, $valid_placements, true ) ) {
        delete_transient( 'radle_bulk_update_running' );
        wp_die( 'Invalid placement option: ' . esc_html( $target_placement ) );
    }

    // Build query
    $query_args = [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all posts
        'fields'         => 'ids',
    ];

    // Only posts with Reddit associations
    if ( $only_with_reddit ) {
        $query_args['meta_query'] = [
            [
                'key'     => '_reddit_post_id',
                'compare' => 'EXISTS'
            ]
        ];
    }

    // Skip posts with existing overrides
    if ( $skip_already_set ) {
        if ( ! isset( $query_args['meta_query'] ) ) {
            $query_args['meta_query'] = [];
        }
        $query_args['meta_query']['relation'] = 'AND';
        $query_args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => '_radle_comment_system_override',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key'     => '_radle_comment_system_override',
                'value'   => 'default',
                'compare' => '='
            ]
        ];
    }

    // Get posts
    $post_ids = get_posts( $query_args );
    $total = count( $post_ids );
    $success = 0;
    $skipped = 0;
    $errors = 0;

    // Update posts
    foreach ( $post_ids as $post_id ) {
        if ( $dry_run ) {
            $success++;
            continue;
        }

        $updated = update_post_meta( $post_id, '_radle_comment_system_override', $target_placement );
        if ( $updated !== false ) {
            $success++;
        } else {
            $errors++;
        }
    }

    // Clear the running lock
    delete_transient( 'radle_bulk_update_running' );

    // Display results
    $mode_text = $dry_run ? ' [TEST MODE - NO CHANGES MADE]' : '';
    $message = sprintf(
        '<h1>Radle Bulk Update Complete%s</h1>
        <p><strong>Placement Set To:</strong> %s</p>
        <ul>
            <li><strong>Total Posts Found:</strong> %d</li>
            <li><strong>Successfully Updated:</strong> %d</li>
            <li><strong>Skipped:</strong> %d</li>
            <li><strong>Errors:</strong> %d</li>
        </ul>
        <p><a href="%s">← Back to Dashboard</a></p>
        <hr>
        <p><em>Remember to remove this code from your functions.php file!</em></p>',
        $mode_text,
        esc_html( $target_placement ),
        $total,
        $success,
        $skipped,
        $errors,
        admin_url()
    );

    wp_die( $message, 'Radle Bulk Update Results' );
} );
