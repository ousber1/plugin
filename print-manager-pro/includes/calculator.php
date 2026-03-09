<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advanced Price Calculator for Print Manager Pro.
 */
class PMP_Calculator {

    private $settings = array();

    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load cost settings from database.
     */
    private function load_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'print_cost_settings';
        $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$table}", OBJECT );

        foreach ( $rows as $row ) {
            $this->settings[ $row->setting_key ] = $row->setting_value;
        }
    }

    /**
     * Get a setting value.
     */
    private function get_setting( $key, $default = 0 ) {
        return isset( $this->settings[ $key ] ) ? floatval( $this->settings[ $key ] ) : floatval( $default );
    }

    /**
     * Calculate print price.
     *
     * @param array $params Configuration parameters.
     * @return array Price breakdown.
     */
    public function calculate( $params ) {
        $quantity   = max( 1, absint( $params['quantity'] ) );
        $sides      = isset( $params['sides'] ) ? $params['sides'] : 'recto';
        $color      = isset( $params['color'] ) ? $params['color'] : 'color';
        $finishing   = isset( $params['finishing'] ) ? (array) $params['finishing'] : array();
        $weight     = isset( $params['weight'] ) ? intval( $params['weight'] ) : 135;
        $format     = isset( $params['format'] ) ? $params['format'] : 'A4';

        // Base costs per sheet
        $paper_cost   = $this->get_setting( 'paper_cost_per_sheet', 0.05 );
        $ink_cost     = $this->get_setting( 'ink_cost_per_sheet', 0.03 );
        $machine_hourly = $this->get_setting( 'machine_cost_per_hour', 25.00 );
        $sheets_per_hour = $this->get_setting( 'sheets_per_hour', 500 );

        // Weight multiplier (heavier paper costs more)
        $weight_multiplier = 1 + ( ( $weight - 90 ) * 0.003 );
        $paper_cost *= $weight_multiplier;

        // Format multiplier
        $format_multipliers = array(
            'A6'     => 0.5,
            'A5'     => 0.7,
            'DL'     => 0.65,
            '10x15'  => 0.5,
            'A4'     => 1.0,
            '13x18'  => 0.75,
            '21x29.7'=> 1.0,
            'A3'     => 1.8,
            'Personnalisé' => 1.0,
        );
        $format_mult = isset( $format_multipliers[ $format ] ) ? $format_multipliers[ $format ] : 1.0;
        $paper_cost *= $format_mult;
        $ink_cost   *= $format_mult;

        // Color multiplier
        if ( 'color' === $color ) {
            $color_mult = $this->get_setting( 'color_multiplier', 1.5 );
            $ink_cost *= $color_mult;
        }

        // Recto/verso multiplier
        if ( 'recto_verso' === $sides ) {
            $rv_mult = $this->get_setting( 'recto_verso_multiplier', 1.8 );
            $ink_cost *= $rv_mult;
            $paper_cost *= 1.0; // Paper doesn't double
        }

        // Machine cost per sheet
        $machine_cost_per_sheet = ( $sheets_per_hour > 0 ) ? $machine_hourly / $sheets_per_hour : 0.05;
        if ( 'recto_verso' === $sides ) {
            $machine_cost_per_sheet *= 1.5; // Double pass takes more time
        }

        // Finishing costs
        $finishing_cost = 0;
        $finishing_map = array(
            'lamination'      => 'finishing_lamination',
            'uv_varnish'      => 'finishing_uv_varnish',
            'folding'         => 'finishing_folding',
            'cutting'         => 'finishing_cutting',
            'rounded_corners' => 'finishing_cutting',
            'embossing'       => 'finishing_uv_varnish',
            'hot_foil'        => 'finishing_lamination',
        );

        foreach ( $finishing as $fin ) {
            $setting_key = isset( $finishing_map[ $fin ] ) ? $finishing_map[ $fin ] : '';
            if ( $setting_key ) {
                $finishing_cost += $this->get_setting( $setting_key, 0.02 );
            }
        }
        $finishing_cost *= $format_mult;

        // Base cost per unit
        $base_cost_per_unit = $paper_cost + $ink_cost + $machine_cost_per_sheet + $finishing_cost;

        // Total base cost
        $total_base_cost = $base_cost_per_unit * $quantity;

        // Setup cost (fixed cost for any print run)
        $setup_cost = 5.00;

        // Bulk discount
        $discount_percent = 0;
        if ( $quantity >= 5000 ) {
            $discount_percent = $this->get_setting( 'bulk_discount_5000', 20 );
        } elseif ( $quantity >= 1000 ) {
            $discount_percent = $this->get_setting( 'bulk_discount_1000', 15 );
        } elseif ( $quantity >= 500 ) {
            $discount_percent = $this->get_setting( 'bulk_discount_500', 10 );
        } elseif ( $quantity >= 100 ) {
            $discount_percent = $this->get_setting( 'bulk_discount_100', 5 );
        }

        $discount_amount = $total_base_cost * ( $discount_percent / 100 );
        $discounted_cost = $total_base_cost - $discount_amount + $setup_cost;

        // Profit margin
        $margin = $this->get_setting( 'profit_margin', 40 );
        $final_price = $discounted_cost * ( 1 + $margin / 100 );

        // Minimum price
        $final_price = max( $final_price, 5.00 );

        $unit_price = $final_price / $quantity;

        return array(
            'unit_price'       => round( $unit_price, 4 ),
            'total_price'      => round( $final_price, 2 ),
            'base_cost'        => round( $base_cost_per_unit, 4 ),
            'paper_cost'       => round( $paper_cost, 4 ),
            'ink_cost'         => round( $ink_cost, 4 ),
            'machine_cost'     => round( $machine_cost_per_sheet, 4 ),
            'finishing_cost'   => round( $finishing_cost, 4 ),
            'setup_cost'       => round( $setup_cost, 2 ),
            'discount_percent' => $discount_percent,
            'discount_amount'  => round( $discount_amount, 2 ),
            'margin_percent'   => $margin,
            'quantity'         => $quantity,
        );
    }
}
