<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Breadcrumbs – outputs JSON-LD BreadcrumbList schema.
 */
class SBP_Breadcrumbs {

    /**
     * Register wp_head hook if enabled.
     */
    public function init() {
        if ( SBP_Helpers::get_option( 'enable_breadcrumbs', '0' ) === '1' ) {
            add_action( 'wp_head', [ $this, 'output_breadcrumbs' ], 5 );
        }
    }

    /**
     * Output BreadcrumbList JSON-LD on singular pages, archives, etc.
     */
    public function output_breadcrumbs() {
        $items = $this->build_breadcrumb_trail();

        if ( count( $items ) < 2 ) {
            return;
        }

        $list_items = [];
        foreach ( $items as $i => $item ) {
            $entry = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
            ];
            if ( ! empty( $item['url'] ) ) {
                $entry['item'] = $item['url'];
            }
            $list_items[] = $entry;
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list_items,
        ];

        echo '<script type="application/ld+json">'
           . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
           . "</script>\n";
    }

    /**
     * Build the breadcrumb trail array.
     */
    private function build_breadcrumb_trail(): array {
        $trail = [];

        // Home
        $trail[] = [
            'name' => get_bloginfo( 'name' ),
            'url'  => home_url( '/' ),
        ];

        if ( is_singular() ) {
            $post = get_queried_object();

            // Add post type archive if applicable
            if ( $post->post_type !== 'page' ) {
                $type_obj = get_post_type_object( $post->post_type );
                if ( $type_obj && $type_obj->has_archive ) {
                    $trail[] = [
                        'name' => $type_obj->labels->name,
                        'url'  => get_post_type_archive_link( $post->post_type ),
                    ];
                }
            }

            // Add category for posts
            if ( $post->post_type === 'post' ) {
                $cats = get_the_category( $post->ID );
                if ( ! empty( $cats ) ) {
                    // Use primary category
                    $cat = $cats[0];
                    // Walk up parent categories
                    $cat_trail = [];
                    $current   = $cat;
                    while ( $current ) {
                        $cat_trail[] = [
                            'name' => $current->name,
                            'url'  => get_category_link( $current->term_id ),
                        ];
                        $current = $current->parent ? get_category( $current->parent ) : null;
                    }
                    $trail = array_merge( $trail, array_reverse( $cat_trail ) );
                }
            }

            // Add page parents for hierarchical post types
            if ( is_post_type_hierarchical( $post->post_type ) && $post->post_parent ) {
                $parents = [];
                $parent_id = $post->post_parent;
                while ( $parent_id ) {
                    $parent    = get_post( $parent_id );
                    $parents[] = [
                        'name' => $parent->post_title,
                        'url'  => get_permalink( $parent->ID ),
                    ];
                    $parent_id = $parent->post_parent;
                }
                $trail = array_merge( $trail, array_reverse( $parents ) );
            }

            // Current page (no URL for last item)
            $trail[] = [
                'name' => $post->post_title,
                'url'  => '',
            ];

        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( is_category() && $term->parent ) {
                $parents = [];
                $parent_id = $term->parent;
                while ( $parent_id ) {
                    $parent    = get_category( $parent_id );
                    $parents[] = [
                        'name' => $parent->name,
                        'url'  => get_category_link( $parent->term_id ),
                    ];
                    $parent_id = $parent->parent;
                }
                $trail = array_merge( $trail, array_reverse( $parents ) );
            }
            $trail[] = [
                'name' => $term->name,
                'url'  => '',
            ];

        } elseif ( is_post_type_archive() ) {
            $trail[] = [
                'name' => post_type_archive_title( '', false ),
                'url'  => '',
            ];

        } elseif ( is_search() ) {
            $trail[] = [
                'name' => sprintf( __( 'Search: %s', 'seo-bot-pro' ), get_search_query() ),
                'url'  => '',
            ];

        } elseif ( is_404() ) {
            $trail[] = [
                'name' => __( '404 Not Found', 'seo-bot-pro' ),
                'url'  => '',
            ];
        }

        return $trail;
    }
}
