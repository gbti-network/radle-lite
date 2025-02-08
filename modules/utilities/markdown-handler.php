<?php

namespace Radle\Modules\Utilities;

/**
 * Lightweight Markdown parser for the Radle plugin.
 * 
 * Converts Markdown syntax to HTML with support for:
 * - Headers (h1-h6)
 * - Bold and italic text
 * - Links
 * - Ordered and unordered lists
 * - Code blocks and inline code
 * - Paragraphs
 * 
 * This is a simplified Markdown parser designed for basic
 * formatting needs in comments and content.
 */
class Markdown_Handler {

    /**
     * Parse Markdown text into HTML.
     * 
     * Converts the following Markdown elements:
     * - # Header 1 through ###### Header 6
     * - **bold text**
     * - *italic text*
     * - [link text](url)
     * - * and - for unordered lists
     * - 1. for ordered lists
     * - ```code blocks```
     * - `inline code`
     * - Blank lines for paragraphs
     * 
     * @param string $text Markdown text to parse
     * @return string HTML output
     * @static
     */
    public static function parse($text) {
        // Convert headers
        $text = preg_replace('/^######\s*(.*?)$/m', '<h6>$1</h6>', $text);
        $text = preg_replace('/^#####\s*(.*?)$/m', '<h5>$1</h5>', $text);
        $text = preg_replace('/^####\s*(.*?)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^###\s*(.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^##\s*(.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^#\s*(.*?)$/m', '<h1>$1</h1>', $text);

        // Convert bold
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

        // Convert italic
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

        // Convert links
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);

        // Convert unordered lists (asterisks and dashes)
        $text = preg_replace('/^[\*\-]\s*(.*?)$/m', '<ul><li>$1</li></ul>', $text);
        $text = preg_replace('/<\/ul>\s*<ul>/', '', $text);

        // Convert ordered lists
        $text = preg_replace('/^\d+\.\s*(.*?)$/m', '<ol><li>$1</li></ol>', $text);
        $text = preg_replace('/<\/ol>\s*<ol>/', '', $text);

        // Convert code blocks
        $text = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $text);

        // Convert inline code
        $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);

        // Convert paragraphs
        $text = '<p>' . preg_replace('/\n\s*\n/', '</p><p>', $text) . '</p>';

        return $text;
    }
}