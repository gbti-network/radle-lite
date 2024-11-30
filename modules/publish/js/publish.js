var RadlePublish = {
    init: function() {
        this.togglePostOptions();
        this.bindEvents();
        this.checkApiConnection();
    },

    togglePostOptions: function() {
        if (jQuery('#radle_post_type_self').is(':checked')) {
            jQuery('.content-template').show();
            jQuery('#radle-preview-post-button').show();
        } else {
            jQuery('.content-template').hide();
            jQuery('#radle-preview-post-button').hide();
        }
    },

    bindEvents: function() {
        jQuery('input[name="radle_post_type"]').change(this.togglePostOptions.bind(this));

        jQuery('#radle-publish-reddit-button').on('click', this.confirmPublishPost.bind(this));
        jQuery('#radle-preview-post-button').on('click', this.previewPost.bind(this));
        jQuery('#radle-delete-reddit-id-button').on('click', this.deleteRedditId.bind(this));

        jQuery('.radle-tokens code').on('click', this.copyToken.bind(this));
    },

    checkApiConnection: function() {
        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/reddit/check-auth',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            }
        }).done(function(response) {
            if (response.is_authorized) {
                RadlePublish.handleApiConnectionSuccess();
            } else {
                RadlePublish.handleApiConnectionFailure();
            }
        }).fail(function(response) {
            RadlePublish.handleApiConnectionFailure();
        });
    },

    handleApiConnectionSuccess: function() {
        jQuery('.radle-options-container').show();
        jQuery('#radle-authorize-link').hide();
    },

    handleApiConnectionFailure: function() {
        this.refreshToken();
    },

    refreshToken: function() {
        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/reddit/refresh-token',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            }
        }).done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                RadlePublish.showApiConnectionError();
            }
        }).fail(function(response) {
            RadlePublish.showApiConnectionError();
        });
    },

    showApiConnectionError: function() {
        var errorMessage = radlePublishingSettings.failedApiConnection;
        var notification = jQuery('<div/>', {
            class: 'notice notice-error is-dismissible',
            html: '<p>' + errorMessage + '</p>'
        });
        jQuery('.wrap').prepend(notification);
    },

    confirmPublishPost: function() {
        if (confirm('Are you sure you want to publish this post to Reddit?')) {
            this.publishPost();
        }
    },

    previewPost: function() {
        var data = {
            title: jQuery('#radle_post_title').val(),
            content: jQuery('#radle_post_content').val(),
            post_id: radlePublishingSettings.post_id,
        };

        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/preview',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            },
            data: data
        }).done(function(response) {
            if (response && response.title && response.content) {
                var previewHtml = '<h2>' + response.title + '</h2><pre>' + response.content + '</pre>';
                jQuery('<div>').html(previewHtml).dialog({
                    title: 'Post Preview',
                    width: 600,
                    modal: true,
                    buttons: {
                        Close: function() {
                            jQuery(this).dialog('close');
                        }
                    }
                });
            } else {
                alert('Invalid response from server');
            }
        }).fail(function(response) {
            alert(response.responseJSON.message);
        });
    },

    publishPost: function() {
        var data = {
            post_id: jQuery('#post_ID').val(),
            post_type: jQuery('input[name="radle_post_type"]:checked').val(),
            title: jQuery('#radle_post_title').val(),
            content: jQuery('#radle_post_content').val()
        };

        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/reddit/publish',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            },
            data: data
        }).done(function(response) {
            if (response.data && response.data.status === 500) {
                var errorMessage = response.message;
                if (response.data.error_message) {
                    errorMessage += ': ' + response.data.error_message;
                }
                alert(errorMessage);
            } else if (response.success === false && response.data && response.data.code === 'invalid_token') {
                RadlePublish.refreshToken();
            } else {
                alert(response.message);
                if (response.url) {
                    jQuery('.radle-options-container').html('<p>' + radlePublishingSettings.success_message + ':<br><a href="' + response.url + '" target="_blank">' + response.url + '</a>.</p>');
                }
            }
        }).fail(function(response) {
            alert('An unexpected error occurred.');
        });
    },

    deleteRedditId: function() {
        var data = {
            post_id: jQuery('#post_ID').val()
        };

        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/disassociate',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            },
            data: data
        }).done(function(response) {
            alert(response.message);
            location.reload();
        }).fail(function(response) {
            alert(response.responseJSON.message);
        });
    },

    copyToken: function(event) {
        var token = jQuery(event.target).data('token');
        var $temp = jQuery('<input>');
        jQuery('body').append($temp);
        $temp.val(token).select();
        document.execCommand('copy');
        $temp.remove();
    }
};

jQuery(document).ready(function() {
    RadlePublish.init();
});