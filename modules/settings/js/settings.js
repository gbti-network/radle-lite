var $ = jQuery;
var redditConnectionSuccess = false;

var RadleSettings = {
    debug: new RadleDebugger('settings.js', false), 

    init: function() {
        this.debug.log('Initializing RadleSettings module');
        this.initiateSpash();
        this.bindEvents();
        this.initTabs();
        this.initHelpIcons();
        this.updateAuthorizationRows();
        this.addWelcomeModuleResetLink();
        this.replaceHeaderWithLogo();
        this.addGitHubIcon();
        this.addSupportButtons();
        this.debug.log('RadleSettings initialization complete');
    },
    bindEvents: function() {
        this.debug.log('Binding settings events...');
        var self = this;
        
        $('#radle-reddit-reset-button').on('click', this.resetRedditAuthorization.bind(this));
        $('#radle-github-reset-button').on('click', this.resetGitHubAuthorization.bind(this));
        $('#radle-github-authorize-button').on('click', this.handleGitHubAuth.bind(this));
        $('#radle-reddit-authorize-button').on('click', this.handleRedditAuth.bind(this));
        $('#radle-client-id, #radle-client-secret').on('input', this.updateAuthorizationRows.bind(this));
        $('#reset-welcome-process').on('click', this.resetWelcomeProcess.bind(this));
        
        this.debug.log('Event handlers bound successfully');
    },
    replaceHeaderWithLogo: function() {
        this.debug.log('Replacing header with logo');
        var $wrap = $('.wrap');
        var $header = $('.wrap h1:first');
        $header.remove();

        var $container = $('<div class="radle-header-container"></div>');
        $container.css('background-image', 'url(' + radleSettings.pluginUrl + 'assets/images/radle-pattern-3.webp' + ')');

        // Create the logo image element
        var $logo = $('<img>', {
            src: radleSettings.pluginUrl + 'assets/images/radle-logo-white.webp',
            alt: 'Radle Logo',
            class: 'radle-logo'
        });


        // Append the logo and the cloned header to the container
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

        // Create a promise to handle the result of the checks
        var apiConnectionPromise = $.Deferred();

        // Start authentication checks
        var redditCheck = self.checkApiConnection('reddit', 'radle/v1/reddit/check-auth');
        var githubCheck = self.checkApiConnection('github', 'radle/v1/github/check-auth');

        // Combine auth checks into one Deferred
        var authChecks = $.when(redditCheck, githubCheck);

        // When auth checks complete, resolve splashDeferred if not already resolved
        authChecks.always(function() {
            if (splashDeferred.state() === 'pending') {
                splashDeferred.resolve();
            }

            apiConnectionPromise.resolve();
        });

        // Set up a maximum timeout
        setTimeout(function() {
            if (splashDeferred.state() === 'pending') {
                self.debug.warn('API checks timed out after 10 seconds');
                splashDeferred.resolve();
            }
        }, 10000); // 10 seconds

        // When splashDeferred is resolved, hide the splash screen
        splashDeferred.done(function() {
            self.debug.log('Removing splash screen');
            $('#radle-splash').fadeOut(300, function() {
                $(this).remove();
                $('#wpcontent').show();
                setTimeout(() => {
                    if (typeof RadleMonitoring !== 'undefined' && RadleMonitoring.initializeChart) {
                        self.debug.log('Initializing monitoring chart');
                        RadleMonitoring.initializeChart();
                    }
                }, 500);
            });
        });

        return apiConnectionPromise.promise();
    },
    checkApiConnection: function(api, endpoint) {
        var self = this;
        self.debug.log(`Checking ${api} API connection status`);
        
        return $.ajax({
            url: wpApiSettings.root + endpoint,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
        }).done(function(response) {
            if (response.is_authorized) {
                self.debug.log(`${api} API connection verified successfully`);
                RadleSettings.handleApiConnectionSuccess(api, response);
            } else {
                self.debug.warn(`${api} API authorization check failed`);
                RadleSettings.handleApiConnectionFailure(api);
            }
        }).fail(function(response) {
            self.debug.error(`${api} API connection check failed:`, response);
            RadleSettings.handleApiConnectionFailure(api);
        });
    },

    resetRedditAuthorization: function() {
        this.resetAuthorization('reddit', 'radle/v1/reddit/reset-token');
    },

    resetGitHubAuthorization: function() {
        this.resetAuthorization('github', 'radle/v1/github/delete-token');
    },
    resetAuthorization: function(api, endpoint) {
        var self = this;
        self.debug.log(`Resetting ${api} authorization`);
        $.ajax({
            url: wpApiSettings.root + endpoint,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
        }).done(function(response) {
            if (response.success) {
                self.debug.log(`${api} authorization reset successful`);
                location.reload();
            } else {
                self.debug.error(`${api} authorization reset failed`);
                RadleSettings.showApiConnectionError(api, radleSettings.i18n[api + 'FailedApiConnection']);
            }
        }).fail(function(response) {
            self.debug.error(`${api} authorization reset failed with error:`, response);
            alert(response.responseJSON.message);
        });
    },
    handleGitHubAuth: function(e) {
        e.preventDefault();
        this.initiateOAuth('github');
    },

    handleRedditAuth: function(e) {
        e.preventDefault();
        this.initiateOAuth('reddit');
    },
    handleApiConnectionSuccess: function(api, response) {
        if (api === 'reddit') {
            this.handleRedditConnectionSuccess(response);
        } else if (api === 'github') {
            this.handleGitHubConnectionSuccess(response);
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
    handleGitHubConnectionSuccess: function(response) {
        $('#radle-github-authorize-button').closest('tr').hide();

        const profileCardHtml = this.generateGitHubProfileCard(response.user_info);
        const contentHtml = `
        <div class="radle-profile-container">
            <div class="radle-profile-left">
                ${profileCardHtml}
                <div class="radle-reset-container">
                    <span id="radle-github-reset-button" class="radle-reset-button github">
                        <img src="${radleSettings.pluginUrl}assets/images/github-icon.svg" alt="GitHub Icon" />
                        ${radleSettings.i18n['resetAuthorization']}
                    </span>
                </div>
            </div>
            <div class="radle-profile-right">
                <div class="radle-special-section">
                    <h4>${radleSettings.i18n['latestReleases']}</h4>
                    <div id="radle-releases-content"></div>
                </div>
            </div>
        </div>`;

        $('#radle-settings-github').append(contentHtml);

        this.bindGitHubEvents();
        this.fetchGitHubReleases();
        this.checkSponsorStatus();
    },
    checkSponsorStatus: function() {
        // Remove any existing sponsor notices first
        $('.radle-sponsor-notice').remove();

        $.ajax({
            url: radleSettings.gbtiServerUri + '/oauth/check-sponsor/',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + radleSettings.githubToken);
            },
            data: JSON.stringify({
                product_name: radleSettings.repoName
            }),
            contentType: 'application/json',
            dataType: 'json'
        }).done((response) => {
            
            // If the user is a sponsor, do something or nothing
            if (response.is_sponsor) {
                // Create container for support buttons
                var $supportContainer = $('<div>', {
                    class: 'radle-support-buttons'
                });

                // Create "Raise Issues" button
                var $issuesButton = $('<a>', {
                    href: 'https://github.com/gbti-network/radle-wordpress-plugin/issues',
                    class: 'radle-support-button radle-issues-button',
                    target: '_blank',
                    html: '<span class="dashicons dashicons-sos"></span>' + radleSettings.i18n['raiseIssues']
                });

                // Create "Request Customizations" button
                var $customizeButton = $('<a>', {
                    href: 'https://app.codeable.io/presets/apply?token=WMG5pJZeMnB2hY4enPmXGwHge9c321mQ1es16PHUcVX63wX5xt',
                    class: 'radle-support-button radle-customize-button',
                    target: '_blank',
                    html: '<span class="dashicons dashicons-admin-tools"></span>' + radleSettings.i18n['requestCustomizations']
                });

                // Create "My GBTI Membership Account" button
                var $accountButton = $('<a>', {
                    href: 'https://gbti.network/account/',
                    class: 'radle-support-button radle-account-button',
                    target: '_blank',
                    html: '<span class="dashicons dashicons-admin-users"></span>' + radleSettings.i18n['myGBTIAccount'] 
                });

                // Add buttons to container
                $supportContainer.append($issuesButton, $customizeButton, $accountButton);

                // Add buttons under GitHub reset button in the profile left section
                $('.radle-profile-left').append($supportContainer);
                return;
            }

            var noticeHtml = `
                <div class="notice radle-sponsor-notice">
                    <div class="notice-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="notice-content">
                        <p>${radleSettings.i18n['sponsorshipRequired']}</p>
                        <a href="https://github.com/sponsors/gbti-network" class="sponsor-link" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-heart"></span>
                            ${radleSettings.i18n['becomeGitHubSponsor']}
                        </a>
                    </div>
                </div>`;

            $('.radle-header-container').after(noticeHtml);
        }).fail((jqXHR, textStatus, errorThrown) => {
            console.error('Failed to check sponsor status:', textStatus, errorThrown);
            
            var errorHtml = `
                <div class="notice notice-error radle-sponsor-notice">
                    <div class="notice-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="notice-content">
                        <p>${radleSettings.i18n['sponsorCheckErrorDetail']}</p>
                    </div>
                </div>`;
            
            $('.radle-header-container').after(errorHtml);
        });
    },
    hideRedditAuthorizationFields: function() {
        $('input[name="radle_client_id"]').closest('tr').hide();
        $('input[name="radle_client_secret"]').closest('tr').hide();
        $('#radle-authorize-button').closest('tr').hide();
        $('button[name="radle_reset_button"]').closest('tr').show();
        $('#reddit-api-documentation').closest('tr').hide();
    },
    generateRedditProfileCard: function(response) {
        return `
        <div class="radle-profile-card">
            <img src="${response.user_info.avatar_url}" alt="${response.user_info.user_name}" class="radle-avatar">
            <h3>${response.user_info.user_name}</h3>
            <p><a href="https://www.reddit.com/user/${response.user_info.user_name}" target="_blank">/u/${response.user_info.user_name}</a></p>
            <p><a href="https://www.reddit.com/settings/apps" target="_blank">${radleSettings.i18n['manageApps']}</a></p>
        </div>`;
    },
    generateGitHubProfileCard: function(userInfo) {
        return `
        <div class="radle-profile-card">
            <img src="${userInfo.avatar_url}" alt="${userInfo.login}" class="radle-avatar">
            <h3>${userInfo.name} (${userInfo.login})</h3>
            <p>${userInfo.company || radleSettings.i18n['noCompany']}</p>
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
    bindGitHubEvents: function() {
        $('#radle-github-reset-button').on('click', this.resetGitHubAuthorization.bind(this));
    },
    fetchGitHubReleases: function() {
        $.ajax({
            url: wpApiSettings.root + 'radle/v1/get-releases',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
        }).done(function(response) {
            if (response.releases_html) {
                $('#radle-releases-content').html(response.releases_html);
            }
        }).fail(function(response) {
            console.error(radleSettings.i18n['releasesFetchFail'], response);
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
    handleSubredditSelection: function(e) {
        const selectedSubreddit = $(e.target).val();

        $.ajax({
            url: wpApiSettings.root + 'radle/v1/radle/set-subreddit',
            method: 'POST',
            data: {
                subreddit: selectedSubreddit
            },
            beforeSend: (xhr) => {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
        }).done((response) => {
            if (response.success) {
                this.loadLatestEntries();
            } else {
                this.showApiConnectionError('reddit', radleSettings.i18n['subredditUpdateFailed']);
            }
        }).fail((response) => {
            this.showApiConnectionError('reddit', radleSettings.i18n['subredditUpdateFailed']);
        });
    },
    handleApiConnectionFailure: function(api) {
        $('#radle-' + api + '-authorize-button').closest('tr').show();
        $('#radle-' + api + '-reset-button').closest('tr').hide();
        this.showApiConnectionError(api, radleSettings.i18n[api + 'FailedApiConnection']);
    },
    showApiConnectionError: function(api, message) {
        $('.radle-api-notice').remove();
        var noticeHtml = `
            <div class="notice radle-sponsor-notice radle-api-notice">
                <div class="notice-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="notice-content">
                    <p>${message}</p>
                </div>
            </div>`;
        $('.radle-header-container').after(noticeHtml);
    },
    initTabs: function() {
        var tabs = jQuery('.nav-tab-wrapper .nav-tab');
        var form = jQuery('.wrap form');

        // Retrieve the last selected tab from localStorage, default to 'publishing' if not found
        var lastTab = localStorage.getItem('radleLastTab') || 'reddit';

        // Hide all tab contents initially
        jQuery('div[id^="radle-settings-"]').hide();

        // Set the active tab based on localStorage or URL
        jQuery('.nav-tab[href="?page=radle-settings&tab=' + lastTab + '"]').addClass('nav-tab-active');
        jQuery('#radle-settings-' + lastTab).show();

        // Run the API check only once and then update visibility
        var apiConnectionPromise = RadleSettings.checkApiConnections();

        // Handle tab click event
        tabs.on('click', function(e) {
            e.preventDefault();
            var tabId = jQuery(this).attr('href').split('=').pop();

            // Remove active class from all tabs and add it to the clicked tab
            tabs.removeClass('nav-tab-active');
            jQuery(this).addClass('nav-tab-active');

            // Hide all settings sections and show the current tab's section
            jQuery('div[id^="radle-settings-"]').hide();
            jQuery('#radle-settings-' + tabId).show();

            // Store the current tab in localStorage
            localStorage.setItem('radleLastTab', tabId);

            // Ensure we wait for API check to complete before updating submit button visibility
            apiConnectionPromise.done(function() {
                RadleSettings.updateSubmitButtonVisibility(tabId);
            });

            // Update the URL in the address bar without reloading the page
            history.pushState(null, null, jQuery(this).attr('href'));
        });

        // Initially run the visibility update after the connection check
        apiConnectionPromise.done(function() {
            RadleSettings.updateSubmitButtonVisibility(lastTab);
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
        var self = this;
        self.debug.log(`Initiating ${api} OAuth authorization flow`);
        
        var clientRedirectUri = wpApiSettings.root + 'radle/v1/github/oauth-callback';
        var oauthUrl = api === 'github' ? radleSettings.gbtiServerUri +'/oauth' : radleSettings.redditOAuthUrl;

        $.ajax({
            url: oauthUrl + '/initiate',
            method: 'GET',
            dataType: 'json',
            data: {
                client_redirect_uri: clientRedirectUri
            }
        }).done(function(response) {
            if (response.auth_url) {
                self.debug.log(`${api} OAuth URL obtained, redirecting to authorization page`);
                window.location.href = response.auth_url;
            } else {
                self.debug.error(`Failed to obtain ${api} OAuth authorization URL`);
                RadleSettings.showApiConnectionError(api, radleSettings.i18n.failedToGetAuthUrl.replace('%s', api));
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            self.debug.error(`${api} OAuth initialization failed:`, {status: textStatus, error: errorThrown});
            RadleSettings.showApiConnectionError(api, radleSettings.i18n.failedToInitiateOAuth.replace('%s', api));
        });
    },
    updateAuthorizationRows: function() {
        this.debug.log('Updating authorization row visibility');
        var redditClientId = $('#radle-client-id').val();
        var redditClientSecret = $('#radle-client-secret').val();

        if (redditClientId && redditClientSecret) {
            this.debug.log('Client credentials present, showing authorization button');
            $('#radle-reddit-authorize-button').closest('tr').show();
        } else {
            this.debug.log('Client credentials missing, hiding authorization button');
            $('#radle-reddit-authorize-button').closest('tr').hide();
        }
    },
    resetWelcomeProcess: function(e) {
        this.debug.log('Resetting welcome process');
        e.preventDefault();
        if(confirm(radleSettings.i18n.resetWelcomeConfirm)) {
            var self = this;
            $.ajax({
                url: wpApiSettings.root + 'radle/v1/welcome/reset-progress',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(function(response) {
                self.debug.log('Welcome process reset successful, reloading page');
                location.reload();
            }).fail(function(response) {
                self.debug.error('Welcome process reset failed:', response);
                alert(response.responseJSON.message);
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
    addGitHubIcon: function() {
        const $button = $('#radle-github-authorize-button');
        if ($button.length && !$button.find('img').length) {
            const iconHtml = `<img src="${radleSettings.pluginUrl}assets/images/github-icon.svg" alt="GitHub Icon" />`;
            $button.prepend(iconHtml);
        }
    },
    addSupportButtons: function() {
        this.debug.log('Adding support buttons');
        
        // Create container for support buttons
        var $supportContainer = $('<div>', {
            class: 'radle-support-buttons'
        });

        // Create "Raise Issues" button
        var $issuesButton = $('<a>', {
            href: 'https://github.com/gbti-network/radle-wordpress-plugin/issues',
            class: 'radle-support-button radle-issues-button',
            target: '_blank',
            html: '<span class="dashicons dashicons-sos"></span>' + radleSettings.i18n['raiseIssues'] || 'Raise Issues'
        });

        // Create "Request Customizations" button
        var $customizeButton = $('<a>', {
            href: 'https://app.codeable.io/presets/apply?token=WMG5pJZeMnB2hY4enPmXGwHge9c321mQ1es16PHUcVX63wX5xt',
            class: 'radle-support-button radle-customize-button',
            target: '_blank',
            html: '<span class="dashicons dashicons-admin-tools"></span>' + radleSettings.i18n['requestCustomizations'] || 'Request Customizations'
        });

        // Add buttons to container
        $supportContainer.append($issuesButton, $customizeButton);

        // Find the reset button and add our support buttons after it
        $('button[name="radle_reset_button"]').closest('tr').after($supportContainer);
    },
};

jQuery(document).ready(function() {
    RadleSettings.init();
});
