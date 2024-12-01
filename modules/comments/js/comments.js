(function($) {

window.RadleComments = {
    commentsContainer: null,
    subreddit: '',
    redditPostId: '',
    originalPoster: '',
    currentSort: 'newest',
    currentSearch: '',
    debounceTimer: null,
    debug: new RadleDebugger('comments.js', false),  

    init: function() {
        this.debug.log('RadleComments initializing...');

        if (!radleCommentsSettings.isPostPage && !radleCommentsSettings.isPostEditPage) {
            this.debug.log('Skipping comments initialization - Not a post page or post edit page');
            return;
        }

        this.commentsContainer = document.getElementById('radle-comments-container');

        if (!this.commentsContainer) {
            this.debug.log('Comments initialization failed - Container #radle-comments-container not found');
            return;
        }

        this.debug.log('Comments container found, binding events...');
        this.bindEvents();
        this.debug.log('Events bound successfully');
        this.showSkeletonLoader();  // Show skeleton on initialization
        this.loadComments();
    },

    bindEvents: function() {
        this.debug.log('Binding comment events...');
        
        // sorting comments
        const sortSelect = document.getElementById('radle-comments-sort');
        if (sortSelect) {
            this.debug.log('Sort select found, adding change listener');
            sortSelect.addEventListener('change', this.handleSortChange.bind(this));
        }

        //searching comments
        const searchInput = document.getElementById('radle-comments-search');
        if (searchInput) {
            this.debug.log('Search input found, adding input listener');
            searchInput.addEventListener('input', this.handleSearch.bind(this));
        }

        //comment thread collapsing
        const collapseButtons = this.commentsContainer.querySelectorAll('.collapse-button');
        collapseButtons.forEach(button => {
            button.addEventListener('click', this.toggleCollapse.bind(this));
        });

        //hidind and showing comments
        if ( typeof jQuery('.radle-hide-comment') != 'undefined' ) {
            jQuery(this.commentsContainer).on('click', '.radle-hide-comment, .radle-show-comment', this.toggleCommentVisibility.bind(this));
        }

        this.debug.log('Comment events binding completed');
    },

    handleSortChange: function(event) {
        this.debug.log(`Sorting comments by: ${event.target.value}`);
        this.currentSort = event.target.value;
        this.showSkeletonLoader();  // Show skeleton on sort change
        this.loadComments();
    },

    handleSearch: function(event) {
        this.debug.log(`Search query updated: "${event.target.value}"`);
        clearTimeout(this.debounceTimer);
        this.currentSearch = event.target.value;
        this.showSkeletonLoader();  // Show skeleton on search change
        this.debounceTimer = setTimeout(() => {
            this.loadComments();
        }, 600);  // 1 second delay
    },

    showSkeletonLoader: function() {
        const skeletonHtml = `
            <div class="skeleton-wrapper">
                <ul class="reddit-comments">
                    ${this.getSkeletonComment()}
                    ${this.getSkeletonComment()}
                    ${this.getSkeletonComment()}
                </ul>
            </div>
        `;
        this.commentsContainer.innerHTML = skeletonHtml;
    },
    getSkeletonComment: function() {
        return `
            <li class="comment-wrapper">
                <div class="comment">
                    <div class="comment-avatar-wrapper skeleton-avatar"></div>
                    <div class="comment-content">
                        <div class="comment-meta">
                            <span class="skeleton skeleton-author"></span>
                            <span class="skeleton skeleton-time"></span>
                        </div>
                        <p class="skeleton skeleton-body"></p>
                    </div>
                </div>
                <ul class="nested-comments">
                    <li class="comment-wrapper">
                        <div class="comment">
                            <div class="comment-avatar-wrapper skeleton-avatar"></div>
                            <div class="comment-content">
                                <div class="comment-meta">
                                    <span class="skeleton skeleton-author"></span>
                                    <span class="skeleton skeleton-time"></span>
                                </div>
                                <p class="skeleton skeleton-body"></p>
                            </div>
                        </div>
                    </li>
                    <li class="comment-wrapper">
                        <div class="comment">
                            <div class="comment-avatar-wrapper skeleton-avatar"></div>
                            <div class="comment-content">
                                <div class="comment-meta">
                                    <span class="skeleton skeleton-author"></span>
                                    <span class="skeleton skeleton-time"></span>
                                </div>
                                <p class="skeleton skeleton-body"></p>
                            </div>
                        </div>
                    </li>
                </ul>
            </li>
        `;
    },
    loadComments: async function() {
        this.debug.log(`Loading comments... (Sort: ${this.currentSort}, Search: "${this.currentSearch}")`);
        try {
            const response = await fetch(`${radleCommentsSettings.root}radle/v1/reddit/comments?post_id=${radleCommentsSettings.post_id}&sort=${this.currentSort}&search=${this.currentSearch}&is_admin=${radleCommentsSettings.isPostEditPage}&can_edit_post=${radleCommentsSettings.canEditPost}`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': radleCommentsSettings.nonce
                }
            });
            const data = await response.json();
            this.subreddit = data.subreddit;
            this.redditPostId = data.reddit_post_id;
            this.originalPoster = data.original_poster;

            if (typeof data.comments == 'undefined') {
                data.comments = [];
            }

            this.renderComments(data.comments);
        } catch (error) {
            this.debug.error('Failed to load comments:', error);
            this.commentsContainer.innerHTML = `<p class="failed-to-load-comments">${radleCommentsSettings.i18n.failed_to_load_comments}</p>`;
        }
    },
    renderComments: function(comments) {
        this.debug.log(`Rendering ${this.countAllComments(comments)} total comments`);
        this.toggleTopCommentButton(comments);

        if (!comments || comments.length === 0) {
            this.commentsContainer.innerHTML = `<p class="no-comments-found">${radleCommentsSettings.i18n.no_comments_found}</p>`;
            return;
        }

        const commentsHtml = this.buildCommentsHtml(comments);
        this.commentsContainer.innerHTML = commentsHtml;
        this.debug.log('Comments rendered successfully');
        this.addEventListeners();

    },

    toggleTopCommentButton: function(comments) {

        if (radleCommentsSettings.buttonPosition !== 'both') {
            return;
        }

        const topButton = document.querySelector('.radle-add-comment-button:first-of-type');
        if (!topButton) {
            return;
        }

        const commentCount = this.countAllComments(comments);

        if ( commentCount < 5 ) {
            topButton.style.display = 'none';
        } else {
            topButton.style.display = 'block';
        }
    },

    countAllComments: function(comments) {

        if (comments.length === 0 ) {
            return 0;
        }

        let count = 0;
        for (let comment of comments) {
            if (comment.more_replies) {
                count += comment.count || 0;
            } else {
                count++;
                if (comment.children && comment.children.length > 0) {
                    count += this.countAllComments(comment.children);
                }
            }
        }
        return count;
    },
    buildCommentsHtml: function(comments, depth = 0) {
        let html = depth === 0 ? '<ul class="reddit-comments">' : '<ul class="nested-comments">';

        comments.forEach((comment) => {
            if (comment.more_replies) {
                const moreRepliesLink = `https://www.reddit.com${comment.parent_permalink}`;
                html += `
                <li class="more-replies">
                    <a href="${moreRepliesLink}" target="_blank" rel="nofollow">+ ${radleCommentsSettings.i18n.view_more_replies} (${comment.count} more)</a>
                </li>
                `;
                return;
            }

            const { profile_picture, author, body, ups, downs, permalink, children, more_nested_replies, is_op, is_mod, is_hidden } = comment;

            const hiddenClass = is_hidden ? 'radle-hidden-comment' : '';

            const processedBody = this.convertMarkdown(body || '');

            html += `
            <li class="comment-wrapper ${hiddenClass}">
                <div class="collapse-button"></div>
                <div class="comment">
                    <div class="comment-avatar-wrapper">
                        <img src="${profile_picture || 'default-avatar.png'}" alt="${author}" class="comment-avatar" />
                    </div>
                    <div class="comment-content">
                        <div class="comment-meta">
                            <span class="comment-author">${author || 'Anonymous'}</span>
                            ${this.renderBadges(is_op, is_mod)}
                            <span class="comment-time">• ${this.formatTimestamp(comment.created_utc)}</span>
                        </div>
                        <p class="comment-body">${processedBody}</p>
                        <div class="comment-actions">
                            <a href="https://www.reddit.com${permalink}" target="_blank" rel="nofollow" class="comment-action upvote">
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </a>
                            <span class="vote-count">${ups - downs}</span>
                            <a href="https://www.reddit.com${permalink}" target="_blank" rel="nofollow" class="comment-action downvote">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </a>
                            <a href="https://www.reddit.com${permalink}" target="_blank" rel="nofollow" class="comment-action reply">
                                <span class="dashicons dashicons-admin-comments"></span> ${radleCommentsSettings.i18n.reply}
                            </a>
                            <a href="#" class="comment-action share" data-permalink="${permalink}">
                                <span class="dashicons dashicons-share"></span> ${radleCommentsSettings.i18n.share}
                            </a>`;

            if (radleCommentsSettings.isPostEditPage || radleCommentsSettings.canEditPost) {
                const hideText = is_hidden ? radleCommentsSettings.i18n.show_in_blog_post : radleCommentsSettings.i18n.hide_from_blog_post;
                const hideClass = is_hidden ? 'radle-show-comment' : 'radle-hide-comment';
                const iconClass = is_hidden ? 'dashicons-visibility' : 'dashicons-hidden';
                html += `
                <a href="#" class="comment-action ${hideClass}" data-comment-id="${comment.id}">
                    <span class="dashicons ${iconClass}"></span> ${hideText}
                </a>`;
            }

            html += `
                    </div>
                </div>
            </div>
        `;

            if (children && children.length > 0) {
                html += this.buildCommentsHtml(children, depth + 1);
            }

            if (more_nested_replies) {
                const moreNestedRepliesLink = `https://www.reddit.com${permalink}`;
                html += `
            <div class="more-replies">
                <a href="${moreNestedRepliesLink}" target="_blank" rel="nofollow">+ ${radleCommentsSettings.i18n.view_more_nested_replies}</a>
            </div>
            `;
            }

            html += '</li>';
        });

        html += '</ul>';
        return html;
    },
    convertMarkdown: function(text) {
        // Helper function to encode URLs
        const encodeUrl = (url) => {
            return encodeURI(url).replace(/'/g, "%27").replace(/"/g, "%22");
        };

        // Helper function to check if a URL is a YouTube link
        const isYouTubeLink = (url) => {
            return /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/.test(url);
        };

        // Helper function to get YouTube video ID
        const getYouTubeVideoId = (url) => {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : null;
        };

        // Function to create YouTube embed
        const createYouTubeEmbed = (url) => {
            const videoId = getYouTubeVideoId(url);
            if (videoId) {
                return `<div class="youtube-embed">
                <iframe width="560" height="315" 
                src="https://www.youtube.com/embed/${videoId}" 
                frameborder="0" 
                allow="autoplay; encrypted-media" 
                allowfullscreen></iframe>
            </div>`;
            }
            return `<a href="${encodeUrl(url)}" target="_blank" rel="nofollow">${url}</a>`;
        };

        // Unescape any pre-escaped square brackets
        text = text.replace(/\\\[/g, '[').replace(/\\\]/g, ']');

        // Array to store YouTube embeds
        const youtubeEmbeds = [];

        // Handle Markdown links and YouTube embeds
        text = text.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, (match, linkText, url) => {
            if (isYouTubeLink(url)) {
                const embed = createYouTubeEmbed(url);
                youtubeEmbeds.push(embed);
                return `{{YOUTUBE_EMBED_${youtubeEmbeds.length - 1}}}`;
            }
            return `<a href="${encodeUrl(url)}" target="_blank" rel="nofollow">${linkText}</a>`;
        });

        // Handle plain URLs
        text = text.replace(/(?<!["'<])(https?:\/\/[^\s<>"']+)/g, (match, url) => {
            if (isYouTubeLink(url)) {
                const embed = createYouTubeEmbed(url);
                youtubeEmbeds.push(embed);
                return `{{YOUTUBE_EMBED_${youtubeEmbeds.length - 1}}}`;
            }
            return `<a href="${encodeUrl(url)}" target="_blank" rel="nofollow">${url}</a>`;
        });

        // Escape HTML characters
        text = text.replace(/[&<>"']/g, (match) => {
            const escapeChars = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return escapeChars[match];
        });

        // Convert headers
        text = text.replace(/^#{1,6}\s+(.*)$/gm, (match, content) => {
            const level = match.trim().split(' ')[0].length;
            return `<h${level}>${content}</h${level}>`;
        });

        // Convert bold
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Convert italic
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Convert strikethrough
        text = text.replace(/~~(.*?)~~/g, '<del>$1</del>');

        // Convert ordered lists
        text = text.replace(/^\d+\.\s+(.*)$/gm, '<li>$1</li>');
        text = text.replace(/(<li>.*<\/li>(\n|$))+/g, (match) => `<ol>${match}</ol>`);

        // Convert unordered lists
        text = text.replace(/^[-*]\s+(.*)$/gm, '<li>$1</li>');
        text = text.replace(/(<li>.*<\/li>(\n|$))+/g, (match) => `<ul>${match}</ul>`);

        // Convert blockquotes
        text = text.replace(/^>\s+(.*)$/gm, '<blockquote>$1</blockquote>');

        // Convert line breaks
        text = text.replace(/\n\n/g, '<br><br>');

        // Replace YouTube embed placeholders with actual embeds
        youtubeEmbeds.forEach((embed, index) => {
            text = text.replace(`{{YOUTUBE_EMBED_${index}}}`, embed);
        });

        return text;
    },
    formatTimestamp: function(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;
        const minute = 60;
        const hour = minute * 60;
        const day = hour * 24;
        const week = day * 7;
        const month = day * 30;
        const year = day * 365;

        if (diff < minute) {
            return 'just now';
        } else if (diff < hour) {
            const minutes = Math.floor(diff / minute);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diff < day) {
            const hours = Math.floor(diff / hour);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else if (diff < week) {
            const days = Math.floor(diff / day);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        } else if (diff < month) {
            const weeks = Math.floor(diff / week);
            return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
        } else if (diff < year) {
            const months = Math.floor(diff / month);
            return `${months} month${months > 1 ? 's' : ''} ago`;
        } else {
            const years = Math.floor(diff / year);
            return `${years} year${years > 1 ? 's' : ''} ago`;
        }
    },

    renderBadges: function(is_op, is_mod) {
        if (!radleCommentsSettings.displayBadges) {
            return '';
        }

        let badges = '';
        if (is_op) {
            badges += `<span class="author-badge op">${radleCommentsSettings.i18n.op_badge}</span>`;
        }
        if (!is_op && is_mod) {
            badges += `<span class="author-badge mod">${radleCommentsSettings.i18n.mod_badge}</span>`;
        }
        return badges;
    },
    toggleCommentVisibility: function(event) {
        const commentId = event.currentTarget.dataset.commentId;
        this.debug.log(`Toggling visibility for comment ID: ${commentId}`);
        event.preventDefault();
        const $button = jQuery(event.currentTarget);

        jQuery.ajax({
            url: `${radleCommentsSettings.root}radle/v1/hide-comment`,
            method: 'POST',
            beforeSend: (xhr) => {
                xhr.setRequestHeader('X-WP-Nonce', radleCommentsSettings.nonce);
            },
            data: {
                post_id: radleCommentsSettings.post_id,
                comment_id: commentId
            }
        }).done((response) => {
            if (response.success) {
                const $commentWrapper = $button.closest('.comment-wrapper');
                if (response.action === 'hidden') {
                    $button.html(`<span class="dashicons dashicons-visibility"></span> ${radleCommentsSettings.i18n.show_in_blog_post}`)
                        .removeClass('radle-hide-comment')
                        .addClass('radle-show-comment');
                    $commentWrapper.addClass('radle-hidden-comment');
                } else {
                    $button.html(`<span class="dashicons dashicons-show"></span> ${radleCommentsSettings.i18n.hide_from_blog_post}`)
                        .removeClass('radle-show-comment')
                        .addClass('radle-hide-comment');
                    $commentWrapper.removeClass('radle-hidden-comment');
                }
            }
        });
    },
    addEventListeners: function() {

    },

    toggleCollapse: function(event) {
        const commentWrapper = event.target.closest('.comment-wrapper');
        commentWrapper.classList.toggle('collapsed');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    RadleComments.init();
});

})(jQuery);