<?php
/**
 * Full-page canvas for portal pages (app mode). Renders only the page content.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'rsp-app-body' ); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
wp_footer();
?>
</body>
</html>
