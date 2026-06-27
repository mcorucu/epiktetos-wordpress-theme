<?php
/**
 * Virtual saved articles route.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' );
?>
<main id="main-content" class="wp-block-group ts-main">
	<?php echo do_shortcode( '[epiktetos_saved]' ); ?>
</main>
<?php
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->' );
wp_footer();
?>
</body>
</html>
