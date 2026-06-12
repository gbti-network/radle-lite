/**
 * Radle Lite - Destination Override JavaScript
 *
 * Handles the destination override UI and functionality in the publish metabox.
 */

(function($) {
    'use strict';

    var RadleDestination = {
        init: function() {
            this.bindEvents();
            this.loadSubreddits();
        },

        bindEvents: function() {
            var self = this;

            // Toggle destination override panel
            $(document).on('click', '#radle_destination_settings_toggle', this.toggleDestinationOverride.bind(this));

            // Toggle custom dropdown
            $(document).on('click', '#radle_override_destination_trigger', function(e) {
                e.stopPropagation();
                self.toggleDropdown();
            });

            // Handle option selection
            $(document).on('click', '.radle-dropdown-option', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.selectOption($(this));
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.radle-custom-dropdown').length) {
                    self.closeDropdown();
                }
            });
        },

        /**
         * Toggle destination override panel
         */
        toggleDestinationOverride: function() {
            var $override = $('#radle_destination_override');
            $override.slideToggle(200);

            // Toggle icon rotation
            var $icon = $('#radle_destination_settings_toggle');
            if ($override.is(':visible')) {
                $icon.css('transform', 'rotate(45deg)');
            } else {
                $icon.css('transform', 'rotate(0deg)');
            }
        },

        /**
         * Toggle custom dropdown
         */
        toggleDropdown: function() {
            var $trigger = $('#radle_override_destination_trigger');
            var $content = $('#radle_override_destination_content');

            $trigger.toggleClass('active');
            $content.toggleClass('show');
        },

        /**
         * Close dropdown
         */
        closeDropdown: function() {
            var $trigger = $('#radle_override_destination_trigger');
            var $content = $('#radle_override_destination_content');

            $trigger.removeClass('active');
            $content.removeClass('show');
        },

        /**
         * Select an option
         */
        selectOption: function($option) {
            var value = $option.data('value');
            var text = $option.text();

            // Update hidden input
            $('#radle_override_destination').val(value);

            // Update trigger text
            $('#radle_override_destination_trigger .radle-dropdown-selected').text(text);

            // Update active state
            $('.radle-dropdown-option').removeClass('active');
            $option.addClass('active');

            // Close dropdown
            this.closeDropdown();
        },

        /**
         * Load connected subreddits from Reddit API
         */
        loadSubreddits: function() {
            var self = this;
            var $group = $('#radle_subreddit_group');
            var $label = $group.find('.radle-dropdown-group-label');

            $.ajax({
                url: wpApiSettings.root + 'radle/v1/reddit/check-auth',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: function(response) {
                    if (response.is_authorized && response.moderated_subreddits) {
                        $label.text('Subreddits');

                        // Remove existing options (keep only the label)
                        $group.find('.radle-dropdown-option').remove();

                        // Add moderated subreddits to dropdown
                        response.moderated_subreddits.forEach(function(subreddit) {
                            var $option = $('<a href="#" class="radle-dropdown-option"></a>')
                                .attr('data-value', subreddit)
                                .text('r/' + subreddit);
                            $group.append($option);
                        });
                    } else {
                        $label.text('No subreddits found');
                    }
                },
                error: function() {
                    $label.text('Failed to load subreddits');
                }
            });
        },

        /**
         * Add destination override data to publish request
         *
         * This function is called by Lite via window.radleFilterPublishData hook
         *
         * @param object data The publish data object
         * @return object Modified data object
         */
        filterPublishData: function(data) {
            var overrideDestination = $('#radle_override_destination').val();

            if (overrideDestination) {
                // Check if it's a user profile (starts with u_)
                if (overrideDestination.indexOf('u_') === 0) {
                    data.override_destination_type = 'profile';
                    data.override_subreddit = overrideDestination;
                } else {
                    // It's a subreddit
                    data.override_destination_type = 'subreddit';
                    data.override_subreddit = overrideDestination;
                }
            }

            return data;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RadleDestination.init();

        // Expose filter function to Lite
        window.radleFilterPublishData = RadleDestination.filterPublishData;
    });

})(jQuery);
