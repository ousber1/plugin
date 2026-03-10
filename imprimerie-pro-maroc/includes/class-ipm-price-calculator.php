<?php
/**
 * Calculateur de prix dynamique
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Price_Calculator {

    /**
     * Calculer le prix total
     *
     * @param int   $product_id     ID du produit
     * @param array $selected_options Options sélectionnées
     * @return array Détails du prix
     */
    public static function calculate( $product_id, $selected_options ) {
        $product    = new IPM_Product( $product_id );
        $base_price = $product->get_base_price();
        $quantity   = isset( $selected_options['quantity'] ) ? absint( $selected_options['quantity'] ) : 1;

        if ( $quantity < 1 ) {
            $quantity = 1;
        }

        // Prix unitaire de base
        $unit_price   = $base_price;
        $price_details = array(
            'base_price'      => $base_price,
            'quantity'        => $quantity,
            'options_fixed'   => 0,
            'options_percent' => 0,
            'volume_discount' => 0,
            'subtotal'        => 0,
            'total'           => 0,
            'currency'        => 'MAD',
            'breakdown'       => array(),
        );

        $price_details['breakdown'][] = array(
            'label' => 'Prix de base',
            'value' => $base_price,
        );

        // Appliquer les options à prix fixe
        $option_prices = IPM_Options::get_option_prices( $product_id );
        $all_options   = IPM_Options::get_predefined_options();

        foreach ( $selected_options as $key => $value ) {
            if ( 'quantity' === $key || empty( $value ) ) {
                continue;
            }

            $option_price = 0;

            // Vérifier le prix dans les options configurées du produit
            if ( isset( $option_prices[ $key ] ) ) {
                $config = $option_prices[ $key ];

                if ( is_array( $value ) ) {
                    // Groupe de checkboxes
                    foreach ( $value as $v ) {
                        if ( isset( $config[ $v ] ) ) {
                            $opt_conf = $config[ $v ];
                            if ( 'fixed' === $opt_conf['type'] ) {
                                $option_price += (float) $opt_conf['value'];
                                $price_details['options_fixed'] += (float) $opt_conf['value'];
                            } elseif ( 'percentage' === $opt_conf['type'] ) {
                                $pct_amount = $base_price * ( (float) $opt_conf['value'] / 100 );
                                $option_price += $pct_amount;
                                $price_details['options_percent'] += $pct_amount;
                            }
                        }
                    }
                } elseif ( isset( $config[ $value ] ) ) {
                    $opt_conf = $config[ $value ];
                    if ( 'fixed' === $opt_conf['type'] ) {
                        $option_price = (float) $opt_conf['value'];
                        $price_details['options_fixed'] += $option_price;
                    } elseif ( 'percentage' === $opt_conf['type'] ) {
                        $option_price = $base_price * ( (float) $opt_conf['value'] / 100 );
                        $price_details['options_percent'] += $option_price;
                    }
                } elseif ( isset( $config['type'] ) ) {
                    // Prix direct sur l'option
                    if ( 'fixed' === $config['type'] ) {
                        $option_price = (float) $config['value'];
                        $price_details['options_fixed'] += $option_price;
                    } elseif ( 'percentage' === $config['type'] ) {
                        $option_price = $base_price * ( (float) $config['value'] / 100 );
                        $price_details['options_percent'] += $option_price;
                    }
                }
            }
            // Prix prédéfinis
            elseif ( isset( $all_options[ $key ]['price'] ) && $value ) {
                $preset = $all_options[ $key ]['price'];
                if ( 'fixed' === $preset['type'] ) {
                    $option_price = (float) $preset['value'];
                    $price_details['options_fixed'] += $option_price;
                } elseif ( 'percentage' === $preset['type'] ) {
                    $option_price = $base_price * ( (float) $preset['value'] / 100 );
                    $price_details['options_percent'] += $option_price;
                }
            }

            if ( $option_price > 0 ) {
                $label = isset( $all_options[ $key ]['label'] ) ? $all_options[ $key ]['label'] : $key;
                $price_details['breakdown'][] = array(
                    'label' => $label,
                    'value' => $option_price,
                );
            }
        }

        // Sous-total unitaire
        $unit_total = $unit_price + $price_details['options_fixed'] + $price_details['options_percent'];

        // Appliquer la quantité
        $subtotal = $unit_total * $quantity;

        // Remise volume
        $volume_discounts = $product->get_volume_discounts();
        $discount_amount  = 0;

        foreach ( $volume_discounts as $discount ) {
            $min = (int) $discount['min_quantity'];
            $max = $discount['max_quantity'] ? (int) $discount['max_quantity'] : PHP_INT_MAX;

            if ( $quantity >= $min && $quantity <= $max ) {
                if ( 'percentage' === $discount['discount_type'] ) {
                    $discount_amount = $subtotal * ( (float) $discount['discount_value'] / 100 );
                } else {
                    $discount_amount = (float) $discount['discount_value'];
                }
                break;
            }
        }

        if ( $discount_amount > 0 ) {
            $price_details['volume_discount'] = $discount_amount;
            $price_details['breakdown'][] = array(
                'label' => 'Remise volume (-' . $quantity . ' ex.)',
                'value' => -$discount_amount,
            );
        }

        $total = $subtotal - $discount_amount;

        // Prix minimum
        $minimum = $product->get_minimum_price();
        if ( $total < $minimum ) {
            $total = $minimum;
        }

        $price_details['subtotal'] = $subtotal;
        $price_details['total']    = round( $total, 2 );

        $price_details['breakdown'][] = array(
            'label' => 'Quantité',
            'value' => 'x' . $quantity,
        );

        return $price_details;
    }

    /**
     * Formater un prix en MAD
     *
     * @param float $price Prix
     * @return string
     */
    public static function format_price( $price ) {
        return number_format( (float) $price, 2, ',', ' ' ) . ' MAD';
    }
}
