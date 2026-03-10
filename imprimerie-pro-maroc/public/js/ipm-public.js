/**
 * Imprimerie Pro Maroc — Scripts publics
 */
(function($) {
    'use strict';

    var IPM = {

        init: function() {
            this.bindCategoryFilter();
            this.bindProductOptions();
            this.bindPriceCalculator();
            this.bindQuoteForm();
            this.bindFileUpload();
            this.bindOrderTracking();
            this.bindAddToCart();
            this.bindGallery();
            this.bindDropZones();
            this.bindDependentOptions();
        },

        // Filtre par catégorie
        bindCategoryFilter: function() {
            $('.ipm-filter-btn').on('click', function() {
                var category = $(this).data('category');
                $('.ipm-filter-btn').removeClass('active');
                $(this).addClass('active');

                if (category === 'all') {
                    $('.ipm-product-card').removeClass('hidden');
                } else {
                    $('.ipm-product-card').each(function() {
                        var cats = ($(this).data('categories') || '').split(',');
                        if (cats.indexOf(category) !== -1) {
                            $(this).removeClass('hidden');
                        } else {
                            $(this).addClass('hidden');
                        }
                    });
                }
            });
        },

        // Changement d'options produit
        bindProductOptions: function() {
            var $form = $('#ipm-product-options-form');
            if (!$form.length) return;

            var debounceTimer;
            $form.on('change', '.ipm-option-input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    IPM.calculatePrice($form);
                }, 300);
            });

            // Quantité personnalisée
            $form.on('change', 'select[name="quantity"]', function() {
                if ($(this).val() === 'custom') {
                    $('#ipm-custom-quantity').show();
                } else {
                    $('#ipm-custom-quantity').hide();
                }
            });
        },

        // Calcul de prix
        calculatePrice: function($form) {
            var data = $form.serialize();
            data += '&action=ipm_calculate_price';

            $('#ipm-dynamic-price').text(ipmPublic.messages.calculating);

            $.post(ipmPublic.ajaxUrl, data, function(response) {
                if (response.success) {
                    var result = response.data;
                    $('#ipm-dynamic-price').text(IPM.formatPrice(result.total));
                    $('#ipm-total-price').text(IPM.formatPrice(result.total));

                    // Détail
                    var $breakdown = $('#ipm-price-breakdown');
                    $breakdown.empty();
                    if (result.breakdown) {
                        result.breakdown.forEach(function(line) {
                            var val = typeof line.value === 'number'
                                ? IPM.formatPrice(line.value)
                                : line.value;
                            $breakdown.append(
                                '<div class="ipm-breakdown-line"><span>' +
                                IPM.escHtml(line.label) + '</span><span>' +
                                IPM.escHtml(val) + '</span></div>'
                            );
                        });
                    }

                    $('#ipm-price-summary').show();
                }
            });
        },

        // Calculateur de prix page
        bindPriceCalculator: function() {
            var $form = $('#ipm-calculator-form');
            if (!$form.length) return;

            // Charger les options quand on sélectionne un produit
            $('#ipm-calc-product').on('change', function() {
                var productId = $(this).val();
                if (!productId) {
                    $('#ipm-calc-options').empty();
                    $('#ipm-calc-result').hide();
                    return;
                }

                $.post(ipmPublic.ajaxUrl, {
                    action: 'ipm_get_product_options',
                    product_id: productId
                }, function(response) {
                    if (response.success) {
                        IPM.renderCalcOptions(response.data);
                    }
                });
            });

            // Recalculer au changement
            var debounce;
            $form.on('change', '.ipm-calc-input', function() {
                clearTimeout(debounce);
                debounce = setTimeout(function() {
                    IPM.calculateCalcPrice($form);
                }, 300);
            });
        },

        renderCalcOptions: function(data) {
            var $container = $('#ipm-calc-options');
            $container.empty();

            $.each(data.options, function(key, opt) {
                var html = '<div class="ipm-calc-field">';
                html += '<label>' + IPM.escHtml(opt.label);
                if (opt.required) html += ' <span class="ipm-required">*</span>';
                html += '</label>';

                if (opt.type === 'select' && opt.choices) {
                    html += '<select name="' + key + '" class="ipm-calc-input ipm-option-input">';
                    html += '<option value="">-- Sélectionner --</option>';
                    $.each(opt.choices, function(val, label) {
                        html += '<option value="' + IPM.escHtml(val) + '">' + IPM.escHtml(label) + '</option>';
                    });
                    html += '</select>';
                } else if (opt.type === 'radio' && opt.choices) {
                    html += '<div class="ipm-radio-group">';
                    $.each(opt.choices, function(val, label) {
                        html += '<label class="ipm-radio-option">';
                        html += '<input type="radio" name="' + key + '" value="' + IPM.escHtml(val) + '" class="ipm-calc-input">';
                        html += '<span>' + IPM.escHtml(label) + '</span></label>';
                    });
                    html += '</div>';
                } else if (opt.type === 'checkbox') {
                    html += '<label class="ipm-checkbox-option">';
                    html += '<input type="checkbox" name="' + key + '" value="1" class="ipm-calc-input">';
                    html += '<span>' + IPM.escHtml(opt.label) + '</span></label>';
                }

                html += '</div>';
                $container.append(html);
            });
        },

        calculateCalcPrice: function($form) {
            var data = $form.serialize();

            $.post(ipmPublic.ajaxUrl, data, function(response) {
                if (response.success) {
                    var result = response.data;
                    var $breakdown = $('#ipm-calc-breakdown');
                    $breakdown.empty();

                    if (result.breakdown) {
                        result.breakdown.forEach(function(line) {
                            var val = typeof line.value === 'number'
                                ? IPM.formatPrice(line.value)
                                : line.value;
                            $breakdown.append(
                                '<div class="ipm-breakdown-line"><span>' +
                                IPM.escHtml(line.label) + '</span><span>' +
                                IPM.escHtml(val) + '</span></div>'
                            );
                        });
                    }

                    $('#ipm-calc-total-price').text(IPM.formatPrice(result.total));
                    $('#ipm-calc-result').show();
                }
            });
        },

        // Formulaire de devis
        bindQuoteForm: function() {
            $('#ipm-quote-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                var $msg = $('#ipm-quote-message');

                $btn.prop('disabled', true).html('<span class="ipm-spinner"></span> Envoi en cours...');

                var formData = new FormData($form[0]);

                $.ajax({
                    url: ipmPublic.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $msg.removeClass('ipm-message-error').addClass('ipm-message-success')
                                .html(response.data.message).show();
                            $form[0].reset();
                        } else {
                            $msg.removeClass('ipm-message-success').addClass('ipm-message-error')
                                .html(response.data.message).show();
                        }
                    },
                    error: function() {
                        $msg.removeClass('ipm-message-success').addClass('ipm-message-error')
                            .html(ipmPublic.messages.error).show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Demander un devis');
                        $('html, body').animate({ scrollTop: $msg.offset().top - 100 }, 500);
                    }
                });
            });
        },

        // Upload de fichier
        bindFileUpload: function() {
            $('#ipm-file-upload-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                var $msg = $('#ipm-upload-message');

                // Validation côté client
                var file = $('#ipm-upload-file')[0].files[0];
                if (!file) {
                    $msg.addClass('ipm-message-error').html('Veuillez sélectionner un fichier.').show();
                    return;
                }

                var maxSize = ipmPublic.maxFileSize * 1024 * 1024;
                if (file.size > maxSize) {
                    $msg.addClass('ipm-message-error').html(ipmPublic.messages.fileTooLarge).show();
                    return;
                }

                var ext = file.name.split('.').pop().toLowerCase();
                var allowed = ipmPublic.allowedTypes.split(',');
                if (allowed.indexOf(ext) === -1) {
                    $msg.addClass('ipm-message-error').html(ipmPublic.messages.invalidType).show();
                    return;
                }

                $btn.prop('disabled', true).html('<span class="ipm-spinner"></span> ' + ipmPublic.messages.uploading);

                var formData = new FormData($form[0]);

                $.ajax({
                    url: ipmPublic.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $msg.removeClass('ipm-message-error').addClass('ipm-message-success')
                                .html(response.data.message).show();
                            $form[0].reset();
                            $('#ipm-file-preview').hide();
                        } else {
                            $msg.removeClass('ipm-message-success').addClass('ipm-message-error')
                                .html(response.data.message).show();
                        }
                    },
                    error: function() {
                        $msg.removeClass('ipm-message-success').addClass('ipm-message-error')
                            .html(ipmPublic.messages.error).show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Téléverser le fichier');
                    }
                });
            });

            // Aperçu fichier
            $('#ipm-upload-file').on('change', function() {
                var file = this.files[0];
                if (file) {
                    var $preview = $('#ipm-file-preview');
                    var size = IPM.formatFileSize(file.size);
                    $preview.html(
                        '<strong>' + IPM.escHtml(file.name) + '</strong> <span>(' + size + ')</span>'
                    ).show();
                }
            });
        },

        // Suivi commande
        bindOrderTracking: function() {
            $('#ipm-tracking-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                var $msg = $('#ipm-tracking-message');
                var $result = $('#ipm-tracking-result');

                $btn.prop('disabled', true).text('Recherche...');

                $.post(ipmPublic.ajaxUrl, $form.serialize(), function(response) {
                    if (response.success) {
                        $msg.hide();
                        var data = response.data;

                        var html = '<div class="ipm-tracking-info">';
                        html += '<p><strong>Commande :</strong> #' + IPM.escHtml(data.order_number) + '</p>';
                        html += '<p><strong>Date :</strong> ' + IPM.escHtml(data.date) + '</p>';
                        html += '<p><strong>Total :</strong> ' + IPM.escHtml(data.total) + '</p>';
                        html += '</div>';

                        // Timeline
                        html += '<div class="ipm-tracking-timeline">';
                        var step = 1;
                        data.timeline.forEach(function(item) {
                            var cls = '';
                            if (item.current) cls = ' current';
                            else if (item.active) cls = ' active';

                            html += '<div class="ipm-timeline-step' + cls + '">';
                            html += '<div class="ipm-timeline-dot"';
                            if (item.color) html += ' style="background:' + item.color + '"';
                            html += '>' + step + '</div>';
                            html += '<span class="ipm-timeline-label">' + IPM.escHtml(item.label) + '</span>';
                            html += '</div>';
                            step++;
                        });
                        html += '</div>';

                        $('#ipm-tracking-timeline').html(html);
                        $result.show();
                    } else {
                        $result.hide();
                        $msg.removeClass('ipm-message-success').addClass('ipm-message-error')
                            .html(response.data.message).show();
                    }
                }).fail(function() {
                    $msg.addClass('ipm-message-error').html(ipmPublic.messages.error).show();
                }).always(function() {
                    $btn.prop('disabled', false).text('Suivre ma commande');
                });
            });
        },

        // Ajout au panier
        bindAddToCart: function() {
            $('#ipm-add-to-cart').on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $form = $('#ipm-product-options-form');

                $btn.prop('disabled', true).html('<span class="ipm-spinner"></span> Ajout en cours...');

                var data = $form.serialize();
                data += '&action=ipm_add_to_cart';

                $.post(ipmPublic.ajaxUrl, data, function(response) {
                    if (response.success) {
                        $btn.html('Ajouté au panier !').addClass('ipm-btn-success');
                        setTimeout(function() {
                            if (response.data.cart_url) {
                                window.location.href = response.data.cart_url;
                            } else {
                                $btn.prop('disabled', false).html('Commander maintenant').removeClass('ipm-btn-success');
                            }
                        }, 1500);
                    } else {
                        alert(response.data.message || ipmPublic.messages.error);
                        $btn.prop('disabled', false).html('Commander maintenant');
                    }
                }).fail(function() {
                    alert(ipmPublic.messages.error);
                    $btn.prop('disabled', false).html('Commander maintenant');
                });
            });
        },

        // Galerie
        bindGallery: function() {
            $('.ipm-gallery-thumb').on('click', function() {
                var fullUrl = $(this).data('full');
                if (fullUrl) {
                    $('.ipm-gallery-main img').attr('src', fullUrl);
                    $('.ipm-gallery-thumb').removeClass('active');
                    $(this).addClass('active');
                }
            });
        },

        // Drop zones
        bindDropZones: function() {
            $('.ipm-file-drop-zone').each(function() {
                var $zone = $(this);

                $zone.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    $zone.addClass('dragover');
                }).on('dragleave drop', function(e) {
                    e.preventDefault();
                    $zone.removeClass('dragover');
                });
            });
        },

        // Options dépendantes
        bindDependentOptions: function() {
            $('[data-depends]').each(function() {
                var $el = $(this);
                var depends = $el.data('depends');

                $.each(depends, function(field, value) {
                    $('[name="' + field + '"]').on('change', function() {
                        if ($(this).val() === value) {
                            $el.slideDown();
                        } else {
                            $el.slideUp();
                        }
                    });
                });
            });
        },

        // Utilitaires
        formatPrice: function(price) {
            var num = parseFloat(price) || 0;
            return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' MAD';
        },

        formatFileSize: function(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' Mo';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' Ko';
            return bytes + ' octets';
        },

        escHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        IPM.init();
    });

})(jQuery);
