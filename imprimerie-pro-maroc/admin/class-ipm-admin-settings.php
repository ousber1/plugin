<?php
/**
 * Page de réglages admin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin_Settings {

    /**
     * Afficher la page de réglages
     */
    public static function render() {
        // Sauvegarde
        if ( isset( $_POST['ipm_save_settings'] ) && check_admin_referer( 'ipm_save_settings' ) ) {
            self::save_settings();
            echo '<div class="notice notice-success"><p>Réglages sauvegardés avec succès.</p></div>';
        }

        $settings = get_option( 'ipm_settings', array() );
        ?>
        <div class="wrap ipm-admin-wrap">
            <h1><span class="dashicons dashicons-admin-settings"></span> Réglages — Imprimerie Pro Maroc</h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'ipm_save_settings' ); ?>

                <div class="ipm-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active" data-tab="general">Général</a>
                        <a href="#whatsapp" class="nav-tab" data-tab="whatsapp">WhatsApp</a>
                        <a href="#files" class="nav-tab" data-tab="files">Fichiers</a>
                        <a href="#emails" class="nav-tab" data-tab="emails">Emails</a>
                    </nav>

                    <!-- Général -->
                    <div class="ipm-tab-content active" id="tab-general">
                        <table class="form-table">
                            <tr>
                                <th>Devise</th>
                                <td>
                                    <input type="text" name="ipm[currency]" value="<?php echo esc_attr( $settings['currency'] ?? 'MAD' ); ?>" class="regular-text" readonly>
                                    <p class="description">Dirham marocain (MAD)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Délai par défaut</th>
                                <td><input type="text" name="ipm[default_delay]" value="<?php echo esc_attr( $settings['default_delay'] ?? '' ); ?>" class="regular-text" placeholder="3-5 jours ouvrables"></td>
                            </tr>
                            <tr>
                                <th>Prix minimum (MAD)</th>
                                <td><input type="number" name="ipm[minimum_price]" value="<?php echo esc_attr( $settings['minimum_price'] ?? 50 ); ?>" step="0.01" min="0" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Activer les devis</th>
                                <td><label><input type="checkbox" name="ipm[enable_quotes]" value="1" <?php checked( $settings['enable_quotes'] ?? true ); ?>> Permettre les demandes de devis</label></td>
                            </tr>
                            <tr>
                                <th>Activer l'upload de fichiers</th>
                                <td><label><input type="checkbox" name="ipm[enable_file_upload]" value="1" <?php checked( $settings['enable_file_upload'] ?? true ); ?>> Permettre le téléversement de fichiers</label></td>
                            </tr>
                            <tr>
                                <th>Retrait en magasin</th>
                                <td>
                                    <label><input type="checkbox" name="ipm[store_pickup]" value="1" <?php checked( $settings['store_pickup'] ?? false ); ?>> Proposer le retrait en magasin</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Adresse du magasin</th>
                                <td><textarea name="ipm[store_address]" rows="3" class="large-text"><?php echo esc_textarea( $settings['store_address'] ?? '' ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th>URL Conditions générales</th>
                                <td><input type="url" name="ipm[terms_url]" value="<?php echo esc_url( $settings['terms_url'] ?? '' ); ?>" class="large-text"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- WhatsApp -->
                    <div class="ipm-tab-content" id="tab-whatsapp">
                        <table class="form-table">
                            <tr>
                                <th>Numéro WhatsApp</th>
                                <td>
                                    <input type="text" name="ipm[whatsapp_number]" value="<?php echo esc_attr( $settings['whatsapp_number'] ?? '' ); ?>" class="regular-text" placeholder="+212 6XX XXX XXX">
                                    <p class="description">Format international avec indicatif pays (ex: +212600000000)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Message pré-rempli</th>
                                <td>
                                    <textarea name="ipm[whatsapp_message]" rows="3" class="large-text"><?php echo esc_textarea( $settings['whatsapp_message'] ?? '' ); ?></textarea>
                                    <p class="description">Variables disponibles : {product} pour le nom du produit</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Fichiers -->
                    <div class="ipm-tab-content" id="tab-files">
                        <table class="form-table">
                            <tr>
                                <th>Taille maximale (Mo)</th>
                                <td><input type="number" name="ipm[max_file_size]" value="<?php echo esc_attr( $settings['max_file_size'] ?? 50 ); ?>" min="1" max="500" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Types de fichiers autorisés</th>
                                <td>
                                    <input type="text" name="ipm[allowed_file_types]" value="<?php echo esc_attr( $settings['allowed_file_types'] ?? 'pdf,png,jpg,jpeg,ai,psd,svg,zip' ); ?>" class="large-text">
                                    <p class="description">Séparés par des virgules (ex: pdf,png,jpg,ai,psd,svg,zip)</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Texte d'aide upload</th>
                                <td><textarea name="ipm[upload_help_text]" rows="2" class="large-text"><?php echo esc_textarea( $settings['upload_help_text'] ?? '' ); ?></textarea></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Emails -->
                    <div class="ipm-tab-content" id="tab-emails">
                        <table class="form-table">
                            <tr>
                                <th>Email de notification</th>
                                <td><input type="email" name="ipm[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ?? get_option( 'admin_email' ) ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Emails automatiques</th>
                                <td><label><input type="checkbox" name="ipm[auto_emails]" value="1" <?php checked( $settings['auto_emails'] ?? true ); ?>> Envoyer automatiquement les emails de notification</label></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit" name="ipm_save_settings" class="button-primary" value="Enregistrer les réglages">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Sauvegarder les réglages
     */
    private static function save_settings() {
        if ( ! isset( $_POST['ipm'] ) || ! is_array( $_POST['ipm'] ) ) {
            return;
        }

        $input    = $_POST['ipm'];
        $settings = array(
            'currency'           => 'MAD',
            'whatsapp_number'    => sanitize_text_field( $input['whatsapp_number'] ?? '' ),
            'whatsapp_message'   => sanitize_textarea_field( $input['whatsapp_message'] ?? '' ),
            'max_file_size'      => absint( $input['max_file_size'] ?? 50 ),
            'allowed_file_types' => sanitize_text_field( $input['allowed_file_types'] ?? 'pdf,png,jpg,jpeg,ai,psd,svg,zip' ),
            'default_delay'      => sanitize_text_field( $input['default_delay'] ?? '' ),
            'notification_email' => sanitize_email( $input['notification_email'] ?? get_option( 'admin_email' ) ),
            'upload_help_text'   => sanitize_textarea_field( $input['upload_help_text'] ?? '' ),
            'terms_url'          => esc_url_raw( $input['terms_url'] ?? '' ),
            'store_pickup'       => ! empty( $input['store_pickup'] ),
            'store_address'      => sanitize_textarea_field( $input['store_address'] ?? '' ),
            'minimum_price'      => (float) ( $input['minimum_price'] ?? 50 ),
            'enable_quotes'      => ! empty( $input['enable_quotes'] ),
            'enable_file_upload' => ! empty( $input['enable_file_upload'] ),
            'auto_emails'        => ! empty( $input['auto_emails'] ),
        );

        update_option( 'ipm_settings', $settings );
    }

    /**
     * Afficher la page de livraison
     */
    public static function render_shipping() {
        $zones = IPM_Shipping::get_zones();
        ?>
        <div class="wrap ipm-admin-wrap">
            <h1><span class="dashicons dashicons-car"></span> Zones de livraison — Imprimerie Pro Maroc</h1>

            <div class="ipm-shipping-manager">
                <table class="widefat striped ipm-shipping-table">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Villes</th>
                            <th>Standard (MAD)</th>
                            <th>Express (MAD)</th>
                            <th>Délai std</th>
                            <th>Délai express</th>
                            <th>Seuil gratuit</th>
                            <th>Actif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ipm-shipping-zones">
                        <?php foreach ( $zones as $zone ) : ?>
                            <tr data-id="<?php echo esc_attr( $zone['id'] ); ?>">
                                <td><input type="text" class="ipm-zone-field" data-field="zone_name" value="<?php echo esc_attr( $zone['zone_name'] ); ?>"></td>
                                <td><input type="text" class="ipm-zone-field" data-field="cities" value="<?php echo esc_attr( $zone['cities'] ); ?>"></td>
                                <td><input type="number" class="ipm-zone-field" data-field="standard_price" value="<?php echo esc_attr( $zone['standard_price'] ); ?>" step="0.01" min="0" style="width:80px"></td>
                                <td><input type="number" class="ipm-zone-field" data-field="express_price" value="<?php echo esc_attr( $zone['express_price'] ); ?>" step="0.01" min="0" style="width:80px"></td>
                                <td><input type="number" class="ipm-zone-field" data-field="standard_days" value="<?php echo esc_attr( $zone['standard_days'] ); ?>" min="1" style="width:60px"> j</td>
                                <td><input type="number" class="ipm-zone-field" data-field="express_days" value="<?php echo esc_attr( $zone['express_days'] ); ?>" min="1" style="width:60px"> j</td>
                                <td><input type="number" class="ipm-zone-field" data-field="free_threshold" value="<?php echo esc_attr( $zone['free_threshold'] ); ?>" step="0.01" min="0" style="width:80px"></td>
                                <td><input type="checkbox" class="ipm-zone-field" data-field="is_active" <?php checked( $zone['is_active'] ); ?>></td>
                                <td>
                                    <button class="button ipm-save-zone" title="Sauvegarder"><span class="dashicons dashicons-saved"></span></button>
                                    <button class="button ipm-delete-zone" title="Supprimer"><span class="dashicons dashicons-trash"></span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button class="button button-primary" id="ipm-add-zone">Ajouter une zone</button>
                </p>

                <div class="ipm-shipping-info">
                    <h3>Informations</h3>
                    <ul>
                        <li><strong>Villes :</strong> Séparez les noms de villes par des virgules.</li>
                        <li><strong>Seuil gratuit :</strong> Montant minimum de commande pour la livraison gratuite (standard).</li>
                        <li><strong>Express :</strong> Livraison rapide avec supplément.</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
