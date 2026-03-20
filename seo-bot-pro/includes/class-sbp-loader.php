<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central loader – wires every component.
 */
class SBP_Loader {

    public function run() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        $dir = SBP_PLUGIN_DIR . 'includes/';

        require_once $dir . 'class-sbp-helpers.php';
        require_once $dir . 'class-sbp-ai-service.php';
        require_once $dir . 'class-sbp-rest-api.php';
        require_once $dir . 'class-sbp-admin.php';
        require_once $dir . 'class-sbp-meta-box.php';
        require_once $dir . 'class-sbp-content-analysis.php';
        require_once $dir . 'class-sbp-faq-generator.php';
        require_once $dir . 'class-sbp-internal-links.php';
        require_once $dir . 'class-sbp-image-alt.php';
        require_once $dir . 'class-sbp-cron.php';
        require_once $dir . 'class-sbp-logger.php';
        require_once $dir . 'class-sbp-post-generator.php';
        require_once $dir . 'class-sbp-schema.php';
        require_once $dir . 'class-sbp-indexing.php';
        require_once $dir . 'class-sbp-sitemap.php';
        require_once $dir . 'class-sbp-rank-booster.php';
        require_once $dir . 'class-sbp-404-monitor.php';
        require_once $dir . 'class-sbp-breadcrumbs.php';
    }

    private function register_hooks() {
        // Admin
        $admin = new SBP_Admin();
        add_action( 'admin_menu', [ $admin, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_assets' ] );

        // REST API
        $api = new SBP_REST_API();
        add_action( 'rest_api_init', [ $api, 'register_routes' ] );

        // Meta box
        $meta = new SBP_Meta_Box();
        add_action( 'add_meta_boxes', [ $meta, 'register' ] );

        // Schema markup
        $schema = new SBP_Schema();
        $schema->init();

        // Built-in SEO head output (when seo_plugin = 'none')
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
        add_action( 'wp_head', [ $this, 'output_robots_meta' ], 1 );
        add_action( 'wp_head', [ $this, 'output_canonical' ], 1 );
        add_action( 'wp_head', [ $this, 'output_og_tags' ], 2 );
        add_action( 'wp_head', [ $this, 'output_twitter_tags' ], 2 );

        // Google Search Console verification
        add_action( 'wp_head', [ $this, 'output_verification_tags' ], 1 );

        // Sitemap
        $sitemap = new SBP_Sitemap();
        $sitemap->init();

        // IndexNow key verification file
        $indexing = new SBP_Indexing();
        add_action( 'template_redirect', [ $indexing, 'serve_indexnow_key' ] );

        // Auto-ping on publish
        if ( SBP_Helpers::get_option( 'auto_ping_publish', '0' ) === '1' ) {
            add_action( 'publish_post', [ $indexing, 'on_publish' ], 20, 2 );
            add_action( 'publish_page', [ $indexing, 'on_publish' ], 20, 2 );
            if ( class_exists( 'WooCommerce' ) ) {
                add_action( 'publish_product', [ $indexing, 'on_publish' ], 20, 2 );
            }
        }

        // CRON
        $cron = new SBP_Cron();
        add_action( 'sbp_daily_optimization', [ $cron, 'run' ] );

        // Rank Booster weekly cron
        $booster = new SBP_Rank_Booster();
        add_action( 'sbp_weekly_rank_boost', [ $booster, 'cron_refresh_stale' ] );

        // 404 Monitor & Redirects
        $monitor_404 = new SBP_404_Monitor();
        $monitor_404->init();
        add_action( 'template_redirect', [ $monitor_404, 'process_redirects' ], 1 );

        // Breadcrumbs
        $breadcrumbs = new SBP_Breadcrumbs();
        $breadcrumbs->init();

        // AJAX endpoints
        add_action( 'wp_ajax_sbp_optimize_post', [ $api, 'ajax_optimize_post' ] );
        add_action( 'wp_ajax_sbp_bulk_optimize', [ $api, 'ajax_bulk_optimize' ] );
        add_action( 'wp_ajax_sbp_generate_faq', [ $api, 'ajax_generate_faq' ] );
        add_action( 'wp_ajax_sbp_suggest_links', [ $api, 'ajax_suggest_links' ] );
        add_action( 'wp_ajax_sbp_fix_image_alts', [ $api, 'ajax_fix_image_alts' ] );
        add_action( 'wp_ajax_sbp_analyze_content', [ $api, 'ajax_analyze_content' ] );
        add_action( 'wp_ajax_sbp_generate_keywords', [ $api, 'ajax_generate_keywords' ] );
        add_action( 'wp_ajax_sbp_optimize_slug', [ $api, 'ajax_optimize_slug' ] );
        add_action( 'wp_ajax_sbp_generate_post', [ $api, 'ajax_generate_post' ] );
        add_action( 'wp_ajax_sbp_generate_excerpt', [ $api, 'ajax_generate_excerpt' ] );
        add_action( 'wp_ajax_sbp_rewrite_content', [ $api, 'ajax_rewrite_content' ] );
        add_action( 'wp_ajax_sbp_save_robots_meta', [ $api, 'ajax_save_robots_meta' ] );
        add_action( 'wp_ajax_sbp_ping_engines', [ $api, 'ajax_ping_engines' ] );
        add_action( 'wp_ajax_sbp_submit_sitemap', [ $api, 'ajax_submit_sitemap' ] );
        add_action( 'wp_ajax_sbp_bulk_indexnow', [ $api, 'ajax_bulk_indexnow' ] );
        add_action( 'wp_ajax_sbp_refresh_stale', [ $api, 'ajax_refresh_stale' ] );

        // Auto-optimize on publish
        if ( SBP_Helpers::get_option( 'auto_optimize_publish' ) === '1' ) {
            add_action( 'publish_post', [ $api, 'auto_optimize_on_publish' ], 10, 2 );
            add_action( 'publish_page', [ $api, 'auto_optimize_on_publish' ], 10, 2 );
            if ( class_exists( 'WooCommerce' ) ) {
                add_action( 'publish_product', [ $api, 'auto_optimize_on_publish' ], 10, 2 );
            }
        }
    }

    /**
     * Output meta title, description, keywords in <head> when seo_plugin = 'none'.
     */
    public function output_meta_tags() {
        if ( ! is_singular() || SBP_Helpers::get_option( 'seo_plugin', 'rank_math' ) !== 'none' ) {
            return;
        }

        $post_id    = get_the_ID();
        $meta_title = get_post_meta( $post_id, '_sbp_meta_title', true );
        $meta_desc  = get_post_meta( $post_id, '_sbp_meta_description', true );
        $meta_kw    = get_post_meta( $post_id, '_sbp_meta_keywords', true );

        if ( $meta_desc ) {
            echo '<meta name="description" content="' . esc_attr( $meta_desc ) . "\" />\n";
        }
        if ( $meta_kw ) {
            echo '<meta name="keywords" content="' . esc_attr( $meta_kw ) . "\" />\n";
        }
    }

    /**
     * Output Open Graph tags in <head>.
     */
    public function output_og_tags() {
        if ( ! is_singular() || SBP_Helpers::get_option( 'enable_og', '1' ) !== '1' ) {
            return;
        }

        // Skip if another SEO plugin handles OG (only output if seo_plugin = 'none')
        if ( SBP_Helpers::get_option( 'seo_plugin', 'rank_math' ) !== 'none' ) {
            return;
        }

        $post_id  = get_the_ID();
        $post     = get_post( $post_id );
        $og_title = get_post_meta( $post_id, '_sbp_og_title', true );
        $og_desc  = get_post_meta( $post_id, '_sbp_og_description', true );

        if ( ! $og_title ) {
            $og_title = get_post_meta( $post_id, '_sbp_meta_title', true ) ?: get_the_title( $post_id );
        }
        if ( ! $og_desc ) {
            $og_desc = get_post_meta( $post_id, '_sbp_meta_description', true );
        }

        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $og_title ) . "\" />\n";
        if ( $og_desc ) {
            echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . "\" />\n";
        }
        echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . "\" />\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\" />\n";

        $thumb = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( $thumb ) {
            echo '<meta property="og:image" content="' . esc_url( $thumb ) . "\" />\n";
        }
    }

    /**
     * Output Twitter Card tags in <head>.
     */
    public function output_twitter_tags() {
        if ( ! is_singular() || SBP_Helpers::get_option( 'enable_twitter', '1' ) !== '1' ) {
            return;
        }

        if ( SBP_Helpers::get_option( 'seo_plugin', 'rank_math' ) !== 'none' ) {
            return;
        }

        $post_id  = get_the_ID();
        $tw_title = get_post_meta( $post_id, '_sbp_twitter_title', true );
        $tw_desc  = get_post_meta( $post_id, '_sbp_twitter_description', true );

        if ( ! $tw_title ) {
            $tw_title = get_post_meta( $post_id, '_sbp_og_title', true )
                     ?: get_post_meta( $post_id, '_sbp_meta_title', true )
                     ?: get_the_title( $post_id );
        }
        if ( ! $tw_desc ) {
            $tw_desc = get_post_meta( $post_id, '_sbp_og_description', true )
                    ?: get_post_meta( $post_id, '_sbp_meta_description', true );
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $tw_title ) . "\" />\n";
        if ( $tw_desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $tw_desc ) . "\" />\n";
        }

        $thumb = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( $thumb ) {
            echo '<meta name="twitter:image" content="' . esc_url( $thumb ) . "\" />\n";
        }
    }

    /**
     * Output Google Search Console and Bing verification meta tags.
     */
    public function output_verification_tags() {
        $google_code = SBP_Helpers::get_option( 'google_verification', '' );
        $bing_code   = SBP_Helpers::get_option( 'bing_verification', '' );

        if ( $google_code ) {
            echo '<meta name="google-site-verification" content="' . esc_attr( $google_code ) . "\" />\n";
        }
        if ( $bing_code ) {
            echo '<meta name="msvalidate.01" content="' . esc_attr( $bing_code ) . "\" />\n";
        }
    }

    /**
     * Output robots noindex/nofollow in <head> when set via plugin meta.
     */
    public function output_robots_meta() {
        if ( ! is_singular() ) {
            return;
        }
        $post_id  = get_the_ID();
        $noindex  = get_post_meta( $post_id, '_sbp_noindex', true );
        $nofollow = get_post_meta( $post_id, '_sbp_nofollow', true );

        $robots = [];
        if ( $noindex === '1' ) {
            $robots[] = 'noindex';
        }
        if ( $nofollow === '1' ) {
            $robots[] = 'nofollow';
        }

        // Only output if SEO plugin is set to 'none' (other plugins handle their own)
        if ( ! empty( $robots ) && SBP_Helpers::get_option( 'seo_plugin', 'rank_math' ) === 'none' ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . "\" />\n";
        }
    }

    /**
     * Output canonical URL in <head> when set via plugin meta.
     */
    public function output_canonical() {
        if ( ! is_singular() ) {
            return;
        }
        $canonical = get_post_meta( get_the_ID(), '_sbp_canonical', true );

        if ( $canonical && SBP_Helpers::get_option( 'seo_plugin', 'rank_math' ) === 'none' ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . "\" />\n";
        }
    }
}
