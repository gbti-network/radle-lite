console.log('Radle Publish JS loaded - Version with WebSocket support');

var RadlePublish = {
    mediaFrame: null,

    init: function() {
        console.log('RadlePublish.init() called');

        // Force image mode as default (override any saved setting)
        // This ensures image mode is always the default until user manually changes it in settings
        var checkedRadio = jQuery('input[name="radle_post_type"]:checked');
        if (checkedRadio.length === 0 || checkedRadio.val() !== 'image') {
            // If nothing is checked OR if something other than image is checked, select image
            jQuery('#radle_post_type_image').prop('checked', true);
        }

        this.togglePostOptions();
        this.bindEvents();
        this.checkApiConnection();
        this.initImageUploader();
    },

    togglePostOptions: function() {
        var selectedType = jQuery('input[name="radle_post_type"]:checked').val();

        // Hide all option containers first
        jQuery('#radle_self_options, #radle_image_options, #radle-preview-post-button').hide();

        // Reset field visibility for self options
        jQuery('#radle_self_options .content-template, #radle_self_options .url-field').show();

        // Use switch for cleaner logic
        switch (selectedType) {
            case 'self':
                jQuery('#radle_self_options, #radle-preview-post-button').show();
                jQuery('#radle_self_options .url-field').hide();
                break;
            case 'link':
                // Link posts show title, URL, content, and preview
                jQuery('#radle_self_options, #radle-preview-post-button').show();
                jQuery('#radle_self_options .url-field').show();
                break;
            case 'image':
                jQuery('#radle_image_options, #radle-preview-post-button').show();
                break;
        }
    },

    bindEvents: function() {
        jQuery('input[name="radle_post_type"]').change(this.togglePostOptions.bind(this));

        jQuery('#radle-publish-reddit-button').on('click', this.confirmPublishPost.bind(this));
        jQuery('#radle-preview-post-button').on('click', this.previewPost.bind(this));
        jQuery('#radle-delete-reddit-id-button').on('click', this.deleteRedditId.bind(this));

        jQuery('.radle-tokens code').on('click', this.copyToken.bind(this));

        // Image uploader events
        jQuery('#radle_add_images').on('click', this.openMediaLibrary.bind(this));
        jQuery(document).on('click', '.radle-image-item .remove-image', this.removeImage.bind(this));
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
        var errorMessage = radlePublishingSettings.strings.failed_api_connection;
        var notification = jQuery('<div/>', {
            class: 'notice notice-error is-dismissible',
            html: '<p>' + errorMessage + '</p>'
        });
        jQuery('.wrap').prepend(notification);
    },

    confirmPublishPost: function() {
        if (confirm(radlePublishingSettings.strings.confirm_publish)) {
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
                    title: radlePublishingSettings.strings.preview_title,
                    width: 600,
                    modal: true,
                    buttons: [{
                        text: radlePublishingSettings.strings.close_button,
                        click: function() {
                            jQuery(this).dialog('close');
                        }
                    }]
                });
            } else {
                alert(radlePublishingSettings.strings.invalid_response);
            }
        }).fail(function(response) {
            var errorMsg = response.responseJSON?.message || radlePublishingSettings.strings.unexpected_error;
            alert(errorMsg);
        });
    },

    initImageUploader: function() {
        // Initialize drag and drop
        var $gallery = jQuery('#radle_image_gallery');

        $gallery.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $gallery.addClass('drag-over');
        });

        $gallery.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $gallery.removeClass('drag-over');
        });

        $gallery.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            RadlePublish.handleFileSelection(files);
        });

        // Set empty state text
        if ($gallery.children().length === 0) {
            $gallery.attr('data-empty-text', radlePublishingSettings.strings.empty_gallery_text);
        }
    },

    openMediaLibrary: function() {
        // Create media frame if it doesn't exist
        if (!this.mediaFrame) {
            this.mediaFrame = wp.media({
                title: radlePublishingSettings.strings.media_library_title,
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            this.mediaFrame.on('select', function() {
                var selection = RadlePublish.mediaFrame.state().get('selection');
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    RadlePublish.addImageToGallery(attachment);
                });
            });
        }

        this.mediaFrame.open();
    },

    addImageToGallery: function(attachment) {
        // Validate image format and size
        var validation = this.validateImage(attachment);
        if (!validation.valid) {
            this.showValidationMessage(validation.message, 'error');
            return;
        }

        var $gallery = jQuery('#radle_image_gallery');
        var imageHtml = this.createImageItemHtml(attachment);

        $gallery.append(imageHtml);
        $gallery.removeAttr('data-empty-text');
    },

    createImageItemHtml: function(attachment) {
        var imageUrl = attachment.sizes && attachment.sizes.medium ?
                       attachment.sizes.medium.url : attachment.url;

        return '<div class="radle-image-item" data-attachment-id="' + attachment.id + '">' +
               '<img src="' + imageUrl + '" alt="' + (attachment.alt || attachment.title) + '">' +
               '<button type="button" class="remove-image" title="' + radlePublishingSettings.strings.remove_image + '">×</button>' +
               '<input type="hidden" name="radle_images[]" value="' + attachment.id + '">' +
               '</div>';
    },

    removeImage: function(e) {
        e.preventDefault();
        var $item = jQuery(e.target).closest('.radle-image-item');
        $item.remove();

        // Reset empty state if no images
        var $gallery = jQuery('#radle_image_gallery');
        if ($gallery.children().length === 0) {
            $gallery.attr('data-empty-text', radlePublishingSettings.strings.empty_gallery_text);
        }
    },

    validateImage: function(attachment) {
        var result = { valid: true, message: '' };

        // Check file type
        if (radlePublishingSettings.supported_formats.indexOf(attachment.mime) === -1) {
            result.valid = false;
            result.message = radlePublishingSettings.strings.unsupported_format;
            return result;
        }

        // Check file size (WordPress provides size in bytes)
        if (attachment.filesizeInBytes && attachment.filesizeInBytes > radlePublishingSettings.max_file_size) {
            result.valid = false;
            result.message = radlePublishingSettings.strings.file_too_large;
            return result;
        }

        return result;
    },

    handleFileSelection: function(files) {
        for (var i = 0; i < files.length; i++) {
            var file = files[i];

            // Basic validation
            if (radlePublishingSettings.supported_formats.indexOf(file.type) === -1) {
                this.showValidationMessage(radlePublishingSettings.strings.unsupported_format + ': ' + file.name, 'error');
                continue;
            }

            if (file.size > radlePublishingSettings.max_file_size) {
                this.showValidationMessage(radlePublishingSettings.strings.file_too_large + ': ' + file.name, 'error');
                continue;
            }

            // For drag and drop, we'd need to upload to WordPress media library first
            // This is a simplified version - full implementation would require media upload
            this.showValidationMessage(radlePublishingSettings.strings.drag_drop_coming_soon, 'warning');
        }
    },

    showValidationMessage: function(message, type) {
        var $container = jQuery('#radle_image_options');
        var $message = jQuery('<div class="radle-validation-message ' + type + '">' + message + '</div>');

        // Remove existing messages
        $container.find('.radle-validation-message').remove();

        // Add new message
        $container.prepend($message);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 5000);
    },

    publishPost: function() {
        console.log('publishPost called');

        var postType = jQuery('input[name="radle_post_type"]:checked').val();
        console.log('Post type:', postType);

        var data = {
            post_id: jQuery('#post_ID').val(),
            post_type: postType
        };

        // Set data based on post type
        switch (postType) {
            case 'image':
                data.title = jQuery('#radle_image_title').val();
                data.content = jQuery('#radle_image_content').val();

                // Get selected images
                var selectedImages = [];
                jQuery('#radle_image_gallery input[name="radle_images[]"]').each(function() {
                    selectedImages.push(jQuery(this).val());
                });

                console.log('Selected images:', selectedImages);

                if (selectedImages.length === 0) {
                    alert(radlePublishingSettings.strings.no_images_selected);
                    return;
                }

                // New flow: prepare images first, poll WebSocket, then publish
                console.log('Calling prepareAndPublishImages');
                this.prepareAndPublishImages(data.post_id, data.title, data.content, selectedImages);
                return;

            case 'link':
                data.title = jQuery('#radle_post_title').val();
                data.content = jQuery('#radle_post_content').val();
                data.url = jQuery('#radle_post_url').val();
                break;

            default: // 'self'
                data.title = jQuery('#radle_post_title').val();
                data.content = jQuery('#radle_post_content').val();
                break;
        }

        // Non-image posts use direct publish
        this.submitToReddit(data);
    },

    prepareAndPublishImages: function(postId, title, content, imageIds) {
        var self = this;

        console.log('prepareAndPublishImages called', {postId: postId, title: title, imageIds: imageIds});

        var imageCount = imageIds.length;
        var postTypeText = imageCount === 1 ? 'image' : 'gallery';

        // Show progress modal
        this.showProgressModal('Uploading images to Reddit...', imageCount);

        // Start progress animation immediately (0% to 70% over 10 seconds)
        this.startProgressAnimation(postTypeText);

        // Show progress indicator
        jQuery('#radle-publish-reddit-button').prop('disabled', true).text('Uploading to Reddit...');

        var ajaxUrl = radlePublishingSettings.root + 'radle/v1/reddit/prepare-images';
        console.log('Making AJAX request to:', ajaxUrl);

        // Step 1: Upload images to Reddit CDN and get WebSocket URLs
        jQuery.ajax({
            url: ajaxUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
                console.log('Request headers set');
            },
            data: {
                images: imageIds
            }
        }).done(function(response) {
            console.log('Prepare images response:', response);

            if (!response.success || !response.assets || response.assets.length === 0) {
                console.error('Failed to prepare images:', response);
                self.handlePublishError('Failed to upload images to Reddit');
                return;
            }

            console.log('Assets uploaded successfully');

            // Update modal message
            jQuery('#radle-progress-modal .radle-progress-message').text('Submitting post to Reddit...');
            jQuery('#radle-publish-reddit-button').text('Submitting to Reddit...');

            self.submitToReddit({
                post_id: postId,
                post_type: 'image',
                title: title,
                content: content,
                prepared_assets: response.assets
            });
        }).fail(function(xhr, status, error) {
            console.error('AJAX request failed:', {xhr: xhr, status: status, error: error});
            self.handlePublishError('Failed to prepare images');
        });
    },

    startProgressAnimation: function(postTypeText) {
        var self = this;

        // Animate progress bar from 0% to 70% over 20 seconds
        var targetPercent = 70;
        var duration = 20000; // 20 seconds
        var intervalTime = 100; // Update every 100ms
        var steps = duration / intervalTime;
        var increment = targetPercent / steps;

        self.currentProgressPercent = 0;

        jQuery('#radle-progress-modal .radle-progress-status').text('Please wait while Reddit processes your ' + postTypeText + '...');

        var progressInterval = setInterval(function() {
            self.currentProgressPercent += increment;
            if (self.currentProgressPercent >= targetPercent) {
                self.currentProgressPercent = targetPercent;
                clearInterval(progressInterval);
                self.currentProgressInterval = null;
                // Show encouraging message when we hit 70%
                jQuery('#radle-progress-modal .radle-progress-status').text('Hang tight, just a little while longer...');
            }
            jQuery('#radle-progress-modal .radle-progress-fill').css('width', self.currentProgressPercent + '%');
        }, intervalTime);

        // Store interval ID so we can clear it when submission completes
        self.currentProgressInterval = progressInterval;
    },

    completeProgress: function(callback) {
        var self = this;

        // Clear existing animation if running
        if (self.currentProgressInterval) {
            clearInterval(self.currentProgressInterval);
            self.currentProgressInterval = null;
        }

        // Get current progress or default to 70% if not set
        var currentPercent = self.currentProgressPercent || 70;

        // Animate from current position to 100% over 500ms
        var targetPercent = 100;
        var duration = 500;
        var intervalTime = 20;
        var steps = duration / intervalTime;
        var increment = (targetPercent - currentPercent) / steps;

        var completeInterval = setInterval(function() {
            currentPercent += increment;
            if (currentPercent >= targetPercent) {
                currentPercent = targetPercent;
                clearInterval(completeInterval);
                jQuery('#radle-progress-modal .radle-progress-fill').css('width', '100%');
                jQuery('#radle-progress-modal .radle-progress-status').text('Complete!');
                // Wait a moment to show 100% before callback
                if (callback) {
                    setTimeout(callback, 300);
                }
            } else {
                jQuery('#radle-progress-modal .radle-progress-fill').css('width', currentPercent + '%');
            }
        }, intervalTime);
    },

    monitorAssetProcessing: function(assets) {
        var self = this;
        var promises = [];
        var completedCount = 0;
        var totalAssets = assets.length;

        console.log('monitorAssetProcessing called with', assets.length, 'assets');

        assets.forEach(function(asset, index) {
            console.log('Processing asset:', asset.asset_id, 'WebSocket URL:', asset.websocket_url);

            if (!asset.websocket_url) {
                console.log('No WebSocket URL for asset', asset.asset_id, '- resolving immediately');
                promises.push(Promise.resolve(asset));
                return;
            }

            promises.push(new Promise(function(resolve, reject) {
                console.log('Opening WebSocket for asset:', asset.asset_id);
                var ws = new WebSocket(asset.websocket_url);
                var wsMessageReceived = false;

                // Wait for asset processing - Reddit needs time to process uploads
                var timeout = setTimeout(function() {
                    console.warn('WebSocket timeout for asset:', asset.asset_id, '- proceeding anyway (asset should be ready)');
                    ws.close();
                    completedCount++;
                    self.updateProgressModal('Asset ready, proceeding...', totalAssets, completedCount);
                    resolve(asset); // Resolve anyway instead of rejecting
                }, 20000); // 20 second timeout to give Reddit time to process

                ws.onopen = function() {
                    console.log('WebSocket opened for asset:', asset.asset_id);
                };

                ws.onmessage = function(event) {
                    wsMessageReceived = true;
                    console.log('WebSocket message received for asset', asset.asset_id, ':', event.data);

                    try {
                        var data = JSON.parse(event.data);
                        console.log('Parsed WebSocket data:', data);

                        if (data.type === 'complete' || data.state === 'succeeded' || data.status === 'complete') {
                            console.log('Asset processing completed:', asset.asset_id);
                            completedCount++;
                            self.updateProgressModal('Processing images on Reddit...', totalAssets, completedCount);
                            clearTimeout(timeout);
                            ws.close();
                            resolve(asset);
                        } else if (data.type === 'failed' || data.state === 'failed' || data.status === 'failed') {
                            console.error('Asset processing failed:', asset.asset_id);
                            completedCount++;
                            self.updateProgressModal('Asset processing failed, proceeding...', totalAssets, completedCount);
                            clearTimeout(timeout);
                            ws.close();
                            resolve(asset); // Resolve anyway to not block the flow
                        } else {
                            console.log('WebSocket status update:', data);
                        }
                    } catch (e) {
                        console.error('Failed to parse WebSocket message:', e);
                    }
                };

                ws.onerror = function(error) {
                    console.error('WebSocket error for asset', asset.asset_id, ':', error);
                    clearTimeout(timeout);
                    // Don't reject on WebSocket error - add small safety delay before assuming asset is ready
                    setTimeout(function() {
                        console.log('Resolving asset after WebSocket error with 3s delay:', asset.asset_id);
                        resolve(asset);
                    }, 3000); // 3 second safety delay to ensure Reddit had time to register asset
                };

                ws.onclose = function(event) {
                    console.log('WebSocket closed for asset', asset.asset_id, 'Code:', event.code, 'Reason:', event.reason);
                    clearTimeout(timeout);
                    // If WebSocket closes cleanly without explicit success/fail, assume asset is ready
                    // This handles cases where Reddit closes the connection after processing
                    if (event.code === 1000) {
                        console.log('WebSocket closed normally, resolving asset:', asset.asset_id);
                        resolve(asset);
                    }
                };
            }));
        });

        console.log('Waiting for', promises.length, 'promises to resolve');
        return Promise.all(promises);
    },

    submitToReddit: function(data) {
        var self = this;

        jQuery.ajax({
            url: radlePublishingSettings.root + 'radle/v1/reddit/publish',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
            },
            data: data
        }).done(function(response) {
            console.log('Submit response:', response);

            // Handle processing state - post submitted but needs time to get final ID
            if (response.processing && response.websocket_url) {
                console.log('Post is processing - monitoring WebSocket for final URL...');

                // Animate to 100% while waiting for WebSocket
                self.completeProgress(function() {
                    jQuery('#radle-progress-modal .radle-progress-message').text('Post submitted! Waiting for final URL...');
                    jQuery('#radle-progress-modal .radle-progress-status').text('Almost there...');
                });

                self.monitorSubmissionProcessing(response.websocket_url, data.post_id).then(function(finalUrl) {
                    jQuery('#radle-publish-reddit-button').prop('disabled', false).text('Publish to Reddit');
                    self.closeProgressModal();

                    alert(response.message);
                    if (finalUrl) {
                        jQuery('.radle-options-container').html('<p>' + radlePublishingSettings.strings.success_message + ':<br><a href="' + finalUrl + '" target="_blank">' + finalUrl + '</a>.</p>');
                    }
                }).catch(function(error) {
                    console.error('Failed to get final URL:', error);
                    jQuery('#radle-publish-reddit-button').prop('disabled', false).text('Publish to Reddit');
                    self.closeProgressModal();

                    // Still show success - post was created
                    alert(response.message + ' (Check your Reddit profile to find the post)');
                });
                return;
            }

            // Normal success flow - we have the URL immediately
            // Animate to 100% then show alert
            self.completeProgress(function() {
                jQuery('#radle-publish-reddit-button').prop('disabled', false).text('Publish to Reddit');
                self.closeProgressModal();

                if (response.data && response.data.status === 500) {
                    var errorMessage = response.message;
                    if (response.data.error_message) {
                        errorMessage += ': ' + response.data.error_message;
                    }
                    alert(errorMessage);
                } else if (response.success === false && response.data && response.data.code === 'invalid_token') {
                    self.refreshToken();
                } else {
                    alert(response.message);
                    if (response.url) {
                        jQuery('.radle-options-container').html('<p>' + radlePublishingSettings.strings.success_message + ':<br><a href="' + response.url + '" target="_blank">' + response.url + '</a>.</p>');
                    }
                }
            });
        }).fail(function(response) {
            // Stop progress animation and reset
            if (self.currentProgressInterval) {
                clearInterval(self.currentProgressInterval);
                self.currentProgressInterval = null;
            }

            jQuery('#radle-publish-reddit-button').prop('disabled', false).text('Publish to Reddit');
            self.closeProgressModal();
            alert(radlePublishingSettings.strings.unexpected_error);
        });
    },

    monitorSubmissionProcessing: function(websocketUrl, postId) {
        var self = this;

        return new Promise(function(resolve, reject) {
            console.log('Opening submission WebSocket:', websocketUrl);
            var ws = new WebSocket(websocketUrl);
            var messageReceived = false;

            var timeout = setTimeout(function() {
                console.warn('Submission WebSocket timeout - proceeding with fallback');
                ws.close();
                reject(new Error('WebSocket timeout'));
            }, 30000); // 30 second timeout

            ws.onopen = function() {
                console.log('Submission WebSocket connected');
            };

            ws.onmessage = function(event) {
                console.log('Submission WebSocket message:', event.data);
                messageReceived = true;

                try {
                    var data = JSON.parse(event.data);

                    // Reddit sends the post URL when it's ready
                    if (data.payload && data.payload.redirect) {
                        var redditUrl = data.payload.redirect;
                        console.log('Got final Reddit URL:', redditUrl);

                        // Extract post ID from URL
                        var postIdMatch = redditUrl.match(/\/comments\/([a-z0-9]+)\//);
                        if (postIdMatch) {
                            var redditPostId = postIdMatch[1];
                            console.log('Extracted Reddit post ID:', redditPostId);

                            // Save association via backend
                            self.associateRedditPost(postId, redditPostId, redditUrl).then(function() {
                                clearTimeout(timeout);
                                ws.close();
                                resolve(redditUrl);
                            }).catch(function(error) {
                                console.error('Failed to save association:', error);
                                clearTimeout(timeout);
                                ws.close();
                                resolve(redditUrl); // Still return URL even if save fails
                            });
                        } else {
                            clearTimeout(timeout);
                            ws.close();
                            resolve(redditUrl);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing WebSocket message:', e);
                }
            };

            ws.onerror = function(error) {
                console.error('Submission WebSocket error:', error);
                clearTimeout(timeout);
                reject(error);
            };

            ws.onclose = function() {
                console.log('Submission WebSocket closed');
                if (!messageReceived) {
                    clearTimeout(timeout);
                    reject(new Error('WebSocket closed without receiving data'));
                }
            };
        });
    },

    associateRedditPost: function(wpPostId, redditPostId, redditUrl) {
        return new Promise(function(resolve, reject) {
            jQuery.ajax({
                url: radlePublishingSettings.root + 'radle/v1/reddit/associate',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', radlePublishingSettings.nonce);
                },
                data: {
                    post_id: wpPostId,
                    reddit_id: redditPostId,
                    reddit_url: redditUrl
                }
            }).done(function(response) {
                console.log('Association saved successfully');
                resolve(response);
            }).fail(function(xhr, status, error) {
                console.error('Failed to save association:', error);
                reject(error);
            });
        });
    },

    handlePublishError: function(message) {
        jQuery('#radle-publish-reddit-button').prop('disabled', false).text('Publish to Reddit');
        this.closeProgressModal();
        alert(message);
    },

    showProgressModal: function(message, totalAssets) {
        // Remove existing modal if present
        jQuery('#radle-progress-modal').remove();

        var modal = jQuery('<div id="radle-progress-modal" class="radle-modal">' +
            '<div class="radle-modal-content">' +
                '<div class="radle-modal-header">' +
                    '<h3>Publishing to Reddit</h3>' +
                '</div>' +
                '<div class="radle-modal-body">' +
                    '<p class="radle-progress-message">' + message + '</p>' +
                    '<div class="radle-progress-bar">' +
                        '<div class="radle-progress-fill" style="width: 0%"></div>' +
                    '</div>' +
                    '<p class="radle-progress-status">0 of ' + totalAssets + ' assets ready</p>' +
                '</div>' +
            '</div>' +
        '</div>');

        jQuery('body').append(modal);
        modal.fadeIn(200);
    },

    updateProgressModal: function(message, total, completed) {
        var percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        jQuery('#radle-progress-modal .radle-progress-message').text(message);
        jQuery('#radle-progress-modal .radle-progress-fill').css('width', percentage + '%');
        jQuery('#radle-progress-modal .radle-progress-status').text(completed + ' of ' + total + ' assets ready');
    },

    closeProgressModal: function() {
        jQuery('#radle-progress-modal').fadeOut(200, function() {
            jQuery(this).remove();
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