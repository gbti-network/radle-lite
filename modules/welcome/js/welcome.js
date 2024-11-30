var $ = jQuery.noConflict();

const RadleWelcome = {
    debug: new RadleDebugger('welcome.js', true), // Debug instance for this module

    init: function() {
        this.debug.log('Initializing RadleWelcome');
        try {
            this.bindEvents();
            this.checkInitialState();
            this.handleToggleSwitch();
        } catch (error) {
            this.debug.error('Error in init:', error);
        }
    },

    bindEvents: function() {
        $('.get-started, .next-step, .prev-step').on('click', this.handleStepNavigation.bind(this));
        $('.reset-welcome').on('click', this.handleReset.bind(this));
        $('.github-auth').on('click', this.handleGitHubAuth.bind(this));
        $('.enable-comments').on('click', this.handleEnableComments.bind(this));
        $('.skip-comments').on('click', this.handleSkipComments.bind(this));
    },

    checkInitialState: function() {
        const currentStep = parseInt($('.welcome-step:visible').data('step'));
        if (currentStep === 2) {
            this.checkGitHubAuthorization();
        } else if (currentStep === 3) {
            this.checkSponsorStatus();
        } else if (currentStep === 7) {
            this.setupSubredditDropdown();
        } else if (currentStep === 9) {
            this.showConfetti();
        }
    },
    handleStepNavigation: function(e) {
        e.preventDefault();
        const nextStep = $(e.currentTarget).data('step');
        const currentStep = parseInt($('.welcome-step:visible').data('step'));

        if (nextStep === 5) {
            const shareEvents = $('input[name="radle_share_events"]').is(':checked');
            const shareDomain = $('input[name="radle_share_domain"]').is(':checked');

            this.updateProgress(nextStep, {
                radle_share_events: shareEvents,
                radle_share_domain: shareDomain
            });
        } else if (nextStep === 6) {
            if (this.validateRedditCredentials()) {
                const clientId = $('#reddit_client_id').val().trim();
                const clientSecret = $('#reddit_client_secret').val().trim();
                this.updateProgress(nextStep, {
                    reddit_client_id: clientId,
                    reddit_client_secret: clientSecret
                });
            }
        } else if (nextStep === 8) {
            const selectedSubreddit = $('#radle-subreddit-select').val();
            if (selectedSubreddit) {
                this.updateProgress(nextStep);
            } else {
                alert(radleWelcome.i18n.selectSubredditFirst);
            }
        } else {
            this.updateProgress(nextStep);
        }
    },
    handleReset: function(e, skipConfirmation = false) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }

        if (skipConfirmation || confirm(radleWelcome.i18n['resetWelcomeMessage'])) {
            $.ajax({
                url: radleWelcome.root + 'radle/v1/welcome/reset-progress',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
                }
            }).done((response) => {
                if (response.success) {
                    window.location.reload();
                } else {
                    this.debug.error('Error resetting progress:', response.message);
                }
            }).fail((jqXHR, textStatus, errorThrown) => {
                this.debug.error('Failed to reset progress:', textStatus, errorThrown);
            });
        }
    },
    handleGitHubAuth: function(e) {
        e.preventDefault();

        const clientRedirectUri = radleWelcome.root + 'radle/v1/github/oauth-callback';

        $.ajax({
            url: radleWelcome.gbtiServerUri + '/oauth/initiate',
            method: 'GET',
            dataType: 'json',
            data: {
                client_redirect_uri: clientRedirectUri,
                state: 'welcome'  // Add state parameter to indicate we're in welcome flow
            }
        }).done((response) => {
            if (response.auth_url) {
                window.location.href = response.auth_url;
            } else {
                this.debug.error('Failed to get authorization URL');
            }
        }).fail((jqXHR, textStatus, errorThrown) => {
            this.debug.error('Failed to initiate OAuth:', textStatus, errorThrown);
        });
    },
    // Add this to your existing RadleWelcome object
    handleToggleSwitch: function() {
        $('.toggle-switch input[type="checkbox"]').on('change', function() {
            const $label = $(this).closest('.toggle-switch');
            const enabledTitle = $(this).data('enabled-title');
            const disabledTitle = $(this).data('disabled-title');

            if ($(this).is(':checked')) {
                $label.attr('title', enabledTitle);
            } else {
                $label.attr('title', disabledTitle);
            }

            if ($(this).attr('name') === 'share_none') {
                if ($(this).is(':checked')) {
                    $('.toggle-switch input[type="checkbox"]').not(this).prop('checked', false);
                }
            } else {
                if ($(this).is(':checked')) {
                    $('.toggle-switch input[name="share_none"]').prop('checked', false);
                }
            }

        });

        $('.toggle-switch input[type="checkbox"]').each(function() {
            const $label = $(this).closest('.toggle-switch');
            const enabledTitle = $(this).data('enabled-title');
            const disabledTitle = $(this).data('disabled-title');

            if ($(this).is(':checked')) {
                $label.attr('title', enabledTitle);
            } else {
                $label.attr('title', disabledTitle);
            }
        });
    },
    checkGitHubAuthorization: function() {
        $.ajax({
            url: radleWelcome.root + 'radle/v1/github/check-auth',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            }
        }).done((response) => {
            if (response.is_authorized) {
                this.updateProgress(3);
            } else if (radleWelcome.isRedirect) {
                this.showGitHubAuthorizationFailure();
            }
        }).fail((jqXHR, textStatus, errorThrown) => {
            this.debug.error('Failed to check authorization:', textStatus, errorThrown);
            if (radleWelcome.isRedirect) {
                this.showGitHubAuthorizationFailure();
            }
        });
    },
    checkSponsorStatus: function() {
        $.ajax({
            url: radleWelcome.gbtiServerUri + '/oauth/check-sponsor/',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + radleWelcome.githubToken);
            },
            data: JSON.stringify({
                product_name: radleWelcome.repoName
            }),
            contentType: 'application/json',
            dataType: 'json'
        }).done((response) => {
            if (response.is_sponsor) {
                this.updateProgress(4 , {});
            } else {
                this.debug.error('Repository access denied:', response.message);
                this.showSponsorMessage();
            }
        }).fail((jqXHR, textStatus, errorThrown) => {
            this.debug.error('Failed to check repository access:', textStatus, errorThrown);

        });
    },
    showSponsorMessage: function() {
        $('#repo-access-message').hide();
        $('#sponsor-message').show();
        $('.try-again').show().on('click', function(e) {
            e.preventDefault();
            RadleWelcome.handleReset(null, true);
        });
    },
    validateRedditCredentials: function() {
        const clientId = $('#reddit_client_id').val().trim();
        const clientSecret = $('#reddit_client_secret').val().trim();

        if (clientId === '' || clientSecret === '') {
            alert(radleWelcome.i18n.enter_both_credentials);
            return false;
        }

        return true;
    },
    updateProgress: function(step, data = {}, skipProcessing = false) {
        if (!skipProcessing) {
            $.ajax({
                url: radleWelcome.root + 'radle/v1/welcome/update-progress',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
                },
                data: {
                    step: step,
                    ...data
                }
            }).done((response) => {

                if (response.success) {
                    window.location.reload();
                }
               
            });
        } else {
            $('.welcome-step').hide();
            $(`.step-${step}`).show();
            if (step === 9) {
                this.showConfetti();
            }
        }
    },

    storeAccessToken: function(token) {
        $.ajax({
            url: radleWelcome.root + 'radle/v1/welcome/exchange-token',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            },
            data: {
                access_token: token
            }
        }).done((response) => {
            if (response.success) {
                this.debug.log('Token stored successfully');
                const url = new URL(window.location);
                url.searchParams.delete('access_token');
                window.history.replaceState({}, document.title, url.toString());
                this.updateProgress(3);
            } else {
                this.debug.error('Error storing token:', response.message);
                this.showGitHubAuthorizationFailure();
            }
        }).fail((jqXHR, textStatus, errorThrown) => {
            this.debug.error('Failed to store token:', textStatus, errorThrown);
            this.showGitHubAuthorizationFailure();
        });
    },
    setupSubredditDropdown: function() {
        const subredditSelect = document.getElementById('radle-subreddit-select');
        const nextButton = $('.next-step[data-step="8"]');
        if (subredditSelect) {
            // Populate the dropdown
            $.ajax({
                url: radleWelcome.root + 'radle/v1/reddit/check-auth',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
                }
            }).done((data) => {
                if (data.is_authorized) {
                    if (data.moderated_subreddits && data.moderated_subreddits.length > 0) {
                        data.moderated_subreddits.forEach(subreddit => {
                            const option = document.createElement('option');
                            option.value = subreddit;
                            option.textContent = subreddit;
                            if (subreddit === data.current_subreddit) {
                                option.selected = true;
                            }
                            subredditSelect.appendChild(option);
                        });
                        this.enableNextButton();
                    } else {
                        this.debug.error('No moderated subreddits found');
                        // You might want to add some user-friendly message here
                    }
                } else {
                    // Handle case where Reddit is not authorized
                    this.showRedditAuthorizationFailure();
                }
            }).fail((jqXHR, textStatus, errorThrown) => {
                this.debug.error('Failed to fetch subreddits:', textStatus, errorThrown);
                this.showRedditAuthorizationFailure();
            });

            // Handle dropdown change
            $(subredditSelect).on('change', function() {
                const selectedSubreddit = $(this).val();
                nextButton.prop('disabled', !selectedSubreddit);

                if (selectedSubreddit) {
                    $.ajax({
                        url: radleWelcome.root + 'radle/v1/radle/set-subreddit',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
                        },
                        data: {
                            subreddit: selectedSubreddit
                        }
                    }).done((data) => {
                        if (data.success) {
                            this.debug.log('Subreddit updated successfully');
                        } else {
                            this.debug.error('Failed to update subreddit');
                            nextButton.prop('disabled', true);
                        }
                    }).fail((jqXHR, textStatus, errorThrown) => {
                        this.debug.error('Failed to update subreddit:', textStatus, errorThrown);
                        nextButton.prop('disabled', true);
                    });
                }
            });
        }
    },
    showGitHubAuthorizationFailure: function() {
        $('.welcome-step.step-2 .success-message').remove();
        $('.welcome-step.step-2').append('<p class="error-message">' + radleWelcome.i18n.authorizationFailed + '</p>');
    },
    showRedditAuthorizationFailure: function() {
        $('.welcome-step.step-7').append('<p class="error-message">' + radleWelcome.i18n.redditAuthorizationFailed + '</p>');
    },
    enableNextButton: function() {
        $('.next-step[data-step="8"]').prop('disabled', false);
    },
    disableNextButton: function() {
        $('.next-step[data-step="8"]').prop('disabled', true);
    },
    handleEnableComments: function(e) {
        e.preventDefault();
        // Enable Radle comments
        $.ajax({
            url: radleWelcome.root + 'radle/v1/settings',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            },
            data: {
                radle_comment_system: 'radle'
            }
        }).always(() => {
            this.updateProgress(9);
        });
    },

    handleSkipComments: function(e) {
        e.preventDefault();
        // Keep WordPress comments
        $.ajax({
            url: radleWelcome.root + 'radle/v1/settings',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            },
            data: {
                radle_comment_system: 'wordpress'
            }
        }).always(() => {
            this.updateProgress(9);
        });
    },
    showConfetti: function() {
        if (typeof confetti !== 'undefined') {
            const duration = 3000;
            const end = Date.now() + duration;

            const colors = ['#ff0000', '#00ff00', '#0099ff'];

            (function frame() {
                confetti({
                    particleCount: 3,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 },
                    colors: colors
                });
                confetti({
                    particleCount: 3,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 },
                    colors: colors
                });

                if (Date.now() < end) {
                    requestAnimationFrame(frame);
                }
            }());
        }
    },

};

$(document).ready(function() {
    RadleWelcome.init();

    const token = new URLSearchParams(window.location.search).get('access_token');
    const error = new URLSearchParams(window.location.search).get('error');

    if (token || error) {
        // We're coming from a redirect
        if (token) {
            RadleWelcome.storeAccessToken(token);
        } else if (error) {
            RadleWelcome.debug.error('Authorization error:', error);
            RadleWelcome.showGitHubAuthorizationFailure();
        }
    }
});