/**
 * Print Manager Pro - Product Configurator
 * Handles real-time price calculation and file upload.
 */
(function($) {
    'use strict';

    var PMP_Configurator = {
        debounceTimer: null,

        init: function() {
            this.bindEvents();
            this.calculatePrice();
        },

        bindEvents: function() {
            var self = this;

            // Price recalculation on any change
            $('#pmp-format, #pmp-paper, #pmp-weight, #pmp-sides, #pmp-color, #pmp-quantity').on('change', function() {
                self.calculatePrice();
            });

            $('input[name="pmp_finishing[]"]').on('change', function() {
                self.calculatePrice();
            });

            // File upload
            $('#pmp-file-upload').on('change', function() {
                self.uploadFile(this.files[0]);
            });
        },

        calculatePrice: function() {
            var self = this;

            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function() {
                self.doCalculation();
            }, 300);
        },

        doCalculation: function() {
            var $configurator = $('#pmp-configurator');
            var productId = $configurator.data('product-id');

            if (!productId) return;

            var finishing = [];
            $('input[name="pmp_finishing[]"]:checked').each(function() {
                finishing.push($(this).val());
            });

            var data = {
                action: 'pmp_calculate_price',
                nonce: pmp_config.nonce,
                product_id: productId,
                format: $('#pmp-format').val() || '',
                paper: $('#pmp-paper').val() || '',
                weight: $('#pmp-weight').val() || '',
                sides: $('#pmp-sides').val(),
                color: $('#pmp-color').val(),
                quantity: $('#pmp-quantity').val(),
                finishing: finishing
            };

            $('.pmp-price-loading').show();
            $('.pmp-calculated-price').css('opacity', '0.5');

            $.post(pmp_config.ajax_url, data, function(response) {
                $('.pmp-price-loading').hide();
                $('.pmp-calculated-price').css('opacity', '1');

                if (response.success) {
                    var result = response.data;
                    var unitPrice = parseFloat(result.unit_price).toFixed(4);
                    var totalPrice = parseFloat(result.total_price).toFixed(2);

                    $('.pmp-unit-price').text(unitPrice + ' ' + pmp_config.currency);
                    $('.pmp-total-price').text(totalPrice + ' ' + pmp_config.currency);
                    $('#pmp-calculated-price').val(totalPrice);

                    // Show discount info if applicable
                    if (result.discount_percent > 0) {
                        var discountHtml = '<br><small style="color:#00a32a;">Remise quantité : -' + result.discount_percent + '%</small>';
                        $('.pmp-total-price').after(function() {
                            $(this).siblings('.pmp-discount-info').remove();
                            return '<span class="pmp-discount-info">' + discountHtml + '</span>';
                        });
                    } else {
                        $('.pmp-discount-info').remove();
                    }
                }
            });
        },

        uploadFile: function(file) {
            if (!file) return;

            // Validate file size
            if (file.size > pmp_config.max_upload_size) {
                this.showUploadResult('Le fichier dépasse la taille maximale de 100 Mo.', 'error');
                return;
            }

            // Validate file type
            var ext = file.name.split('.').pop().toLowerCase();
            var allowed = ['pdf', 'ai', 'psd', 'png', 'jpg', 'jpeg'];
            if (allowed.indexOf(ext) === -1) {
                this.showUploadResult('Type de fichier non autorisé.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'pmp_upload_file');
            formData.append('nonce', pmp_config.nonce);
            formData.append('pmp_file', file);

            var self = this;
            $('.pmp-upload-progress').show();
            $('.pmp-upload-result').hide();

            $.ajax({
                url: pmp_config.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var pct = Math.round((e.loaded / e.total) * 100);
                            $('.pmp-progress-fill').css('width', pct + '%');
                            $('.pmp-progress-text').text(pct + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $('.pmp-upload-progress').hide();
                    if (response.success) {
                        $('#pmp-uploaded-file').val(response.data.file_url);
                        self.showUploadResult('Fichier "' + response.data.file_name + '" téléchargé avec succès.', 'success');
                    } else {
                        self.showUploadResult(response.data.message, 'error');
                    }
                },
                error: function() {
                    $('.pmp-upload-progress').hide();
                    self.showUploadResult('Erreur de connexion. Veuillez réessayer.', 'error');
                }
            });
        },

        showUploadResult: function(message, type) {
            var $result = $('.pmp-upload-result');
            $result.removeClass('success error').addClass(type);
            $result.text(message).show();
        }
    };

    $(document).ready(function() {
        if ($('#pmp-configurator').length) {
            PMP_Configurator.init();
        }
    });

})(jQuery);
