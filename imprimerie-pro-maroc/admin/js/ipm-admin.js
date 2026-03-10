/**
 * Imprimerie Pro Maroc — Scripts admin
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // === Onglets réglages ===
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.ipm-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // === Galerie images ===
        $('#ipm-add-gallery-images').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Sélectionner des images',
                button: { text: 'Ajouter à la galerie' },
                multiple: true
            });

            frame.on('select', function() {
                var attachments = frame.state().get('selection').toJSON();
                var $container = $('#ipm-gallery-container');

                attachments.forEach(function(att) {
                    var thumbUrl = att.sizes && att.sizes.thumbnail
                        ? att.sizes.thumbnail.url
                        : att.url;

                    $container.append(
                        '<div class="ipm-gallery-item" data-id="' + att.id + '">' +
                        '<img src="' + thumbUrl + '">' +
                        '<button type="button" class="ipm-remove-image">&times;</button>' +
                        '<input type="hidden" name="ipm_gallery[]" value="' + att.id + '">' +
                        '</div>'
                    );
                });
            });

            frame.open();
        });

        $(document).on('click', '.ipm-remove-image', function() {
            $(this).closest('.ipm-gallery-item').remove();
        });

        // === Statut devis ===
        $('.ipm-quote-status-select').on('change', function() {
            var $select = $(this);
            var quoteId = $select.data('quote-id');
            var status = $select.val();

            $.post(ipmAdmin.ajaxUrl, {
                action: 'ipm_update_quote_status',
                nonce: ipmAdmin.nonce,
                quote_id: quoteId,
                status: status
            }, function(response) {
                if (response.success) {
                    $select.css('background', '#e8f5e9');
                    setTimeout(function() { $select.css('background', ''); }, 1500);
                } else {
                    alert('Erreur: ' + (response.data.message || 'Erreur inconnue'));
                }
            });
        });

        // === Convertir devis en commande ===
        $('.ipm-convert-quote').on('click', function() {
            var quoteId = $(this).data('quote-id');

            if (!confirm('Convertir ce devis en commande WooCommerce ?')) return;

            $.post(ipmAdmin.ajaxUrl, {
                action: 'ipm_convert_quote',
                nonce: ipmAdmin.nonce,
                quote_id: quoteId
            }, function(response) {
                if (response.success) {
                    alert('Commande créée avec succès !');
                    if (response.data.edit_url) {
                        window.location.href = response.data.edit_url;
                    }
                } else {
                    alert('Erreur: ' + (response.data.message || 'Erreur inconnue'));
                }
            });
        });

        // === Statut fichier ===
        $('.ipm-file-status-select').on('change', function() {
            var $select = $(this);
            var fileId = $select.data('file-id');
            var status = $select.val();

            $.post(ipmAdmin.ajaxUrl, {
                action: 'ipm_update_file_status',
                nonce: ipmAdmin.nonce,
                file_id: fileId,
                status: status
            }, function(response) {
                if (response.success) {
                    $select.css('background', '#e8f5e9');
                    setTimeout(function() { $select.css('background', ''); }, 1500);
                }
            });
        });

        // === Zones de livraison ===
        $('.ipm-save-zone').on('click', function() {
            var $row = $(this).closest('tr');
            var data = {
                action: 'ipm_save_shipping_zone',
                nonce: ipmAdmin.nonce,
                zone_id: $row.data('id') || 0
            };

            $row.find('.ipm-zone-field').each(function() {
                var field = $(this).data('field');
                if ($(this).is(':checkbox')) {
                    data[field] = $(this).is(':checked') ? 1 : 0;
                } else {
                    data[field] = $(this).val();
                }
            });

            $.post(ipmAdmin.ajaxUrl, data, function(response) {
                if (response.success) {
                    $row.css('background', '#e8f5e9');
                    setTimeout(function() { $row.css('background', ''); }, 1500);
                } else {
                    alert('Erreur: ' + (response.data.message || 'Erreur'));
                }
            });
        });

        $('.ipm-delete-zone').on('click', function() {
            var $row = $(this).closest('tr');
            var zoneId = $row.data('id');

            if (!zoneId || !confirm('Supprimer cette zone de livraison ?')) return;

            $.post(ipmAdmin.ajaxUrl, {
                action: 'ipm_delete_shipping_zone',
                nonce: ipmAdmin.nonce,
                zone_id: zoneId
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(function() { $(this).remove(); });
                }
            });
        });

        $('#ipm-add-zone').on('click', function() {
            var html = '<tr data-id="">' +
                '<td><input type="text" class="ipm-zone-field" data-field="zone_name" value="" placeholder="Nom de la zone"></td>' +
                '<td><input type="text" class="ipm-zone-field" data-field="cities" value="" placeholder="Ville1,Ville2"></td>' +
                '<td><input type="number" class="ipm-zone-field" data-field="standard_price" value="0" step="0.01" min="0" style="width:80px"></td>' +
                '<td><input type="number" class="ipm-zone-field" data-field="express_price" value="0" step="0.01" min="0" style="width:80px"></td>' +
                '<td><input type="number" class="ipm-zone-field" data-field="standard_days" value="3" min="1" style="width:60px"> j</td>' +
                '<td><input type="number" class="ipm-zone-field" data-field="express_days" value="1" min="1" style="width:60px"> j</td>' +
                '<td><input type="number" class="ipm-zone-field" data-field="free_threshold" value="" step="0.01" min="0" style="width:80px"></td>' +
                '<td><input type="checkbox" class="ipm-zone-field" data-field="is_active" checked></td>' +
                '<td>' +
                '<button class="button ipm-save-zone" title="Sauvegarder"><span class="dashicons dashicons-saved"></span></button> ' +
                '<button class="button ipm-delete-zone" title="Supprimer"><span class="dashicons dashicons-trash"></span></button>' +
                '</td></tr>';

            $('#ipm-shipping-zones').append(html);
        });
    });

})(jQuery);
