(function($) {

window.RadleComments = {
    commentsContainer: null,
    subreddit: '',
    redditPostId: '',
    originalPoster: '',
    currentSort: 'newest',
    currentSearch: '',
    debounceTimer: null,
    debug: (typeof RadleDebugger !== 'undefined') ? new RadleDebugger('comments.js', false) : { log: function() {}, error: function() {}, warn: function() {} },  

    init: function() {
        this.debug.log('RadleComments initializing...');
        this.debug.log('isPostPage: ' + (radleCommentsSettings.isPostPage ? 'true' : 'false'));
        this.debug.log('isPostEditPage: ' + (radleCommentsSettings.isPostEditPage ? 'true' : 'false'));

        if (!radleCommentsSettings.isPostPage && !radleCommentsSettings.isPostEditPage) {
            this.debug.log('Skipping comments initialization - Not a post page or post edit page');
            return;
        }

        // Always bind metabox events (comment override dropdown) even if comments container doesn't exist
        this.debug.log('About to call bindMetaboxEvents()');
        this.bindMetaboxEvents();
        this.debug.log('bindMetaboxEvents() completed');

        this.commentsContainer = document.getElementById('radle-comments-container');

        if (!this.commentsContainer) {
            this.debug.log('Comments container not found - skipping comments display');
            return;
        }

        this.debug.log('Comments container found, binding comment events...');
        this.bindCommentEvents();
        this.debug.log('Events bound successfully');
        this.showSkeletonLoader();  // Show skeleton on initialization
        this.loadComments();
    },

    bindMetaboxEvents: function() {
        this.debug.log('Binding metabox events (comment override dropdown)...');

        // Custom dropdown for comment system override (in post editor sidebar)
        const commentOverrideTrigger = document.getElementById('radle_comment_override_trigger');
        if (commentOverrideTrigger) {
            this.debug.log('Comment override dropdown trigger found, adding click listener');
            commentOverrideTrigger.addEventListener('click', this.toggleCommentOverrideDropdown.bind(this));
        } else {
            this.debug.log('Comment override dropdown trigger NOT found');
        }

        // Comment override option clicks
        const commentOverrideOptions = document.querySelectorAll('.radle-comment-override-dropdown .radle-dropdown-option');
        this.debug.log('Found ' + commentOverrideOptions.length + ' comment override options');
        commentOverrideOptions.forEach(option => {
            option.addEventListener('click', this.handleCommentOverrideOptionClick.bind(this));
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.radle-comment-override-dropdown')) {
                this.closeCommentOverrideDropdown();
            }
        });

        this.debug.log('Metabox events binding completed');
    },

    bindCommentEvents: function() {
        this.debug.log('Binding comment display events...');

        // Custom dropdown for sorting
        const sortTrigger = document.getElementById('radle_comments_sort_trigger');
        if (sortTrigger) {
            this.debug.log('Sort dropdown trigger found, adding click listener');
            sortTrigger.addEventListener('click', this.toggleSortDropdown.bind(this));
        }

        // Sort option clicks
        const sortOptions = document.querySelectorAll('.radle-sort-dropdown .radle-dropdown-option');
        sortOptions.forEach(option => {
            option.addEventListener('click', this.handleSortOptionClick.bind(this));
        });

        // Close sort dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.radle-sort-dropdown')) {
                this.closeSortDropdown();
            }
        });

        // Hidden input change (for backwards compatibility)
        const sortInput = document.getElementById('radle-comments-sort');
        if (sortInput) {
            sortInput.addEventListener('change', this.handleSortChange.bind(this));
        }

        // Searching comments
        const searchInput = document.getElementById('radle-comments-search');
        if (searchInput) {
            this.debug.log('Search input found, adding input listener');
            searchInput.addEventListener('input', this.handleSearch.bind(this));
        }

        // Hiding and showing comments
        if ( typeof jQuery('.radle-hide-comment') != 'undefined' ) {
            jQuery(this.commentsContainer).on('click', '.radle-hide-comment, .radle-show-comment', this.toggleCommentVisibility.bind(this));
        }

        // Share button - copy comment link to clipboard
        jQuery(this.commentsContainer).on('click', '.comment-action.share', this.handleShareClick.bind(this));

        this.debug.log('Comment display events binding completed');
    },

    bindEvents: function() {
        // Legacy function - keep for backwards compatibility
        this.bindMetaboxEvents();
        this.bindCommentEvents();
    },

    toggleSortDropdown: function(event) {
        event.stopPropagation();
        const trigger = document.getElementById('radle_comments_sort_trigger');
        const content = document.getElementById('radle_comments_sort_content');

        trigger.classList.toggle('active');
        content.classList.toggle('show');
    },

    closeSortDropdown: function() {
        const trigger = document.getElementById('radle_comments_sort_trigger');
        const content = document.getElementById('radle_comments_sort_content');

        if (trigger && content) {
            trigger.classList.remove('active');
            content.classList.remove('show');
        }
    },

    handleSortOptionClick: function(event) {
        event.preventDefault();
        event.stopPropagation();

        const option = event.currentTarget;
        const value = option.getAttribute('data-value');
        const text = option.textContent;

        // Update hidden input
        const sortInput = document.getElementById('radle-comments-sort');
        if (sortInput) {
            sortInput.value = value;
            // Trigger change event
            const changeEvent = new Event('change');
            sortInput.dispatchEvent(changeEvent);
        }

        // Update trigger text
        const selectedSpan = document.querySelector('.radle-sort-dropdown .radle-dropdown-selected');
        if (selectedSpan) {
            selectedSpan.textContent = text;
        }

        // Update active state
        document.querySelectorAll('.radle-sort-dropdown .radle-dropdown-option').forEach(opt => {
            opt.classList.remove('active');
        });
        option.classList.add('active');

        // Close dropdown
        this.closeSortDropdown();
    },

    handleSortChange: function(event) {
        this.debug.log(`Sorting comments by: ${event.target.value}`);
        this.currentSort = event.target.value;
        this.showSkeletonLoader();  // Show skeleton on sort change
        this.loadComments();
    },

    toggleCommentOverrideDropdown: function(event) {
        event.stopPropagation();
        const trigger = document.getElementById('radle_comment_override_trigger');
        const content = document.getElementById('radle_comment_override_content');

        trigger.classList.toggle('active');
        content.classList.toggle('show');
    },

    closeCommentOverrideDropdown: function() {
        const trigger = document.getElementById('radle_comment_override_trigger');
        const content = document.getElementById('radle_comment_override_content');

        if (trigger && content) {
            trigger.classList.remove('active');
            content.classList.remove('show');
        }
    },

    handleCommentOverrideOptionClick: function(event) {
        event.preventDefault();
        event.stopPropagation();

        const option = event.currentTarget;
        const value = option.getAttribute('data-value');
        const text = option.textContent.trim();

        this.debug.log('Comment override option clicked: ' + value);

        // Update hidden input
        const overrideInput = document.getElementById('radle_comment_system_override_input');
        if (overrideInput) {
            overrideInput.value = value;
            this.debug.log('Hidden input updated to: ' + value);
        } else {
            this.debug.error('Hidden input #radle_comment_system_override_input not found!');
        }

        // Update trigger text
        const selectedSpan = document.querySelector('.radle-comment-override-dropdown .radle-dropdown-selected');
        if (selectedSpan) {
            selectedSpan.textContent = text;
        }

        // Update active state
        document.querySelectorAll('.radle-comment-override-dropdown .radle-dropdown-option').forEach(opt => {
            opt.classList.remove('active');
        });
        option.classList.add('active');

        // Close dropdown
        this.closeCommentOverrideDropdown();
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
        this.initializeLightbox();

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

        // Array to store protected content (links, embeds)
        const protectedContent = [];

        // Store a placeholder for content that shouldn't be escaped
        const protect = (content) => {
            const placeholder = `{{PROTECTED_${protectedContent.length}}}`;
            protectedContent.push(content);
            return placeholder;
        };

        // Helper function to check if it's a Reddit GIF
        const isRedditGif = (url) => {
            return url.startsWith('giphy|') || url.startsWith('tenor|');
        };

        // Helper function to create GIF embed with WordPress lightbox
        const createGifEmbed = (url) => {
            // Reddit GIF format: giphy|ID|size or tenor|ID|size
            const parts = url.split('|');
            if (parts.length >= 2) {
                const provider = parts[0]; // giphy or tenor
                const gifId = parts[1];
                const uniqueId = Math.random().toString(36).substr(2, 9);
                let gifUrl = '';

                if (provider === 'giphy') {
                    gifUrl = `https://media.giphy.com/media/${gifId}/giphy.gif`;
                } else if (provider === 'tenor') {
                    gifUrl = `https://media.tenor.com/images/${gifId}/tenor.gif`;
                }

                if (gifUrl) {
                    // Use WordPress lightbox structure
                    return `<figure data-wp-context='{"imageId":"${uniqueId}"}' data-wp-interactive="core/image" class="wp-block-image reddit-gif-embed wp-lightbox-container">
                        <img decoding="async"
                             data-wp-init="callbacks.setButtonStyles"
                             data-wp-on-async--click="actions.showLightbox"
                             data-wp-on-async--load="callbacks.setButtonStyles"
                             src="${gifUrl}"
                             alt="GIF"
                             loading="lazy" />
                        <button class="lightbox-trigger" type="button" aria-haspopup="dialog" aria-label="Enlarge"
                                data-wp-init="callbacks.initTriggerButton"
                                data-wp-on-async--click="actions.showLightbox"
                                data-wp-style--right="state.imageButtonRight"
                                data-wp-style--top="state.imageButtonTop">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 12 12">
                                <path fill="#fff" d="M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z"></path>
                            </svg>
                        </button>
                    </figure>`;
                }
            }
            return '';
        };

        // Handle Reddit GIFs: ![gif](giphy|ID|size)
        text = text.replace(/!\[gif\]\(([^\)]+)\)/g, (match, url) => {
            if (isRedditGif(url)) {
                return protect(createGifEmbed(url));
            }
            return match;
        });

        // Handle Markdown images: ![alt](url)
        text = text.replace(/!\[([^\]]*)\]\(([^\)]+)\)/g, (match, altText, url) => {
            if (isRedditGif(url)) {
                return protect(createGifEmbed(url));
            }
            // Regular image with WordPress lightbox support
            const uniqueId = Math.random().toString(36).substr(2, 9);
            const imageHtml = `<figure data-wp-context='{"imageId":"${uniqueId}"}' data-wp-interactive="core/image" class="wp-block-image wp-lightbox-container">
                <img decoding="async"
                     data-wp-init="callbacks.setButtonStyles"
                     data-wp-on-async--click="actions.showLightbox"
                     data-wp-on-async--load="callbacks.setButtonStyles"
                     src="${encodeUrl(url)}"
                     alt="${altText}"
                     loading="lazy" />
                <button class="lightbox-trigger" type="button" aria-haspopup="dialog" aria-label="Enlarge"
                        data-wp-init="callbacks.initTriggerButton"
                        data-wp-on-async--click="actions.showLightbox"
                        data-wp-style--right="state.imageButtonRight"
                        data-wp-style--top="state.imageButtonTop">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 12 12">
                        <path fill="#fff" d="M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z"></path>
                    </svg>
                </button>
            </figure>`;
            return protect(imageHtml);
        });

        // Handle Markdown links and YouTube embeds
        text = text.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, (match, linkText, url) => {
            if (isYouTubeLink(url)) {
                return protect(createYouTubeEmbed(url));
            }
            return protect(`<a href="${encodeUrl(url)}" target="_blank" rel="nofollow">${linkText}</a>`);
        });

        // Handle plain URLs
        text = text.replace(/(?<!["'<])(https?:\/\/[^\s<>"']+)/g, (match, url) => {
            if (isYouTubeLink(url)) {
                return protect(createYouTubeEmbed(url));
            }
            return protect(`<a href="${encodeUrl(url)}" target="_blank" rel="nofollow">${url}</a>`);
        });

        // NOW escape any remaining HTML characters (protects against XSS)
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

        // Restore protected content (links and embeds)
        protectedContent.forEach((content, index) => {
            text = text.replace(`{{PROTECTED_${index}}}`, content);
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
    handleShareClick: function(event) {
        event.preventDefault();
        const $button = jQuery(event.currentTarget);
        const permalink = $button.data('permalink');

        // Build full Reddit URL
        const redditUrl = 'https://www.reddit.com' + permalink;

        this.debug.log('Share button clicked, copying URL: ' + redditUrl);

        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(redditUrl).then(() => {
                this.showShareTooltip($button);
            }).catch(err => {
                this.debug.error('Failed to copy to clipboard:', err);
                // Fallback for older browsers
                this.fallbackCopyToClipboard(redditUrl, $button);
            });
        } else {
            // Fallback for older browsers
            this.fallbackCopyToClipboard(redditUrl, $button);
        }
    },

    fallbackCopyToClipboard: function(text, $button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            this.showShareTooltip($button);
        } catch (err) {
            this.debug.error('Fallback: Failed to copy to clipboard', err);
        }

        document.body.removeChild(textArea);
    },

    showShareTooltip: function($button) {
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'radle-share-tooltip';
        tooltip.textContent = radleCommentsSettings.i18n.comment_link_copied;

        // Position tooltip above button
        const buttonRect = $button[0].getBoundingClientRect();
        tooltip.style.position = 'absolute';
        tooltip.style.top = (buttonRect.top + window.scrollY - 35) + 'px';
        tooltip.style.left = (buttonRect.left + window.scrollX + (buttonRect.width / 2)) + 'px';
        tooltip.style.transform = 'translateX(-50%)';

        document.body.appendChild(tooltip);

        // Fade in
        setTimeout(() => {
            tooltip.style.opacity = '1';
        }, 10);

        // Remove after 2 seconds
        setTimeout(() => {
            tooltip.style.opacity = '0';
            setTimeout(() => {
                document.body.removeChild(tooltip);
            }, 300);
        }, 2000);
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

    initializeLightbox: function() {
        const self = this;
        this.debug.log('Initializing custom lightbox for images');

        // Find all lightbox images and buttons
        const lightboxContainers = this.commentsContainer.querySelectorAll('.wp-lightbox-container');

        lightboxContainers.forEach(container => {
            const img = container.querySelector('img');
            const button = container.querySelector('.lightbox-trigger');

            if (img && button) {
                // Click handler for both image and button
                const openLightbox = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    self.openLightbox(img.src, img.alt);
                };

                img.addEventListener('click', openLightbox);
                button.addEventListener('click', openLightbox);
            }
        });
    },

    openLightbox: function(src, alt) {
        this.debug.log('Opening lightbox for:', src);

        // Create lightbox overlay
        const overlay = document.createElement('div');
        overlay.className = 'radle-lightbox-overlay';
        overlay.innerHTML = `
            <div class="radle-lightbox-content">
                <button class="radle-lightbox-close" aria-label="Close">&times;</button>
                <img src="${src}" alt="${alt}" />
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        // Fade in
        setTimeout(() => {
            overlay.style.opacity = '1';
        }, 10);

        // Close handlers
        const closeLightbox = () => {
            overlay.style.opacity = '0';
            setTimeout(() => {
                document.body.removeChild(overlay);
                document.body.style.overflow = '';
            }, 300);
        };

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.classList.contains('radle-lightbox-close')) {
                closeLightbox();
            }
        });

        // ESC key to close
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeLightbox();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    },

    addEventListeners: function() {

    }
};

document.addEventListener('DOMContentLoaded', function() {
    RadleComments.init();
});

})(jQuery);