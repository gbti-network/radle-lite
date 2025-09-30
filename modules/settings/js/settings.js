var $ = jQuery;
var redditConnectionSuccess = false;

var RadleSettings = {
    debug: new RadleDebugger('settings.js', true), 

    init: function() {
        this.debug.log('Initializing RadleSettings module');
        this.initiateSpash();
        this.bindEvents();
        this.initTabs();
        this.initHelpIcons();
        this.updateAuthorizationRows();
        this.addWelcomeModuleResetLink();
        this.replaceHeaderWithLogo();
        this.addSupportButtons();
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

        $container.append($logo);
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
        const subredditDropdownHtml = this.generateSubredditDropdown(response.moderated_subreddits, response.current_subreddit);
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
    generateSubredditDropdown: function(subreddits, currentSubreddit) {
        let dropdownHtml = `
            <h4>${radleSettings.i18n['selectSubreddit']}</h4>
            <select id="radle-subreddit-select">
                <option value="">${radleSettings.i18n['selectSubreddit']}</option>`;

        subreddits.forEach(subreddit => {
            dropdownHtml += `<option value="${subreddit}" ${subreddit === currentSubreddit ? 'selected' : ''}>${subreddit}</option>`;
        });

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
            RadleSettings.initProFields();
        });
    },

    initProFields: function() {
        var self = this;
        var proFields = [
            'radle_max_depth_level',
            'radle_max_siblings',
            'radle_cache_duration',
            'radle_enable_search',
            'radle_show_badges',
            'radle_show_comments_menu'
        ];

        // Remove any existing notices first
        $('.notice-success').remove();

        proFields.forEach(function(fieldName) {
            var $field = $('[name="' + fieldName + '"]');
            if ($field.length) {
                // Add pro field class if not already present
                $field.addClass('radle-pro-field');

                // Get the help text from the existing help icon
                var $helpIcon = $field.siblings('.radle-help-icon');
                var helpText = $helpIcon.siblings('.radle-help-description').text();
                
                // Remove the existing help icon
                $helpIcon.next('.radle-help-description').remove();
                $helpIcon.remove();

                // Add pro notice with help text functionality
                var proText = radleSettings.i18n.proVersionOnly || 'Pro Version Only';
                var learnMoreText = radleSettings.i18n.learnMore || 'Learn More';
                var $proNotice = $('<span class="radle-pro-notice">' + 
                    proText + ' (<a href="#" class="radle-help-toggle">' + learnMoreText + '</a>)</span>' +
                    '<p class="radle-help-description" style="display: none;">' + helpText + '</p>');
                
                $field.after($proNotice);

                // Handle help text toggle
                $proNotice.find('.radle-help-toggle').on('click', function(e) {
                    e.preventDefault();
                    $(this).closest('.radle-pro-notice').next('.radle-help-description').slideToggle();
                });
            }
        });

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
    addSupportButtons: function() {
        var $container = $('.radle-overview-section');
        if (!$container.length) return;

        // Create review CTA
        var $reviewCta = $('<div>', {
            class: 'radle-review-cta',
            html: `
                <span class="dashicons dashicons-star-filled"></span>
                <span class="dashicons dashicons-star-filled"></span>
                <span class="dashicons dashicons-star-filled"></span>
                <span class="dashicons dashicons-star-filled"></span>
                <span class="dashicons dashicons-star-filled"></span>
                <h3>${radleSettings.i18n['enjoyingRadle'] || 'Enjoying Radle Lite?'}</h3>
                <p>${radleSettings.i18n['reviewMessage'] || 'Help us grow by leaving a 5-star review! Your feedback helps us improve and reach more developers.'}</p>
                <a href="https://wordpress.org/support/plugin/radle-lite/reviews/#new-post" class="button radle-review-button" target="_blank">
                    <span class="dashicons dashicons-thumbs-up"></span>
                    ${radleSettings.i18n['writeReview'] || 'Write a Review'}
                </a>
            `
        });

        // Create support buttons container
        var $supportContainer = $('<div>', {
            class: 'radle-support-buttons'
        });

        // Create "Raise Issues" button
        var $issuesButton = $('<a>', {
            href: 'https://github.com/gbti-network/radle-lite/settings',
            class: 'radle-support-button radle-issues-button',
            target: '_blank',
            html: '<span class="dashicons dashicons-sos"></span>' + (radleSettings.i18n['raiseIssues'] || 'Raise Issues')
        });

        // Create "Request Customizations" button
        var $customizeButton = $('<a>', {
            href: 'https://app.codeable.io/presets/apply?token=LwGYjPe67pbiT1irdE89bCBXaJ9uHRs9yazmg8zF6gZwLHxi9C',
            class: 'radle-support-button radle-customize-button',
            target: '_blank',
            html: '<span class="dashicons dashicons-admin-tools"></span>' + (radleSettings.i18n['requestCustomizations'] || 'Request Customizations')
        });

        // First append the review CTA
        $container.append($reviewCta);

        // Then append support buttons
        $supportContainer.append($issuesButton).append($customizeButton);
        $container.append($supportContainer);
    },
};

jQuery(document).ready(function() {
    RadleSettings.init();
});
