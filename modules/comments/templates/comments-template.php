<?php
/**
 * Template for displaying Reddit comments with filters.
 */

use Radle\Modules\Comments\comments;

global $post;

$reddit_post_id = get_post_meta($post->ID, '_reddit_post_id', true);
$reddit_post_url = 'https://www.reddit.com/comments/' . $reddit_post_id;
$button_position = comments::$button_position;

if (!$reddit_post_id && is_admin()) {
    echo '<p>' . __('No Reddit post associated with this WordPress post.','radle-demo') . '</p>';
    return;
} elseif (!$reddit_post_id) {
    echo '<p class="nocomments">' . __('Comments disabled.','radle-demo') . '</p>';
    return;
}

echo '<div id="radle-comments-wrapper">';
// Render the filter UI
comments::render_filter_ui();

// Conditionally render the button above the comments but below the filter UI
if ($button_position === 'above' || $button_position === 'both') {
    comments::render_add_comment_button($reddit_post_url);
}

echo '<div id="radle-comments-container"><p>' . esc_html__('Loading comments...','radle-demo') . '</p></div>';
// Conditionally render the button below the comments
if ($button_position === 'below' || $button_position === 'both') {
    comments::render_add_comment_button($reddit_post_url);
}

echo '</div>';
$show_powered_by = get_option('radle_show_powered_by', 'no');
if ($show_powered_by === 'yes') {
    echo '<div class="powered-by-container">';
    echo ' <a href="'.$reddit_post_url.'" target="_blank" rel="nofollow" title="' . __('Powered by the Reddit API','radle-demo') . '"><img src="' . RADLE_PLUGIN_URL . 'assets/images/powered-by-reddit.webp" ></a>';
    echo ' <a href="https://gbti.network/radle" target="_blank" rel="nofollow" class="powered-by-radle" title="' . __('Powered by Radle WordPress Plugin','radle-demo') . ' ' . RADLE_VERSION .'">'. __('Radle','radle-demo') . ' ' . RADLE_VERSION .'</a>';
    echo '</div>';
}