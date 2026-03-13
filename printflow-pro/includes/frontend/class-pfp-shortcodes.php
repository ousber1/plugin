<?php
/**
 * PrintFlow Pro - Shortcodes
 *
 * Registers and renders all frontend shortcodes.
 *
 * @package PrintFlow_Pro
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PFP_Shortcodes {

    /**
     * Singleton instance.
     *
     * @var PFP_Shortcodes|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return PFP_Shortcodes
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Initialize shortcode registrations.
     *
     * @return void
     */
    public function init() {
        add_shortcode( 'pfp_pricing_calculator', array( $this, 'render_pricing_calculator' ) );
        add_shortcode( 'pfp_quote_form', array( $this, 'render_quote_form' ) );
        add_shortcode( 'pfp_order_tracking', array( $this, 'render_order_tracking' ) );
        add_shortcode( 'pfp_file_upload', array( $this, 'render_file_upload' ) );
    }

    /**
     * Load a template file from the views directory.
     *
     * Allows theme overrides via printflow-pro/ directory in the active theme.
     *
     * @param string $template_name Template file name (without path).
     * @param array  $args          Variables to pass to the template.
     * @return string Rendered HTML.
     */
    private function load_template( $template_name, $args = array() ) {
        // Allow themes to override templates.
        $theme_template = locate_template( 'printflow-pro/' . $template_name );

        if ( $theme_template ) {
            $template_path = $theme_template;
        } else {
            $template_path = PFP_PLUGIN_DIR . 'includes/frontend/views/' . $template_name;
        }

        if ( ! file_exists( $template_path ) ) {
            return '';
        }

        // Extract args so they are available in template scope.
        if ( ! empty( $args ) ) {
            extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Render the interactive pricing calculator shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_pricing_calculator( $atts ) {
        $atts = shortcode_atts(
            array(
                'product_id' => 0,
                'show_title' => 'yes',
            ),
            $atts,
            'pfp_pricing_calculator'
        );

        return $this->load_template( 'pricing-calculator.php', array(
            'product_id' => absint( $atts['product_id'] ),
            'show_title' => $atts['show_title'] === 'yes',
        ) );
    }

    /**
     * Render the quote request form shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_quote_form( $atts ) {
        $atts = shortcode_atts(
            array(
                'product_type' => '',
            ),
            $atts,
            'pfp_quote_form'
        );

        return $this->load_template( 'quote-form.php', array(
            'preset_product_type' => sanitize_text_field( $atts['product_type'] ),
        ) );
    }

    /**
     * Render the order tracking shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_order_tracking( $atts ) {
        $atts = shortcode_atts(
            array(),
            $atts,
            'pfp_order_tracking'
        );

        return $this->load_template( 'order-tracking.php' );
    }

    /**
     * Render the file upload area shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_file_upload( $atts ) {
        $atts = shortcode_atts(
            array(
                'max_files' => 5,
            ),
            $atts,
            'pfp_file_upload'
        );

        return $this->load_template( 'file-upload.php', array(
            'max_files' => absint( $atts['max_files'] ),
        ) );
    }
}
