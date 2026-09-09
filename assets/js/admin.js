/**
 * Admin JavaScript for 404 Redirect Plugin
 *
 * @package Redirect404Custom
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Document ready
     */
    $(document).ready(function() {
        R404C_Admin.init();
    });

    /**
     * Main admin object
     */
    var R404C_Admin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.setupQuickSelect();
            this.setupFormValidation();
            this.setupToggleLabels();
            this.setupUrlPreview();
        },

        /**
         * Setup quick select functionality
         */
        setupQuickSelect: function() {
            $('#r404c_quick_select').on('change', function() {
                var selectedUrl = $(this).val();
                if (selectedUrl) {
                    $('#r404c_redirect_url').val(selectedUrl).trigger('input');
                    
                    // Add visual feedback
                    $('#r404c_redirect_url').addClass('updated');
                    setTimeout(function() {
                        $('#r404c_redirect_url').removeClass('updated');
                    }, 1000);
                }
            });
        },

        /**
         * Setup form validation
         */
        setupFormValidation: function() {
            var self = this;
            
            // Real-time URL validation
            $('#r404c_redirect_url').on('input blur', function() {
                var url = $(this).val().trim();
                var $input = $(this);
                
                // Clear previous validation state
                $input.removeClass('valid invalid');
                
                if (url) {
                    if (self.isValidUrl(url)) {
                        $input.addClass('valid');
                    } else {
                        $input.addClass('invalid');
                    }
                }
            });

            // Form submission validation
            $('form').on('submit', function(e) {
                var isValid = true;
                var url = $('#r404c_redirect_url').val().trim();
                
                if (url && !self.isValidUrl(url)) {
                    e.preventDefault();
                    self.showError('Please enter a valid URL for the redirect.');
                    $('#r404c_redirect_url').focus();
                    isValid = false;
                }

                // Check if redirect URL is the same as current site
                if (url && self.isSameAsCurrent(url)) {
                    if (!confirm('The redirect URL is the same as your current site URL. This might cause redirect loops. Are you sure you want to continue?')) {
                        e.preventDefault();
                        isValid = false;
                    }
                }

                return isValid;
            });
        },

        /**
         * Setup toggle labels
         */
        setupToggleLabels: function() {
            $('.r404c-toggle input').on('change', function() {
                var $label = $(this).closest('.r404c-toggle-container').find('.r404c-toggle-label');
                var isChecked = $(this).is(':checked');
                
                if (isChecked) {
                    $label.text($label.data('on'));
                } else {
                    $label.text($label.data('off'));
                }
            });

            // Initialize labels
            $('.r404c-toggle input').trigger('change');
        },

        /**
         * Setup URL preview
         */
        setupUrlPreview: function() {
            var $urlInput = $('#r404c_redirect_url');
            var $preview = $('<div class="r404c-url-preview"></div>');
            
            $urlInput.after($preview);

            $urlInput.on('input', function() {
                var url = $(this).val().trim();
                if (url) {
                    $preview.html('<small><strong>Preview:</strong> <a href="' + url + '" target="_blank">' + url + '</a></small>').show();
                } else {
                    $preview.hide();
                }
            });

            // Initialize preview
            $urlInput.trigger('input');
        },

        /**
         * Validate URL
         */
        isValidUrl: function(string) {
            try {
                // Add protocol if missing
                if (!string.match(/^https?:\/\//)) {
                    string = 'http://' + string;
                }
                
                var url = new URL(string);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (e) {
                return false;
            }
        },

        /**
         * Check if URL is same as current site
         */
        isSameAsCurrent: function(url) {
            try {
                // Add protocol if missing
                if (!url.match(/^https?:\/\//)) {
                    url = 'http://' + url;
                }
                
                var inputUrl = new URL(url);
                var homeUrl = new URL(r404c_ajax.home_url);
                
                return inputUrl.hostname === homeUrl.hostname;
            } catch (e) {
                return false;
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Add CSS for validation states
     */
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .r404c-url-input input.valid {
                border-color: #4caf50 !important;
                box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2) !important;
            }
            
            .r404c-url-input input.invalid {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
            }
            
            .r404c-url-input input.updated {
                animation: highlight 1s ease;
            }
            
            @keyframes highlight {
                0% { background-color: #fff3cd; }
                100% { background-color: transparent; }
            }
            
            .r404c-url-preview {
                margin-top: 8px;
                padding: 8px 12px;
                background-color: #f8f9fa;
                border-left: 3px solid #4caf50;
                border-radius: 0 4px 4px 0;
            }
            
            .r404c-url-preview a {
                color: #4caf50;
                text-decoration: none;
                word-break: break-all;
            }
            
            .r404c-url-preview a:hover {
                text-decoration: underline;
            }
        `)
        .appendTo('head');

})(jQuery);