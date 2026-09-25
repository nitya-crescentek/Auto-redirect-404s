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
            this.setupToggleLabels();

            // Settings tab
            if ($('#r404c_redirect_url').length) {
                this.setupQuickSelect();
                this.setupFormValidation();
                this.setupUrlPreview();
            }

            // Redirection Manager tab
            if ($('#r404c-redirect-form').length) {
                this.setupRedirectForm();
            }
        },

        /**
         * Add / edit redirect form: match type hints, 410 handling and the
         * page picker for the target.
         */
        setupRedirectForm: function() {
            var self = this;
            var i18n = (window.r404c_ajax && r404c_ajax.i18n) || {};
            var $form = $('#r404c-redirect-form form');
            var $source = $('#r404c_source_url');
            var $match = $('#r404c_match_type');
            var $hint = $('#r404c_match_hint');
            var $code = $('#r404c_status_code');
            var $target = $('#r404c_target_url');
            var $quick = $('#r404c_target_quick_select');

            function refreshHint() {
                var $option = $match.find(':selected');
                $hint.text($option.data('hint') || '');
                $source.attr('placeholder', $option.data('placeholder') || '');
            }

            // A 410 answers "gone" instead of redirecting, so it has no target.
            function refreshTarget() {
                var gone = '410' === $code.val();
                $target.prop('disabled', gone);
                $quick.prop('disabled', gone);
                $('.r404c-target-field').toggleClass('is-disabled', gone);
            }

            $match.on('change', refreshHint);
            $code.on('change', refreshTarget);

            $quick.on('change', function() {
                var url = $(this).val();
                if (url) {
                    $target.val(url).trigger('input').focus();
                    $(this).val('');
                }
            });

            $form.on('submit', function(e) {
                if (!$.trim($source.val())) {
                    e.preventDefault();
                    self.showError(i18n.source_required || 'Enter the source URL.');
                    $source.focus();
                    return false;
                }

                if ('410' !== $code.val() && !$.trim($target.val())) {
                    e.preventDefault();
                    self.showError(i18n.target_required || 'Enter the target URL.');
                    $target.focus();
                    return false;
                }

                return true;
            });

            refreshHint();
            refreshTarget();
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
            $('#r404c_redirect_url').closest('form').on('submit', function(e) {
                var isValid = true;
                var url = $('#r404c_redirect_url').val().trim();
                
                if (url && !self.isValidUrl(url)) {
                    e.preventDefault();
                    self.showError('Please enter a valid URL for the redirect.');
                    $('#r404c_redirect_url').focus();
                    isValid = false;
                }

                // No same-site warning here. Pointing 404s at your own homepage
                // is the normal, recommended setup, and the old check compared
                // hostnames only, so it fired on every save. Genuine loops are
                // caught server-side: the request and destination are compared
                // by host and path, and Redirect Loop Protection verifies the
                // destination is not itself a 404.
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
         * Show error message
         */
        showError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.wp-header-end').after($notice);
            
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
            $('.wp-header-end').after($notice);
            
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