<?php
/**
 * Enregistrement des Custom Post Types et Taxonomies
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Post_Types {

    /**
     * Enregistrer tous les CPT et taxonomies
     */
    public static function register() {
        self::register_print_product();
        self::register_print_quote();
        self::register_taxonomies();
    }

    /**
     * CPT : Produit d'impression
     */
    private static function register_print_product() {
        $labels = array(
            'name'               => 'Produits d\'impression',
            'singular_name'      => 'Produit d\'impression',
            'menu_name'          => 'Produits Impression',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter un produit d\'impression',
            'edit_item'          => 'Modifier le produit',
            'new_item'           => 'Nouveau produit',
            'view_item'          => 'Voir le produit',
            'search_items'       => 'Rechercher un produit',
            'not_found'          => 'Aucun produit trouvé',
            'not_found_in_trash' => 'Aucun produit dans la corbeille',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // Géré par le menu admin custom
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'produit-impression' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'ipm_product', $args );
    }

    /**
     * CPT : Devis
     */
    private static function register_print_quote() {
        $labels = array(
            'name'               => 'Devis',
            'singular_name'      => 'Devis',
            'menu_name'          => 'Devis',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Nouveau devis',
            'edit_item'          => 'Modifier le devis',
            'new_item'           => 'Nouveau devis',
            'view_item'          => 'Voir le devis',
            'search_items'       => 'Rechercher un devis',
            'not_found'          => 'Aucun devis trouvé',
            'not_found_in_trash' => 'Aucun devis dans la corbeille',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'custom-fields' ),
        );

        register_post_type( 'ipm_quote', $args );
    }

    /**
     * Taxonomies
     */
    private static function register_taxonomies() {
        // Catégorie de produit d'impression
        $labels = array(
            'name'              => 'Catégories d\'impression',
            'singular_name'     => 'Catégorie',
            'search_items'      => 'Rechercher une catégorie',
            'all_items'         => 'Toutes les catégories',
            'parent_item'       => 'Catégorie parente',
            'parent_item_colon' => 'Catégorie parente :',
            'edit_item'         => 'Modifier la catégorie',
            'update_item'       => 'Mettre à jour',
            'add_new_item'      => 'Ajouter une catégorie',
            'new_item_name'     => 'Nom de la catégorie',
            'menu_name'         => 'Catégories',
        );

        register_taxonomy( 'ipm_category', array( 'ipm_product' ), array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'categorie-impression' ),
            'show_in_rest'      => true,
        ) );

        // Insérer les catégories par défaut
        self::insert_default_categories();
    }

    /**
     * Insérer les catégories par défaut
     */
    private static function insert_default_categories() {
        $categories = array(
            'cartes-de-visite'      => 'Cartes de visite',
            'flyers'                => 'Flyers',
            'brochures'             => 'Brochures',
            'affiches'              => 'Affiches',
            'stickers'              => 'Stickers',
            'baches'                => 'Bâches',
            'roll-up'               => 'Roll-up',
            't-shirts-personnalises' => 'T-shirts personnalisés',
            'mugs-personnalises'    => 'Mugs personnalisés',
            'invitations'           => 'Invitations',
            'papier-en-tete'        => 'Papier en-tête',
            'enveloppes'            => 'Enveloppes',
            'produits-sur-mesure'   => 'Produits sur mesure',
        );

        foreach ( $categories as $slug => $name ) {
            if ( ! term_exists( $slug, 'ipm_category' ) ) {
                wp_insert_term( $name, 'ipm_category', array( 'slug' => $slug ) );
            }
        }
    }
}
