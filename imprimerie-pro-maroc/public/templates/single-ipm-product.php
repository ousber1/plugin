<?php
/**
 * Template pour un produit d'impression unique
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="ipm-single-product-page">
    <div class="ipm-container">
        <?php
        while ( have_posts() ) :
            the_post();
            echo do_shortcode( '[ipm_product id="' . get_the_ID() . '"]' );
        endwhile;
        ?>
    </div>
</div>

<style>
.ipm-single-product-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.ipm-container {
    width: 100%;
}
</style>

<?php
get_footer();
