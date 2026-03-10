<?php
/**
 * Intégration WhatsApp
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_WhatsApp {

    /**
     * Afficher le bouton flottant WhatsApp
     */
    public function render_floating_button() {
        $number = Imprimerie_Pro_Maroc::get_option( 'whatsapp_number' );

        if ( empty( $number ) ) {
            return;
        }

        $message = Imprimerie_Pro_Maroc::get_option(
            'whatsapp_message',
            'Bonjour, je souhaite avoir plus d\'informations.'
        );

        $url = self::get_whatsapp_url( $number, $message );
        ?>
        <div id="ipm-whatsapp-floating" class="ipm-whatsapp-float">
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"
               title="Contactez-nous sur WhatsApp" aria-label="Contactez-nous sur WhatsApp">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="40" height="40" fill="#fff">
                    <path d="M16.004 0h-.008C7.174 0 0 7.176 0 16c0 3.5 1.129 6.742 3.047 9.375L1.054 31.25l6.094-1.953A15.908 15.908 0 0016.004 32C24.828 32 32 24.824 32 16S24.828 0 16.004 0zm9.32 22.598c-.39 1.098-1.937 2.012-3.152 2.277-.832.176-1.918.317-5.574-1.199-4.676-1.937-7.687-6.695-7.921-7.004-.227-.309-1.852-2.469-1.852-4.707 0-2.238 1.172-3.34 1.59-3.797.39-.425 1.023-.605 1.629-.605.195 0 .371.01.528.019.457.02.687.047 .988.762.375.89 1.289 3.148 1.402 3.375.117.23.234.535.086.844-.14.316-.262.457-.492.723-.23.27-.449.476-.68.766-.214.253-.453.523-.191.98.261.457 1.164 1.918 2.5 3.11 1.718 1.531 3.164 2.008 3.613 2.23.457.223.726.188.992-.113.27-.305 1.152-1.34 1.46-1.8.301-.458.609-.38 1.023-.228.418.152 2.656 1.254 3.113 1.48.457.228.762.34.875.527.117.191.117 1.09-.273 2.144z"/>
                </svg>
                <span class="ipm-whatsapp-text">Besoin d'aide ?</span>
            </a>
        </div>
        <?php
    }

    /**
     * Générer le bouton produit WhatsApp
     *
     * @param int $product_id ID du produit
     * @return string HTML du bouton
     */
    public static function get_product_button( $product_id ) {
        $number = Imprimerie_Pro_Maroc::get_option( 'whatsapp_number' );

        if ( empty( $number ) ) {
            return '';
        }

        $product = get_post( $product_id );
        if ( ! $product ) {
            return '';
        }

        $template = Imprimerie_Pro_Maroc::get_option(
            'whatsapp_message',
            'Bonjour, je suis intéressé(e) par {product}. Pouvez-vous me donner plus d\'informations ?'
        );

        $message = str_replace( '{product}', $product->post_title, $template );
        $url     = self::get_whatsapp_url( $number, $message );

        return sprintf(
            '<a href="%s" class="ipm-whatsapp-product-btn" target="_blank" rel="noopener noreferrer">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="20" height="20" fill="currentColor">
                    <path d="M16.004 0h-.008C7.174 0 0 7.176 0 16c0 3.5 1.129 6.742 3.047 9.375L1.054 31.25l6.094-1.953A15.908 15.908 0 0016.004 32C24.828 32 32 24.824 32 16S24.828 0 16.004 0zm9.32 22.598c-.39 1.098-1.937 2.012-3.152 2.277-.832.176-1.918.317-5.574-1.199-4.676-1.937-7.687-6.695-7.921-7.004-.227-.309-1.852-2.469-1.852-4.707 0-2.238 1.172-3.34 1.59-3.797.39-.425 1.023-.605 1.629-.605.195 0 .371.01.528.019.457.02.687.047.988.762.375.89 1.289 3.148 1.402 3.375.117.23.234.535.086.844-.14.316-.262.457-.492.723-.23.27-.449.476-.68.766-.214.253-.453.523-.191.98.261.457 1.164 1.918 2.5 3.11 1.718 1.531 3.164 2.008 3.613 2.23.457.223.726.188.992-.113.27-.305 1.152-1.34 1.46-1.8.301-.458.609-.38 1.023-.228.418.152 2.656 1.254 3.113 1.48.457.228.762.34.875.527.117.191.117 1.09-.273 2.144z"/>
                </svg>
                Demander via WhatsApp
            </a>',
            esc_url( $url )
        );
    }

    /**
     * Construire l'URL WhatsApp
     *
     * @param string $number  Numéro
     * @param string $message Message pré-rempli
     * @return string
     */
    public static function get_whatsapp_url( $number, $message = '' ) {
        $number = preg_replace( '/[^0-9+]/', '', $number );
        $number = ltrim( $number, '+' );

        $params = array( 'phone' => $number );
        if ( $message ) {
            $params['text'] = $message;
        }

        return 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );
    }
}
