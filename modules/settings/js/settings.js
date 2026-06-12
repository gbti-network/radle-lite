var $ = jQuery;
var redditConnectionSuccess = false;

var RadleSettings = {
    debug: (typeof RadleDebugger !== 'undefined') ? new RadleDebugger('settings.js', true) : { log: function() {}, error: function() {}, warn: function() {} }, 

    init: function() {
        this.debug.log('Initializing RadleSettings module');
        this.initiateSpash();
        this.bindEvents();
        this.initTabs();
        this.initHelpIcons();
        this.updateAuthorizationRows();
        this.addWelcomeModuleResetLink();
        this.replaceHeaderWithLogo();
        this.addPromoSlideshow();
        this.debug.log('RadleSettings initialization complete');
    },

    bindEvents: function() {
        this.debug.log('Binding settings events...');
        var self = this;

        $('#radle-reddit-reset-button').on('click', this.resetRedditAuthorization.bind(this));
        $('#radle-reddit-authorize-button').on('click', this.handleRedditAuth.bind(this));
        $('#radle-client-id, #radle-client-secret').on('input', this.updateAuthorizationRows.bind(this));
        $('#reset-welcome-process').on('click', this.resetWelcomeProcess.bind(this));
        $('#radle_comment_system').on('change', this.handleCommentSystemChange.bind(this));

        // Initialize shortcode notice visibility on page load
        this.handleCommentSystemChange();

        this.debug.log('Event handlers bound successfully');
    },

    handleCommentSystemChange: function() {
        var selectedValue = $('#radle_comment_system').val();
        var $shortcodeNotice = $('#radle-shortcode-notice');

        if (selectedValue === 'shortcode') {
            $shortcodeNotice.slideDown();
        } else {
            $shortcodeNotice.slideUp();
        }
    },

    replaceHeaderWithLogo: function() {
        this.debug.log('Replacing header with logo');
        var $wrap = $('.wrap');
        var $header = $('.wrap h1:first');
        $header.remove();

        var $container = $('<div class="radle-header-container"></div>');
        $container.css('background-image', 'url(' + radleSettings.pluginUrl + 'assets/images/radle-pattern-3.webp' + ')');

        var $logo = $('<img>', {
            src: radleSettings.pluginUrl + 'assets/images/radle-logo-white.webp',
            alt: 'Radle Logo',
            class: 'radle-logo'
        });

        // Constrain the logo to the same centered column as the settings content
        // so it starts where the container starts.
        var $inner = $('<div class="radle-header-inner"></div>');
        $inner.append($logo);
        $container.append($inner);
        $wrap.prepend($container);
    },

    initiateSpash: function() {
        $('#wpcontent').hide();

        var splash = $('<div id="radle-splash">' +
            '<img src="' + radleSettings.pluginUrl + 'assets/images/radle-logo-pattern.webp" alt="Radle Logo">' +
            '<p>Loading...</p>' +
            '</div>');
        $('#wpwrap').prepend(splash);
    },

    checkApiConnections: function() {
        this.debug.log('Starting API connection checks');
        var self = this;
        var splashDeferred = $.Deferred();
        var apiConnectionPromise = $.Deferred();

        // Only check Reddit API connection
        var redditCheck = self.checkApiConnection('reddit', 'radle/v1/reddit/check-auth');

        // When Reddit check completes, resolve both promises
        redditCheck.always(function() {
            if (splashDeferred.state() === 'pending') {
                splashDeferred.resolve();
            }
            apiConnectionPromise.resolve();
        });

        // Set up a maximum timeout of 10 seconds
        setTimeout(function() {
            if (splashDeferred.state() === 'pending') {
                self.debug.warn('API checks timed out after 10 seconds');
                splashDeferred.resolve();
                apiConnectionPromise.resolve();
            }
        }, 10000);

        // When splashDeferred is resolved, remove splash screen
        splashDeferred.done(function() {
            self.debug.log('Removing splash screen');
            $('#radle-splash').fadeOut(300, function() {
                $(this).remove();
                $('#wpcontent').fadeIn(300);
                
                // Initialize monitoring chart if we're on the monitoring tab
                if (window.location.hash === '#monitoring' && typeof RadleMonitoring !== 'undefined') {
                    setTimeout(function() {
                        RadleMonitoring.initializeChart();
                    }, 100);
                }
            });
        });

        return apiConnectionPromise.promise();
    },

    checkApiConnection: function(api, endpoint) {
        this.debug.log('Checking ' + api + ' API connection');
        var self = this;
        
        return $.ajax({
            url: wpApiSettings.root + endpoint,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            }
        })
        .done(function(response) {
            self.handleApiConnectionSuccess(api, response);
        })
        .fail(function() {
            self.handleApiConnectionFailure(api);
        });
    },

    resetRedditAuthorization: function() {
        this.resetAuthorization('reddit', 'radle/v1/reddit/reset-token');
    },

    resetAuthorization: function(api, endpoint) {
        var self = this;
        self.debug.log(`Resetting ${api} authorization`);
        
        // Disable the reset button and show loading state
        var $resetButton = $('#radle-' + api + '-reset-button');
        var originalText = $resetButton.text();
        $resetButton.prop('disabled', true).text(radleSettings.i18n.resetting || 'Resetting...');
        
        $.ajax({
            url: wpApiSettings.root + endpoint,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
        }).done(function(response) {
            if (response && (response.success || response.data)) {
                self.debug.log(`${api} authorization reset successful, reloading...`);
                window.location.reload();
            } else {
                self.debug.error(`${api} authorization reset failed`);
                RadleSettings.showApiConnectionError(api, radleSettings.i18n[api + 'FailedApiConnection']);
                $resetButton.prop('disabled', false).text(originalText);
            }
        }).fail(function(response) {
            self.debug.error(`${api} authorization reset failed with error:`, response);
            alert(response.responseJSON.message);
            $resetButton.prop('disabled', false).text(originalText);
        });
    },
    handleRedditAuth: function(e) {
        e.preventDefault();
        this.initiateOAuth('reddit');
    },

    handleApiConnectionSuccess: function(api, response) {
        this.debug.log(api + ' connection successful');
        if (api === 'reddit') {
            this.handleRedditConnectionSuccess(response);
        }
    },

    handleRedditConnectionSuccess: function(response) {
        this.hideRedditAuthorizationFields();
        const profileCardHtml = this.generateRedditProfileCard(response);
        const subredditDropdownHtml = this.generateSubredditDropdown(response.moderated_subreddits, response.current_subreddit, response.user_info);
        const latestEntriesHtml = this.generateLatestEntriesSection(response.current_subreddit);

        const contentHtml = `
        <div class="radle-profile-container">
            <div class="radle-profile-left">
                ${profileCardHtml}
                <div class="radle-reset-container">
                    <span id="radle-reddit-reset-button" class="radle-reset-button reddit">
                        <img src="${radleSettings.pluginUrl}assets/images/reddit-icon.svg" alt="Reddit Icon" />
                        ${radleSettings.i18n['resetAuthorization']}
                    </span>
                </div>
            </div>
            <div class="radle-profile-right">
                <div class="radle-special-section">
                    ${subredditDropdownHtml}
                </div>
                ${latestEntriesHtml}
            </div>
        </div>`;

        $('#radle-settings-reddit').prepend(contentHtml);

        this.bindRedditEvents();
        this.loadLatestEntries();

        redditConnectionSuccess = true;
    },

    hideRedditAuthorizationFields: function() {
        $('input[name="radle_client_id"]').closest('tr').hide();
        $('input[name="radle_client_secret"]').closest('tr').hide();
        $('#radle-authorize-button').closest('tr').hide();
        $('button[name="radle_reset_button"]').closest('tr').show();
        $('#reddit-api-documentation').closest('tr').hide();
        $('.reddit-authorize-prompt').closest('tr').hide();

    },

    generateRedditProfileCard: function(response) {
        return `
        <div class="radle-profile-card">
            <img src="${response.user_info.avatar_url}" alt="${response.user_info.user_name}" class="radle-avatar">
            <h3>${response.user_info.user_name}</h3>
            <p><a href="https://www.reddit.com/user/${response.user_info.user_name}" target="_blank">/u/${response.user_info.user_name}</a></p>
        </div>`;
    },

    generateLatestEntriesSection: function(currentSubreddit) {
        return `
        <div class="radle-special-section">
            <h4>
                ${currentSubreddit ?
                `<a href="https://www.reddit.com/r/${currentSubreddit}" target="_blank" rel="noopener noreferrer">/r/${currentSubreddit}</a>: `
                : ''
            }${radleSettings.i18n['recentPosts']}
            </h4>
            <div id="radle-posts-content">
                ${currentSubreddit ? radleSettings.i18n['loadingEntries'] : radleSettings.i18n['mustConnectSubreddit']}
            </div>
        </div>`;
    },
    generateSubredditDropdown: function(subreddits, currentSubreddit, userInfo) {
        let dropdownHtml = `
            <h4>${radleSettings.i18n['selectSubreddit']}</h4>
            <select id="radle-subreddit-select">
                <option value="">${radleSettings.i18n['selectSubreddit']}</option>`;

        // Add user profile option first (always available)
        if (userInfo && userInfo.user_name) {
            const profileValue = 'u_' + userInfo.user_name;
            const isSelected = profileValue === currentSubreddit || (!currentSubreddit && (!subreddits || subreddits.length === 0));
            dropdownHtml += `<option value="${profileValue}" ${isSelected ? 'selected' : ''}>📝 My Profile (u/${userInfo.user_name})</option>`;
        }

        // Add moderated subreddits if any exist
        if (subreddits && subreddits.length > 0) {
            // Add separator
            dropdownHtml += `<option disabled>─────────────────</option>`;

            // Add subreddits
            subreddits.forEach(subreddit => {
                dropdownHtml += `<option value="${subreddit}" ${subreddit === currentSubreddit ? 'selected' : ''}>👥 r/${subreddit}</option>`;
            });
        }

        dropdownHtml += `</select>`;

        return dropdownHtml;
    },

    bindRedditEvents: function() {
        $('#radle-reddit-reset-button').on('click', this.resetRedditAuthorization.bind(this));
        $('#radle-subreddit-select').on('change', this.handleSubredditSelection.bind(this));
    },

    handleSubredditSelection: function(e) {
        var self = this;
        var subreddit = $(e.target).val();
        
        if (!subreddit) {
            return;
        }
        
        // Show loading state
        var $select = $(e.target);
        var originalText = $select.find('option:selected').text();
        $select.prop('disabled', true);
        $select.find('option:selected').text(radleSettings.i18n.saving || 'Saving...');
        
        $.ajax({
            url: wpApiSettings.root + 'radle/v1/radle/set-subreddit',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            data: {
                subreddit: subreddit
            }
        })
        .done(function() {
            // Update the UI without page reload
            self.loadLatestEntries();
            
            // Show success message
            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + 
                (radleSettings.i18n.subredditUpdated || 'Subreddit updated successfully.') + '</p></div>');
            $('.wrap > h2').first().after($notice);
            
            // Initialize the dismissible functionality
            if (typeof wp !== 'undefined' && wp.notices && wp.notices.removeDismissible) {
                wp.notices.removeDismissible($notice);
            }
            
            // Auto dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        })
        .fail(function(jqXHR) {
            // Show error message
            var errorMessage = jqXHR.responseJSON && jqXHR.responseJSON.message 
                ? jqXHR.responseJSON.message 
                : (radleSettings.i18n.errorUpdatingSubreddit || 'Error updating subreddit.');
            
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
            $('.wrap > h2').first().after($notice);
            
            if (typeof wp !== 'undefined' && wp.notices && wp.notices.removeDismissible) {
                wp.notices.removeDismissible($notice);
            }
        })
        .always(function() {
            // Restore select state
            $select.prop('disabled', false);
            $select.find('option:selected').text(originalText);
        });
    },
    loadLatestEntries: function() {
        const $entriesContent = $('#radle-posts-content');
        const subreddit = $('#radle-subreddit-select').val();

        if (!subreddit) {
            $entriesContent.html('<p>' + radleSettings.i18n.mustConnectSubreddit + '</p>');
            return;
        }

        $entriesContent.closest('.radle-special-section').find('h4').html(`<a href="https://www.reddit.com/r/${subreddit}" target="_blank" rel="noopener noreferrer">/r/${subreddit}</a>: ${radleSettings.i18n['recentPosts']}`);
        $entriesContent.html('<p>' + radleSettings.i18n.loadingEntries + '</p>');

        $.ajax({
            url: wpApiSettings.root + 'radle/v1/reddit/get-entries',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            }
        }).done(function(response) {
            if (response.length > 0) {
                var entriesHtml = '<ul>';
                response.forEach(function(entry) {
                    entriesHtml += '<li><a href="' + entry.url + '" target="_blank">' + entry.title + '</a> by ' + entry.author + '</li>';
                });
                entriesHtml += '</ul>';
                $entriesContent.html(entriesHtml);
            } else {
                $entriesContent.html('<p>' + radleSettings.i18n.noEntriesFound + '</p>');
            }
        }).fail(function() {
            $entriesContent.html('<p>' + radleSettings.i18n.failedToLoadEntries + '</p>');
        });
    },
    handleApiConnectionFailure: function(api) {
        this.debug.error(api + ' connection failed');
        this.showApiConnectionError(api);
    },

    showApiConnectionError: function(api, message) {
        message = message || radleSettings.i18n.connectionError;
        var $container = $('#radle-' + api + '-connection-error');
        
        if ($container.length) {
            $container.html('<div class="notice notice-error"><p>' + message + '</p></div>');
        }
    },

    initTabs: function() {
        var self = this;
        var $tabs = $('.nav-tab-wrapper .nav-tab');
        
        // Get the current tab from URL parameters or local storage
        var params = new URLSearchParams(window.location.search);
        var currentTab = params.get('tab');
        
        // If no tab in URL, use last tab from local storage or default to 'overview'
        if (!currentTab) {
            currentTab = localStorage.getItem('radleLastTab') || 'overview';
            // Update URL to match the loaded tab
            var newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', currentTab);
            window.history.replaceState({}, '', newUrl);
        }
        
        self.debug.log('Initializing tabs with current tab: ' + currentTab);
        self.debug.log('Found ' + $tabs.length + ' tab elements');
        
        var apiConnectionPromise = RadleSettings.checkApiConnections();

        function updateWpReferer(tabId) {
            var $referer = $('input[name="_wp_http_referer"]');
            if ($referer.length) {
                var refererUrl = new URL($referer.val(), window.location.origin);
                refererUrl.searchParams.set('tab', tabId);
                $referer.val(refererUrl.pathname + refererUrl.search);
                self.debug.log('Updated _wp_http_referer to: ' + $referer.val());
            }
        }

        function showTab(tabId) {
            self.debug.log('=== Tab Switch Debug ===');
            self.debug.log('Attempting to switch to tab: ' + tabId);
            
            // Always update local storage with current tab
            localStorage.setItem('radleLastTab', tabId);

            // Find the target section first
            var $targetSection = $('#radle-settings-' + tabId);
            if (!$targetSection.length) {
                self.debug.warn('Target section not found: #radle-settings-' + tabId);
                return;
            }

            // First, remove active class and hide all sections immediately
            $('.radle-settings-section').removeClass('active').hide();
            
            // Then show the new section and make it active
            $targetSection.fadeIn(200, function() {
                $(this).addClass('active');
            });

            // Update tab active states
            $tabs.removeClass('nav-tab-active');
            $('.nav-tab[data-tab="' + tabId + '"]').addClass('nav-tab-active');

            // Update URL without reloading page
            var newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', tabId);
            window.history.pushState({}, '', newUrl);

            // Update WordPress referer field
            updateWpReferer(tabId);

            // Initialize monitoring chart if needed
            if (tabId === 'monitoring' && typeof RadleMonitoring !== 'undefined') {
                setTimeout(function() {
                    RadleMonitoring.initializeChart();
                }, 200); // Increased timeout to ensure chart initializes after animation
            }

            // Update submit button visibility
            self.updateSubmitButtonVisibility(tabId);
            self.debug.log('=== End Tab Switch Debug ===');
        }

        // Handle tab clicks
        $tabs.on('click', function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');
            self.debug.log('Tab clicked: ' + tabId);
            showTab(tabId);
            return false;
        });

        // Show initial tab and update referer
        self.debug.log('Setting initial tab: ' + currentTab);
        showTab(currentTab);
        updateWpReferer(currentTab);

        // Handle browser back/forward buttons
        $(window).on('popstate', function() {
            var params = new URLSearchParams(window.location.search);
            var tabId = params.get('tab') || localStorage.getItem('radleLastTab') || 'overview';
            self.debug.log('History navigation - switching to tab: ' + tabId);
            showTab(tabId);
        });

        apiConnectionPromise.done(function() {
            RadleSettings.updateSubmitButtonVisibility(currentTab);
            RadleSettings.initSettingsForm();
        });
    },

    initSettingsForm: function() {
        // Handle form submission
        $('form').on('submit', function(e) {
            // Show only one settings saved notice
            var $notices = $('.notice-success');
            if ($notices.length > 1) {
                $notices.slice(1).remove();
            }
            
            // Don't prevent form submission - let PHP handle the value enforcement
        });
    },

    updateSubmitButtonVisibility: function(tabId) {
        if (tabId === 'github') {
            jQuery('.submit').hide(); 
        } else if (tabId === 'reddit') {
            if (!redditConnectionSuccess) {
                jQuery('.submit').show();
            } else {
                jQuery('.submit').hide();
            }
        } else {
            jQuery('.submit').show();
        }
    },

    initHelpIcons: function() {
        jQuery('.radle-help-icon').on('click', function() {
            jQuery(this).next('.radle-help-description').slideToggle();
        });
    },

    initiateOAuth: function(api) {
        var width = 800;
        var height = 600;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;

        var authWindow = window.open(
            radleSettings[api + 'AuthUrl'],
            'Radle' + api.charAt(0).toUpperCase() + api.slice(1) + 'Auth',
            'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left
        );

        var checkWindow = setInterval(function() {
            if (authWindow.closed) {
                clearInterval(checkWindow);
                location.reload();
            }
        }, 200);
    },

    updateAuthorizationRows: function() {
        var clientId = $('#radle-client-id').val();
        var clientSecret = $('#radle-client-secret').val();
        
        if (clientId && clientSecret) {
            $('#radle-reddit-auth-button').show();
        } else {
            $('#radle-reddit-auth-button').hide();
        }
    },

    resetWelcomeProcess: function(e) {
        e.preventDefault();
        
        if(confirm(radleSettings.i18n.resetWelcomeConfirm)) {
            var self = this;
            $.ajax({
                url: wpApiSettings.root + 'radle/v1/welcome/reset',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            })
            .done(function() {
                location.reload();
            });
        }
    },

    addWelcomeModuleResetLink: function() {
        var resetLink = "<a href='#' id='reset-welcome-link'>" + radleSettings.i18n.clickHere + "</a>";
        var footerText = radleSettings.i18n.resetWelcomeProcess.replace('%s', resetLink);
        $('#footer-thankyou').html(footerText);

        $('#reset-welcome-link').on('click', function(e) {
            e.preventDefault();
            RadleSettings.resetWelcomeProcess(e);
        });
    },
    addPromoSlideshow: function() {
        var $container = $('.radle-overview-section');
        if (!$container.length) return;

        var GBTI_BASE = 'https://gbti.network/?ref=atwellpub&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=';
        var CODEABLE = 'https://codeable.io/?ref=99TG1&utm_source=radle-lite&utm_medium=wordpress-plugin&utm_campaign=slideshow';

        var slides = [
            {
                cls: 'gbti',
                accent: '#45c08d',
                icon: 'groups',
                name: 'gbti network',
                tag: 'Join',
                heading: 'Are you a builder looking for a community?',
                body: 'GBTI Network is a developer co-op — a private Discord, weekly build sessions, and a place to publish your work under your own name. 90-day free trial, no card.',
                ctaText: 'Start your free trial',
                ctaHref: GBTI_BASE + 'slideshow-join'
            },
            {
                cls: 'codeable',
                accent: '#ff6b5b',
                ctaBg: '#1a1b2e',
                icon: 'admin-tools',
                name: 'codeable',
                tag: 'Hire',
                heading: 'Looking for a reliable WordPress developer?',
                body: 'Codeable pairs you with a vetted expert and sends a free, no-obligation estimate.',
                ctaText: 'Get a free estimate',
                ctaHref: CODEABLE
            }
        ];

        var $show = $('<div>', { class: 'radle-promo-slideshow' });
        var $track = $('<div>', { class: 'radle-promo-track' });

        slides.forEach(function(s, i) {
            $track.append($('<div>', {
                class: 'radle-promo-slide ' + s.cls + (i === 0 ? ' active' : ''),
                html: `
                    <div class="radle-promo-main">
                        <div class="radle-promo-brand">
                            <span class="radle-promo-icon"><span class="dashicons dashicons-${s.icon}"></span></span>
                            <span class="radle-promo-name">${s.name}</span>
                            <span class="radle-promo-sep"></span>
                            <span class="radle-promo-tag">${s.tag}</span>
                        </div>
                        <h3>${s.heading}</h3>
                        <p>${s.body}</p>
                    </div>
                    <div class="radle-promo-action">
                        <a href="${s.ctaHref}" target="_blank" rel="noopener" class="radle-promo-cta">${s.ctaText} <span class="dashicons dashicons-arrow-right-alt"></span></a>
                    </div>
                `
            }));
        });

        // Navigation (bottom-right): prev arrow · dots · next arrow
        var $dots = $('<div>', { class: 'radle-promo-dots' });
        slides.forEach(function(s, i) {
            $dots.append($('<button>', {
                class: 'radle-promo-dot' + (i === 0 ? ' active' : ''),
                type: 'button', 'data-index': i, 'aria-label': 'Slide ' + (i + 1)
            }));
        });
        var $prev = $('<button>', { class: 'radle-promo-arrow prev', type: 'button', 'aria-label': 'Previous', html: '<span class="dashicons dashicons-arrow-left-alt2"></span>' });
        var $next = $('<button>', { class: 'radle-promo-arrow next', type: 'button', 'aria-label': 'Next', html: '<span class="dashicons dashicons-arrow-right-alt2"></span>' });
        var $nav = $('<div>', { class: 'radle-promo-nav' }).append($prev).append($dots).append($next);

        $show.append($track).append($nav);
        $container.append($show);

        // Auto-rotation
        var current = 0;
        var total = slides.length;
        var timer = null;
        var INTERVAL = 4000;

        function go(index) {
            current = (index + total) % total;
            $show[0].style.setProperty('--accent', slides[current].accent);
            $show[0].style.setProperty('--cta-bg', slides[current].ctaBg || slides[current].accent);
            $track.find('.radle-promo-slide').removeClass('active').eq(current).addClass('active');
            $dots.find('.radle-promo-dot').removeClass('active').eq(current).addClass('active');
        }
        function advance() { go(current + 1); }
        function start() { stop(); timer = setInterval(advance, INTERVAL); }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }

        $next.on('click', function() { advance(); start(); });
        $prev.on('click', function() { go(current - 1); start(); });
        $dots.on('click', '.radle-promo-dot', function() { go(parseInt($(this).attr('data-index'), 10)); start(); });
        $show.on('mouseenter', stop).on('mouseleave', start);

        go(0);
        start();

        // Persistent review CTA below the slideshow
        $container.append($('<div>', {
            class: 'radle-review-bar',
            html: `
                <span class="dashicons dashicons-star-filled"></span>
                <span>${radleSettings.i18n['enjoyingRadle'] || 'Enjoying Radle?'}</span>
                <a href="https://wordpress.org/support/plugin/radle-lite/reviews/#new-post" target="_blank" rel="noopener" class="radle-review-bar-link">${radleSettings.i18n['writeReview'] || 'Leave a 5-star review'}</a>
            `
        }));
    },
};

jQuery(document).ready(function() {
    RadleSettings.init();
});
