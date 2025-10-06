var $ = jQuery.noConflict();

const RadleWelcome = {
    debug: (typeof RadleDebugger !== 'undefined') ? new RadleDebugger('welcome.js', true) : { log: function() {}, error: function() {}, warn: function() {} },

    init: function() {
        this.debug.log('Initializing RadleWelcome');
        try {
            this.bindEvents();
            this.checkInitialState();
        } catch (error) {
            this.debug.error('Error in init:', error);
        }
    },

    bindEvents: function() {
        $('.get-started, .next-step, .prev-step').on('click', this.handleStepNavigation.bind(this));
        $('.reset-welcome').on('click', this.handleReset.bind(this));
        $('.enable-attribution').on('click', () => this.handleAttribution(true));
        $('.disable-attribution').on('click', () => this.handleAttribution(false));
        $('.enable-comments').on('click', this.handleEnableComments.bind(this));
        $('.skip-comments').on('click', this.handleSkipComments.bind(this));
    },

    checkInitialState: function() {
        const currentStep = parseInt($('.welcome-step:visible').data('step'));
        if (currentStep === 4) {
            this.setupSubredditDropdown();
        } else if (currentStep === 8) {
            this.showConfetti();
        }
    },

    handleStepNavigation: function(e) {
        e.preventDefault();
        const nextStep = $(e.currentTarget).data('step');
        const currentStep = parseInt($('.welcome-step:visible').data('step'));

        if (nextStep === 3) {
            if (this.validateRedditCredentials()) {
                this.updateProgress(nextStep);
            }
        } else if (nextStep === 5) {
            const selectedSubreddit = $('#radle-subreddit-select').val();
            if (selectedSubreddit) {
                this.updateProgress(nextStep, { subreddit: selectedSubreddit });
            } else {
                alert(radleWelcome.i18n.selectSubreddit);
            }
        } else if (nextStep === 8) {
            const shareEvents = $('#radle_share_events').is(':checked');
            const shareDomain = $('#radle_share_domain').is(':checked');
            this.updateProgress(nextStep, {
                share_events: shareEvents,
                share_domain: shareDomain
            });
        } else {
            this.updateProgress(nextStep);
        }
    },

    handleReset: function(e) {
        e.preventDefault();
        
        $.ajax({
            url: radleWelcome.root + 'radle/v1/welcome/reset',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            }
        })
        .done(function() {
            location.reload();
        })
        .fail(function(response) {
            this.debug.error('Failed to reset progress:', response);
        }.bind(this));
    },

    handleAttribution: function(enabled) {
        this.updateProgress(7, { 
            attribution_enabled: enabled 
        });
    },

    validateRedditCredentials: function() {
        const clientId = $('#reddit_client_id').val().trim();
        const clientSecret = $('#reddit_client_secret').val().trim();

        if (!clientId || !clientSecret) {
            alert(radleWelcome.i18n.enter_both_credentials);
            return false;
        }

        return true;
    },

    updateProgress: function(step, data = {}, skipProcessing = false) {
        this.debug.log('Updating progress to step:', step);
        
        if (!skipProcessing) {
            data.step = step;
            
            if (step === 3) {
                data.client_id = $('#reddit_client_id').val();
                data.client_secret = $('#reddit_client_secret').val();
            }
        }

        $.ajax({
            url: radleWelcome.root + 'radle/v1/welcome/progress',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radleWelcome.nonce);
            },
            data: data
        })
        .done(function(response) {
            if (!skipProcessing && response.success) {
                location.reload();
            }
        })
        .fail(function(response) {
            this.debug.error('Failed to update progress:', response);
        }.bind(this));
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
                        this.showRedditAuthorizationFailure();
                    }
                } else {
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
                    }).done((response) => {
                        if (response.success) {
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
            }.bind(this));
        }
    },

    showRedditAuthorizationFailure: function() {
        alert(radleWelcome.i18n.redditAuthorizationFailed);
    },

    enableNextButton: function() {
        $('.next-step').prop('disabled', false);
    },

    disableNextButton: function() {
        $('.next-step').prop('disabled', true);
    },

    handleEnableComments: function(e) {
        e.preventDefault();
        this.updateProgress(6, {
            enable_comments: true
        });
    },

    handleSkipComments: function(e) {
        e.preventDefault();
        this.updateProgress(6, {
            enable_comments: false
        });
    },

    showConfetti: function() {
        const count = 200;
        const defaults = {
            origin: { y: 0.7 }
        };

        function fire(particleRatio, opts) {
            confetti({
                ...defaults,
                ...opts,
                particleCount: Math.floor(count * particleRatio)
            });
        }

        fire(0.25, {
            spread: 26,
            startVelocity: 55,
        });

        fire(0.2, {
            spread: 60,
        });

        fire(0.35, {
            spread: 100,
            decay: 0.91,
            scalar: 0.8
        });

        fire(0.1, {
            spread: 120,
            startVelocity: 25,
            decay: 0.92,
            scalar: 1.2
        });

        fire(0.1, {
            spread: 120,
            startVelocity: 45,
        });
    }
};

$(document).ready(function() {
    RadleWelcome.init();
});