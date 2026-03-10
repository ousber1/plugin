<?php
/**
 * Shortcodes du plugin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Shortcodes {

    /**
     * Initialiser les shortcodes
     */
    public static function init() {
        add_shortcode( 'ipm_shop', array( __CLASS__, 'shop' ) );
        add_shortcode( 'ipm_product', array( __CLASS__, 'single_product' ) );
        add_shortcode( 'ipm_price_calculator', array( __CLASS__, 'price_calculator' ) );
        add_shortcode( 'ipm_quote_form', array( __CLASS__, 'quote_form' ) );
        add_shortcode( 'ipm_file_upload', array( __CLASS__, 'file_upload' ) );
        add_shortcode( 'ipm_order_tracking', array( __CLASS__, 'order_tracking' ) );
        add_shortcode( 'ipm_whatsapp_button', array( __CLASS__, 'whatsapp_button' ) );
    }

    /**
     * Shortcode : Boutique impression [ipm_shop]
     */
    public static function shop( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
            'columns'  => 3,
            'limit'    => 12,
        ), $atts );

        $args = array(
            'post_type'      => 'ipm_product',
            'posts_per_page' => absint( $atts['limit'] ),
            'post_status'    => 'publish',
        );

        if ( $atts['category'] ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'ipm_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $atts['category'] ),
                ),
            );
        }

        // Catégories pour le filtre
        $categories = get_terms( array(
            'taxonomy'   => 'ipm_category',
            'hide_empty' => false,
        ) );

        $products = new WP_Query( $args );

        ob_start();
        ?>
        <div class="ipm-shop">
            <div class="ipm-shop-header">
                <h2>Nos services d'impression</h2>
                <p>Livraison partout au Maroc • Paiement à la livraison disponible</p>
            </div>

            <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
            <div class="ipm-categories-filter">
                <button class="ipm-filter-btn active" data-category="all">Tous</button>
                <?php foreach ( $categories as $cat ) : ?>
                    <button class="ipm-filter-btn" data-category="<?php echo esc_attr( $cat->slug ); ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="ipm-products-grid" style="--ipm-columns: <?php echo absint( $atts['columns'] ); ?>">
                <?php if ( $products->have_posts() ) : ?>
                    <?php while ( $products->have_posts() ) : $products->the_post(); ?>
                        <?php
                        $product    = new IPM_Product( get_the_ID() );
                        $base_price = $product->get_base_price();
                        $delay      = $product->get_production_delay();
                        $terms      = get_the_terms( get_the_ID(), 'ipm_category' );
                        $cat_slugs  = $terms ? wp_list_pluck( $terms, 'slug' ) : array();
                        ?>
                        <div class="ipm-product-card" data-categories="<?php echo esc_attr( implode( ',', $cat_slugs ) ); ?>">
                            <div class="ipm-product-image">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium' ); ?>
                                <?php else : ?>
                                    <div class="ipm-product-placeholder">
                                        <svg viewBox="0 0 24 24" width="48" height="48" fill="#ccc">
                                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 19V5h14v14H5zm4-5.86l2.14 2.58 3-3.86L18 17H6l3-3.86z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $terms ) : ?>
                                    <span class="ipm-product-badge"><?php echo esc_html( $terms[0]->name ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ipm-product-info">
                                <h3 class="ipm-product-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>
                                <?php if ( get_the_excerpt() ) : ?>
                                    <p class="ipm-product-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 15 ) ); ?></p>
                                <?php endif; ?>
                                <div class="ipm-product-meta">
                                    <span class="ipm-product-price">
                                        À partir de <strong><?php echo esc_html( IPM_Price_Calculator::format_price( $base_price ) ); ?></strong>
                                    </span>
                                    <span class="ipm-product-delay">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.2 3.2.8-1.3-4.5-2.7V7z"/></svg>
                                        <?php echo esc_html( $delay ); ?>
                                    </span>
                                </div>
                                <div class="ipm-product-actions">
                                    <a href="<?php the_permalink(); ?>" class="ipm-btn ipm-btn-primary">Commander</a>
                                    <?php echo IPM_WhatsApp::get_product_button( get_the_ID() ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <p class="ipm-no-products">Aucun produit trouvé.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Produit unique [ipm_product id="123"]
     */
    public static function single_product( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        $id   = absint( $atts['id'] );

        if ( ! $id ) {
            $id = get_the_ID();
        }

        $product    = new IPM_Product( $id );
        $post       = get_post( $id );
        $base_price = $product->get_base_price();
        $options    = $product->get_options();
        $delay      = $product->get_production_delay();
        $gallery    = $product->get_gallery();
        $all_opts   = IPM_Options::get_predefined_options();

        ob_start();
        ?>
        <div class="ipm-single-product" data-product-id="<?php echo esc_attr( $id ); ?>">
            <div class="ipm-product-gallery">
                <div class="ipm-gallery-main">
                    <?php if ( has_post_thumbnail( $id ) ) : ?>
                        <?php echo get_the_post_thumbnail( $id, 'large' ); ?>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $gallery ) ) : ?>
                <div class="ipm-gallery-thumbs">
                    <?php foreach ( $gallery as $img_id ) : ?>
                        <div class="ipm-gallery-thumb" data-full="<?php echo esc_url( wp_get_attachment_url( $img_id ) ); ?>">
                            <?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="ipm-product-details">
                <h1 class="ipm-product-title"><?php echo esc_html( $post->post_title ); ?></h1>

                <div class="ipm-product-price-display">
                    <span class="ipm-price-label">À partir de</span>
                    <span class="ipm-price-value" id="ipm-dynamic-price"><?php echo esc_html( IPM_Price_Calculator::format_price( $base_price ) ); ?></span>
                </div>

                <div class="ipm-product-delay-info">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.2 3.2.8-1.3-4.5-2.7V7z"/></svg>
                    Délai de production : <strong><?php echo esc_html( $delay ); ?></strong>
                </div>

                <?php if ( $post->post_content ) : ?>
                    <div class="ipm-product-description">
                        <?php echo wp_kses_post( wpautop( $post->post_content ) ); ?>
                    </div>
                <?php endif; ?>

                <form class="ipm-options-form" id="ipm-product-options-form">
                    <input type="hidden" name="product_id" value="<?php echo esc_attr( $id ); ?>">
                    <input type="hidden" name="action" value="ipm_calculate_price">
                    <?php wp_nonce_field( 'ipm_calculate_price', 'ipm_nonce' ); ?>

                    <h3>Choisissez vos options d'impression</h3>

                    <?php foreach ( $options as $opt_key ) :
                        if ( ! isset( $all_opts[ $opt_key ] ) ) continue;
                        $opt = $all_opts[ $opt_key ];
                    ?>
                        <div class="ipm-option-group" id="ipm-option-<?php echo esc_attr( $opt_key ); ?>"
                             <?php if ( ! empty( $opt['depends'] ) ) : ?>
                                 data-depends="<?php echo esc_attr( wp_json_encode( $opt['depends'] ) ); ?>"
                                 style="display:none"
                             <?php endif; ?>>
                            <label class="ipm-option-label">
                                <?php echo esc_html( $opt['label'] ); ?>
                                <?php if ( ! empty( $opt['required'] ) ) : ?>
                                    <span class="ipm-required">*</span>
                                <?php endif; ?>
                            </label>

                            <?php if ( 'select' === $opt['type'] ) : ?>
                                <select name="<?php echo esc_attr( $opt_key ); ?>" class="ipm-option-input"
                                    <?php echo ! empty( $opt['required'] ) ? 'required' : ''; ?>>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ( $opt['choices'] as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ( 'radio' === $opt['type'] ) : ?>
                                <div class="ipm-radio-group">
                                    <?php foreach ( $opt['choices'] as $val => $label ) : ?>
                                        <label class="ipm-radio-option">
                                            <input type="radio" name="<?php echo esc_attr( $opt_key ); ?>"
                                                   value="<?php echo esc_attr( $val ); ?>" class="ipm-option-input"
                                                <?php echo ! empty( $opt['required'] ) ? 'required' : ''; ?>>
                                            <span><?php echo esc_html( $label ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php elseif ( 'checkbox' === $opt['type'] ) : ?>
                                <label class="ipm-checkbox-option">
                                    <input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>"
                                           value="1" class="ipm-option-input">
                                    <span><?php echo esc_html( $opt['label'] ); ?>
                                        <?php if ( ! empty( $opt['price'] ) ) : ?>
                                            (+<?php
                                                echo 'fixed' === $opt['price']['type']
                                                    ? esc_html( IPM_Price_Calculator::format_price( $opt['price']['value'] ) )
                                                    : esc_html( $opt['price']['value'] . '%' );
                                            ?>)
                                        <?php endif; ?>
                                    </span>
                                </label>

                            <?php elseif ( 'checkbox_group' === $opt['type'] ) : ?>
                                <div class="ipm-checkbox-group">
                                    <?php foreach ( $opt['choices'] as $val => $label ) : ?>
                                        <label class="ipm-checkbox-option">
                                            <input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[]"
                                                   value="<?php echo esc_attr( $val ); ?>" class="ipm-option-input">
                                            <span><?php echo esc_html( $label ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php elseif ( 'dimensions' === $opt['type'] ) : ?>
                                <div class="ipm-dimensions-input">
                                    <input type="number" name="width" class="ipm-option-input" placeholder="Largeur (mm)" min="1">
                                    <span class="ipm-dim-sep">×</span>
                                    <input type="number" name="height" class="ipm-option-input" placeholder="Hauteur (mm)" min="1">
                                    <span class="ipm-dim-unit">mm</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Quantité personnalisée -->
                    <div class="ipm-option-group" id="ipm-custom-quantity" style="display:none">
                        <label class="ipm-option-label">Quantité personnalisée</label>
                        <input type="number" name="custom_quantity" class="ipm-option-input" min="1" placeholder="Entrez la quantité">
                    </div>

                    <!-- Résumé du prix -->
                    <div class="ipm-price-summary" id="ipm-price-summary">
                        <div class="ipm-price-breakdown" id="ipm-price-breakdown"></div>
                        <div class="ipm-price-total">
                            <span>Total :</span>
                            <strong id="ipm-total-price"><?php echo esc_html( IPM_Price_Calculator::format_price( $base_price ) ); ?></strong>
                        </div>
                    </div>

                    <div class="ipm-product-form-actions">
                        <?php if ( Imprimerie_Pro_Maroc::is_woocommerce_active() ) : ?>
                            <button type="submit" class="ipm-btn ipm-btn-primary ipm-btn-large" id="ipm-add-to-cart">
                                Commander maintenant
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'demande-de-devis' ) ) ); ?>"
                           class="ipm-btn ipm-btn-secondary">
                            Demander un devis
                        </a>
                    </div>
                </form>

                <div class="ipm-product-whatsapp">
                    <?php echo IPM_WhatsApp::get_product_button( $id ); ?>
                </div>

                <div class="ipm-product-guarantees">
                    <div class="ipm-guarantee">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="#4CAF50"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                        <span>Paiement sécurisé</span>
                    </div>
                    <div class="ipm-guarantee">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="#2196F3"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/></svg>
                        <span>Livraison partout au Maroc</span>
                    </div>
                    <div class="ipm-guarantee">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="#FF9800"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Paiement à la livraison</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Calculateur de prix [ipm_price_calculator]
     */
    public static function price_calculator( $atts ) {
        $products = IPM_Product::get_all();

        ob_start();
        ?>
        <div class="ipm-calculator">
            <h2>Calculateur de prix</h2>
            <p>Estimez le coût de votre impression en quelques clics.</p>

            <form id="ipm-calculator-form" class="ipm-calculator-form">
                <input type="hidden" name="action" value="ipm_calculate_price">
                <?php wp_nonce_field( 'ipm_calculate_price', 'ipm_nonce' ); ?>

                <div class="ipm-calc-field">
                    <label>Type de produit <span class="ipm-required">*</span></label>
                    <select name="product_id" id="ipm-calc-product" required>
                        <option value="">-- Choisir un produit --</option>
                        <?php foreach ( $products as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="ipm-calc-options" class="ipm-calc-options"></div>

                <div class="ipm-calc-result" id="ipm-calc-result" style="display:none">
                    <h3>Estimation de prix</h3>
                    <div id="ipm-calc-breakdown"></div>
                    <div class="ipm-calc-total">
                        <span>Total estimé :</span>
                        <strong id="ipm-calc-total-price">0,00 MAD</strong>
                    </div>
                    <p class="ipm-calc-note">* Prix indicatif. Le prix final peut varier selon les spécifications exactes.</p>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Formulaire de devis [ipm_quote_form]
     */
    public static function quote_form( $atts ) {
        $categories = get_terms( array(
            'taxonomy'   => 'ipm_category',
            'hide_empty' => false,
        ) );

        $whatsapp_number = Imprimerie_Pro_Maroc::get_option( 'whatsapp_number' );

        ob_start();
        ?>
        <div class="ipm-quote-form-wrapper">
            <h2>Demander un devis gratuit</h2>
            <p>Remplissez le formulaire ci-dessous et nous vous répondrons dans les plus brefs délais.</p>

            <div id="ipm-quote-message" class="ipm-message" style="display:none"></div>

            <form id="ipm-quote-form" class="ipm-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ipm_submit_quote">
                <?php wp_nonce_field( 'ipm_submit_quote', 'ipm_quote_nonce' ); ?>

                <div class="ipm-form-row">
                    <div class="ipm-form-group">
                        <label for="ipm-q-firstname">Prénom <span class="ipm-required">*</span></label>
                        <input type="text" id="ipm-q-firstname" name="first_name" required>
                    </div>
                    <div class="ipm-form-group">
                        <label for="ipm-q-lastname">Nom <span class="ipm-required">*</span></label>
                        <input type="text" id="ipm-q-lastname" name="last_name" required>
                    </div>
                </div>

                <div class="ipm-form-row">
                    <div class="ipm-form-group">
                        <label for="ipm-q-phone">Téléphone <span class="ipm-required">*</span></label>
                        <input type="tel" id="ipm-q-phone" name="phone" required placeholder="+212 6XX XXX XXX">
                    </div>
                    <div class="ipm-form-group">
                        <label for="ipm-q-email">Email <span class="ipm-required">*</span></label>
                        <input type="email" id="ipm-q-email" name="email" required>
                    </div>
                </div>

                <div class="ipm-form-row">
                    <div class="ipm-form-group">
                        <label for="ipm-q-city">Ville</label>
                        <input type="text" id="ipm-q-city" name="city">
                    </div>
                    <div class="ipm-form-group">
                        <label for="ipm-q-company">Entreprise</label>
                        <input type="text" id="ipm-q-company" name="company">
                    </div>
                </div>

                <div class="ipm-form-group">
                    <label for="ipm-q-type">Type d'impression <span class="ipm-required">*</span></label>
                    <select id="ipm-q-type" name="print_type" required>
                        <option value="">-- Sélectionner --</option>
                        <?php if ( ! is_wp_error( $categories ) ) : ?>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->name ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <option value="autre">Autre</option>
                    </select>
                </div>

                <div class="ipm-form-row">
                    <div class="ipm-form-group">
                        <label for="ipm-q-quantity">Quantité</label>
                        <input type="number" id="ipm-q-quantity" name="quantity" min="1">
                    </div>
                    <div class="ipm-form-group">
                        <label for="ipm-q-dimensions">Dimensions</label>
                        <input type="text" id="ipm-q-dimensions" name="dimensions" placeholder="ex: 210 x 297 mm">
                    </div>
                </div>

                <div class="ipm-form-group">
                    <label for="ipm-q-description">Description du besoin</label>
                    <textarea id="ipm-q-description" name="description" rows="4" placeholder="Décrivez votre projet d'impression..."></textarea>
                </div>

                <div class="ipm-form-group">
                    <label for="ipm-q-date">Date souhaitée</label>
                    <input type="date" id="ipm-q-date" name="desired_date">
                </div>

                <div class="ipm-form-group">
                    <label for="ipm-q-file">Fichier (optionnel)</label>
                    <div class="ipm-file-drop-zone" id="ipm-quote-dropzone">
                        <input type="file" id="ipm-q-file" name="quote_file"
                               accept=".pdf,.png,.jpg,.jpeg,.ai,.psd,.svg,.zip">
                        <div class="ipm-drop-text">
                            <svg viewBox="0 0 24 24" width="32" height="32" fill="#999"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                            <p>Glissez votre fichier ici ou <span>parcourir</span></p>
                            <small><?php echo esc_html( Imprimerie_Pro_Maroc::get_option( 'upload_help_text' ) ); ?></small>
                        </div>
                    </div>
                </div>

                <div class="ipm-form-actions">
                    <button type="submit" class="ipm-btn ipm-btn-primary ipm-btn-large">
                        Demander un devis
                    </button>

                    <?php if ( $whatsapp_number ) : ?>
                        <a href="<?php echo esc_url( IPM_WhatsApp::get_whatsapp_url( $whatsapp_number, 'Bonjour, je souhaite demander un devis.' ) ); ?>"
                           class="ipm-btn ipm-btn-whatsapp" target="_blank" rel="noopener noreferrer">
                            Demander via WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Upload de fichier [ipm_file_upload]
     */
    public static function file_upload( $atts ) {
        $atts = shortcode_atts( array(
            'order_id' => 0,
        ), $atts );

        ob_start();
        ?>
        <div class="ipm-file-upload-wrapper">
            <h2>Téléverser votre fichier</h2>
            <p>Envoyez-nous votre fichier d'impression. Vous pouvez aussi l'envoyer après avoir passé votre commande.</p>

            <div id="ipm-upload-message" class="ipm-message" style="display:none"></div>

            <form id="ipm-file-upload-form" class="ipm-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ipm_upload_file">
                <?php wp_nonce_field( 'ipm_upload_file', 'ipm_upload_nonce' ); ?>

                <?php if ( $atts['order_id'] ) : ?>
                    <input type="hidden" name="order_id" value="<?php echo absint( $atts['order_id'] ); ?>">
                <?php else : ?>
                    <div class="ipm-form-group">
                        <label for="ipm-upload-order">Numéro de commande (optionnel)</label>
                        <input type="text" id="ipm-upload-order" name="order_number" placeholder="ex: 12345">
                    </div>
                <?php endif; ?>

                <div class="ipm-form-group">
                    <label>Fichier <span class="ipm-required">*</span></label>
                    <div class="ipm-file-drop-zone" id="ipm-upload-dropzone">
                        <input type="file" id="ipm-upload-file" name="print_file" required
                               accept=".pdf,.png,.jpg,.jpeg,.ai,.psd,.svg,.zip">
                        <div class="ipm-drop-text">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="#999"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                            <p>Glissez votre fichier ici ou <span>parcourir</span></p>
                            <small><?php echo esc_html( Imprimerie_Pro_Maroc::get_option( 'upload_help_text' ) ); ?></small>
                        </div>
                    </div>
                    <div id="ipm-file-preview" class="ipm-file-preview" style="display:none"></div>
                </div>

                <div class="ipm-form-group">
                    <label for="ipm-upload-notes">Notes (optionnel)</label>
                    <textarea id="ipm-upload-notes" name="notes" rows="3" placeholder="Instructions spéciales pour l'impression..."></textarea>
                </div>

                <button type="submit" class="ipm-btn ipm-btn-primary ipm-btn-large">
                    Téléverser le fichier
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Suivi de commande [ipm_order_tracking]
     */
    public static function order_tracking( $atts ) {
        ob_start();
        ?>
        <div class="ipm-order-tracking">
            <h2>Suivi de commande</h2>
            <p>Entrez votre numéro de commande et votre email pour suivre l'avancement.</p>

            <div id="ipm-tracking-message" class="ipm-message" style="display:none"></div>

            <form id="ipm-tracking-form" class="ipm-form">
                <input type="hidden" name="action" value="ipm_track_order">
                <?php wp_nonce_field( 'ipm_track_order', 'ipm_tracking_nonce' ); ?>

                <div class="ipm-form-row">
                    <div class="ipm-form-group">
                        <label for="ipm-track-order">Numéro de commande <span class="ipm-required">*</span></label>
                        <input type="text" id="ipm-track-order" name="order_number" required placeholder="ex: 12345">
                    </div>
                    <div class="ipm-form-group">
                        <label for="ipm-track-email">Email <span class="ipm-required">*</span></label>
                        <input type="email" id="ipm-track-email" name="email" required>
                    </div>
                </div>

                <button type="submit" class="ipm-btn ipm-btn-primary">Suivre ma commande</button>
            </form>

            <div id="ipm-tracking-result" class="ipm-tracking-result" style="display:none">
                <h3>Statut de votre commande</h3>
                <div id="ipm-tracking-timeline" class="ipm-tracking-timeline"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode : Bouton WhatsApp [ipm_whatsapp_button]
     */
    public static function whatsapp_button( $atts ) {
        $atts = shortcode_atts( array(
            'text'    => 'Contactez-nous sur WhatsApp',
            'message' => '',
        ), $atts );

        $number = Imprimerie_Pro_Maroc::get_option( 'whatsapp_number' );
        if ( empty( $number ) ) {
            return '';
        }

        $message = $atts['message'] ?: Imprimerie_Pro_Maroc::get_option( 'whatsapp_message' );
        $url = IPM_WhatsApp::get_whatsapp_url( $number, $message );

        return sprintf(
            '<a href="%s" class="ipm-btn ipm-btn-whatsapp" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $url ),
            esc_html( $atts['text'] )
        );
    }
}
