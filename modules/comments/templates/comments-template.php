<?php
/**
 * Template for displaying Reddit comments with filters.
 */

use Radle\Modules\Comments\comments;

if ( ! defined( 'ABSPATH' ) ) exit;

global $post;

$reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
$reddit_post_url = 'https://www.reddit.com/comments/' . $reddit_post_id;
$button_position = comments::$button_position;

if (!$reddit_post_id && is_admin()) {
    echo '<p>' . esc_html__('No Reddit post associated with this WordPress post.','radle-lite') . '</p>';
    return;
} elseif (!$reddit_post_id) {
    echo '<p class="nocomments">' . esc_html__('Comments disabled.','radle-lite') . '</p>';
    return;
}

echo '<div id="radle-comments-wrapper">';
// Render the filter UI
comments::render_filter_ui();

// Conditionally render the button above the comments but below the filter UI
if ($button_position === 'above' || $button_position === 'both') {
    comments::render_add_comment_button($reddit_post_url);
}

echo '<div id="radle-comments-container"><p>' . esc_html__('Loading comments...','radle-lite') . '</p></div>';
// Conditionally render the button below the comments
if ($button_position === 'below' || $button_position === 'both') {
    comments::render_add_comment_button($reddit_post_url);
}

echo '</div>';
$show_powered_by = get_option('radle_show_powered_by', 'no');
if ($show_powered_by === 'yes') {
    echo '<div class="powered-by-container">';
    echo ' <a href="' . esc_url($reddit_post_url) . '" target="_blank" rel="nofollow" title="' . esc_attr__('Powered by the Reddit API','radle-lite') . '"><img src="' . esc_url(RADLE_PLUGIN_URL . 'assets/images/powered-by-reddit.webp') . '" ></a>';
    echo ' <a href="https://gbti.network/radle" target="_blank" rel="nofollow" class="powered-by-radle" title="' . esc_attr__('Powered by Radle WordPress Plugin','radle-lite') . ' ' . esc_attr(RADLE_VERSION) .'">'. esc_html__('Radle','radle-lite') . ' ' . esc_html(RADLE_VERSION) .'</a>';
    echo '</div>';
}