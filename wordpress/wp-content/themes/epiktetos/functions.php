<?php
/**
 * Epiktetos theme functions.
 *
 * Local release candidate for the Epiktetos block theme.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPIKTETOS_VERSION', '1.0.0' );
define( 'EPIKTETOS_DIR', get_template_directory() );
define( 'EPIKTETOS_URI', get_template_directory_uri() );

/**
 * Asset version = file modification time, so every local edit busts the
 * browser cache automatically. Falls back to the theme version.
 *
 * @param string $rel Theme-relative asset path (e.g. 'assets/css/frontend.css').
 * @return string
 */
function epiktetos_asset_ver( $rel ) {
	$path = EPIKTETOS_DIR . '/' . ltrim( $rel, '/' );
	$mtime = file_exists( $path ) ? filemtime( $path ) : 0;
	return $mtime ? (string) $mtime : EPIKTETOS_VERSION;
}

/**
 * Theme supports.
 */
function epiktetos_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'menus' );

	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'epiktetos' ),
		'footer'  => __( 'Footer Navigation', 'epiktetos' ),
	) );

	// Editor stylesheets: load the full frontend system first so the Site
	// Editor renders template parts (header/footer) with real styling, then
	// editor.css for editor-specific refinements.
	add_editor_style( array(
		'assets/css/fonts.css',
		'assets/css/frontend.css',
		'assets/css/editor.css',
	) );

	load_theme_textdomain( 'epiktetos', EPIKTETOS_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'epiktetos_setup' );

/**
 * Seed editable starter menus on theme activation.
 *
 * The header and footer consume the 'primary' and 'footer' nav menu locations
 * and fall back to a hardcoded list when nothing is assigned. On a fresh block
 * theme that fallback hides the fact that Appearance → Menus is fully working —
 * locations look empty and assignment appears to do nothing. Seeding real,
 * editable menus makes the Menus screen reflect the live navigation out of the
 * box. Idempotent: runs only when no location is assigned and the named menus
 * do not already exist, so it never overwrites a user's own menus.
 */
function epiktetos_seed_default_menus() {
	if ( ! function_exists( 'wp_create_nav_menu' ) ) {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
	}

	$locations = (array) get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) || ! empty( $locations['footer'] ) ) {
		return; // A location is already assigned — respect existing setup.
	}

	$page_id = function ( $slug ) {
		$page = get_page_by_path( $slug );
		return ( $page && 'publish' === get_post_status( $page ) ) ? (int) $page->ID : 0;
	};

	$add_item = function ( $menu_id, $title, $url, $page = 0 ) {
		$args = array(
			'menu-item-title'   => $title,
			'menu-item-status'  => 'publish',
			'menu-item-classes' => '',
		);
		if ( $page ) {
			$args['menu-item-object']    = 'page';
			$args['menu-item-object-id'] = $page;
			$args['menu-item-type']      = 'post_type';
		} else {
			$args['menu-item-url']  = $url;
			$args['menu-item-type'] = 'custom';
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	};

	$assign = $locations;

	// Primary: Home, Topics, About, Contact.
	if ( empty( $assign['primary'] ) && ! wp_get_nav_menu_object( 'Primary' ) ) {
		$primary = wp_create_nav_menu( 'Primary' );
		if ( ! is_wp_error( $primary ) ) {
			$add_item( $primary, __( 'Home', 'epiktetos' ), home_url( '/' ) );
			foreach ( array( 'topics' => __( 'Topics', 'epiktetos' ), 'about' => __( 'About', 'epiktetos' ), 'contact' => __( 'Contact', 'epiktetos' ) ) as $slug => $label ) {
				$pid = $page_id( $slug );
				if ( $pid ) {
					$add_item( $primary, $label, '', $pid );
				}
			}
			$assign['primary'] = (int) $primary;
		}
	}

	// Footer: About, Topics, Contact.
	if ( empty( $assign['footer'] ) && ! wp_get_nav_menu_object( 'Footer' ) ) {
		$footer = wp_create_nav_menu( 'Footer' );
		if ( ! is_wp_error( $footer ) ) {
			foreach ( array( 'about' => __( 'About', 'epiktetos' ), 'topics' => __( 'Topics', 'epiktetos' ), 'contact' => __( 'Contact', 'epiktetos' ) ) as $slug => $label ) {
				$pid = $page_id( $slug );
				if ( $pid ) {
					$add_item( $footer, $label, '', $pid );
				}
			}
			$assign['footer'] = (int) $footer;
		}
	}

	set_theme_mod( 'nav_menu_locations', $assign );
}
add_action( 'after_switch_theme', 'epiktetos_seed_default_menus' );

/**
 * Enqueue frontend assets.
 */
function epiktetos_enqueue_assets() {
	$is_saved_route = function_exists( 'epiktetos_is_saved_route' ) && epiktetos_is_saved_route();

	// Self-hosted webfonts: Libre Baskerville (display) + Inter (body/UI),
	// latin subset, declared with font-display:swap. Bundled locally as WOFF2 —
	// no external requests, no Google Fonts dependency. See assets/fonts/.
	wp_enqueue_style(
		'epiktetos-fonts',
		EPIKTETOS_URI . '/assets/css/fonts.css',
		array(),
		epiktetos_asset_ver( 'assets/css/fonts.css' )
	);

	// Base theme stylesheet (header metadata).
	wp_enqueue_style(
		'epiktetos-style',
		get_stylesheet_uri(),
		array( 'epiktetos-fonts' ),
		epiktetos_asset_ver( 'style.css' )
	);

	// Real frontend visual system.
	wp_enqueue_style(
		'epiktetos-frontend',
		EPIKTETOS_URI . '/assets/css/frontend.css',
		array( 'epiktetos-style' ),
		epiktetos_asset_ver( 'assets/css/frontend.css' )
	);

	// Header behaviors (search panel, theme switcher, scroll states).
	wp_enqueue_script(
		'epiktetos-header',
		EPIKTETOS_URI . '/assets/js/header.js',
		array(),
		epiktetos_asset_ver( 'assets/js/header.js' ),
		true
	);
	wp_localize_script(
		'epiktetos-header',
		'EpiktetosHeader',
		array(
			'strings' => array(
				'switchLight'       => __( 'Switch to light mode', 'epiktetos' ),
				'switchDark'        => __( 'Switch to dark mode', 'epiktetos' ),
				'noResults'         => __( 'No articles found.', 'epiktetos' ),
				'searching'         => __( 'Searching...', 'epiktetos' ),
				'resultSingular'    => __( 'result found.', 'epiktetos' ),
				'resultPlural'      => __( 'results found.', 'epiktetos' ),
				'openMenu'          => __( 'Open menu', 'epiktetos' ),
				'closeMenu'         => __( 'Close menu', 'epiktetos' ),
				'newsletterOffline' => __( 'Subscribe via RSS while email delivery is offline.', 'epiktetos' ),
			),
		)
	);

	// Category Showcase sliders — front page only.
	if ( is_front_page() && ! $is_saved_route ) {
		wp_enqueue_script(
			'epiktetos-categories',
			EPIKTETOS_URI . '/assets/js/categories.js',
			array(),
			epiktetos_asset_ver( 'assets/js/categories.js' ),
			true
		);
	}

	// Single-post reading aids — single posts only.
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'epiktetos-single',
			EPIKTETOS_URI . '/assets/js/single.js',
			array(),
			epiktetos_asset_ver( 'assets/js/single.js' ),
			true
		);
		wp_localize_script(
			'epiktetos-single',
			'EpiktetosSingle',
			array(
				'strings' => array(
					'linkCopied'      => __( 'Link copied', 'epiktetos' ),
					'sendingResponse' => __( 'Sending your response...', 'epiktetos' ),
				),
			)
		);
	}

	if ( class_exists( 'Epiktetos_Reader' ) && epiktetos_should_enqueue_reader_assets( $is_saved_route ) ) {
		wp_enqueue_script(
			'epiktetos-reader',
			EPIKTETOS_URI . '/assets/js/reader.js',
			array(),
			epiktetos_asset_ver( 'assets/js/reader.js' ),
			true
		);
		wp_localize_script(
			'epiktetos-reader',
			'EpiktetosReader',
			array(
				'settings' => Epiktetos_Reader::client_settings(),
				'strings'  => array(
					'continueReading' => __( 'Continue Reading', 'epiktetos' ),
					'remove'          => __( 'Remove', 'epiktetos' ),
					'copyQuote'       => __( 'Copy Quote', 'epiktetos' ),
					'copied'          => __( 'Copied', 'epiktetos' ),
					'minutesRead'     => __( 'minutes', 'epiktetos' ),
					'articlesRead'    => __( 'articles', 'epiktetos' ),
					'shortcutsTitle'  => __( 'Keyboard shortcuts', 'epiktetos' ),
					'done'            => __( 'Done', 'epiktetos' ),
					'minLeft'         => __( 'min left', 'epiktetos' ),
					'saved'           => __( 'Saved', 'epiktetos' ),
					'save'            => __( 'Save', 'epiktetos' ),
					'saveForLater'    => __( 'Save for later', 'epiktetos' ),
					'closeImage'      => __( 'Close image', 'epiktetos' ),
					'zoomImage'       => __( 'Zoom image', 'epiktetos' ),
					'shortcuts'       => __( 'Shortcuts', 'epiktetos' ),
					'headingShortcut' => __( 'Next / previous heading', 'epiktetos' ),
					'search'          => __( 'Search', 'epiktetos' ),
					'close'           => __( 'Close', 'epiktetos' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'epiktetos_enqueue_assets' );

/**
 * Detect the local saved-articles route before WordPress has fully resolved the
 * virtual template. This keeps front-page-only assets off /saved/.
 */
function epiktetos_is_saved_route() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
	return 'saved' === $path || ( class_exists( 'Epiktetos_Reader' ) && get_query_var( Epiktetos_Reader::SAVED_QUERY_VAR ) );
}

/**
 * Reader JS is only needed where local history, save buttons, saved articles,
 * article completion, or archive/search history modules are present.
 */
function epiktetos_should_enqueue_reader_assets( $is_saved_route = null ) {
	$is_saved_route = null === $is_saved_route ? epiktetos_is_saved_route() : (bool) $is_saved_route;
	return $is_saved_route || is_front_page() || is_singular( 'post' ) || is_archive() || is_search();
}

/**
 * Skip-to-content link — first focusable element on every page.
 * Targets the #main-content landmark present in every template.
 */
function epiktetos_skip_link() {
	echo '<a class="ts-skip-link" href="#main-content">' . esc_html__( 'Skip to content', 'epiktetos' ) . '</a>';
}
add_action( 'wp_body_open', 'epiktetos_skip_link', 1 );

/**
 * Preload the primary body font so first paint isn't blocked waiting on it.
 * Fonts are self-hosted, so there are no external preconnects to declare.
 */
function epiktetos_preload_fonts() {
	$href = EPIKTETOS_URI . '/assets/fonts/inter-variable.woff2';
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( $href )
	);
}
add_action( 'wp_head', 'epiktetos_preload_fonts', 1 );

/**
 * Branding assets and favicon support.
 */
require_once EPIKTETOS_DIR . '/inc/branding/class-epiktetos-branding.php';

/**
 * Header subsystem (settings, shortcodes, no-FOUC theme script).
 */
require_once EPIKTETOS_DIR . '/inc/header/class-epiktetos-header.php';

/**
 * Homepage hero subsystem ([epiktetos_hero] shortcode).
 *
 * Note on wpautop: block templates run their full output through wpautop after
 * do_blocks(), which wraps any inline element sitting between block-level
 * siblings in stray <p>/</p>. Rather than fight that filter, the hero markup
 * keeps every direct child block-level (figure / div / h1 / p), so wpautop has
 * no loose inline content to wrap — the same reason the header actions <div>
 * renders cleanly.
 */
require_once EPIKTETOS_DIR . '/inc/hero/class-epiktetos-hero.php';

/**
 * Homepage "Latest Articles" section ([epiktetos_latest_articles]).
 */
require_once EPIKTETOS_DIR . '/inc/latest/class-epiktetos-latest.php';

/**
 * Homepage Category Showcase ([epiktetos_category_showcase]).
 */
require_once EPIKTETOS_DIR . '/inc/categories/class-epiktetos-categories.php';

/**
 * Site footer ([epiktetos_footer]).
 */
require_once EPIKTETOS_DIR . '/inc/footer/class-epiktetos-footer.php';

/**
 * Editorial discussion experience ([epiktetos_comments]).
 */
require_once EPIKTETOS_DIR . '/inc/comments/class-epiktetos-comments.php';

/**
 * Single post reading experience ([epiktetos_single]).
 */
require_once EPIKTETOS_DIR . '/inc/single/class-epiktetos-single.php';

/**
 * Reader intelligence, local history, saved articles, and editor picks.
 */
require_once EPIKTETOS_DIR . '/inc/reader/class-epiktetos-reader.php';

/**
 * Search and discovery experience ([epiktetos_search_panel], [epiktetos_search]).
 */
require_once EPIKTETOS_DIR . '/inc/search/class-epiktetos-search.php';

/**
 * Archive and category experience ([epiktetos_archive]).
 */
require_once EPIKTETOS_DIR . '/inc/archive/class-epiktetos-archive.php';

/**
 * Author and static page experience ([epiktetos_author], [epiktetos_page]).
 */
require_once EPIKTETOS_DIR . '/inc/pages/class-epiktetos-pages.php';

/**
 * Tag archives and Topics index ([epiktetos_tag], [epiktetos_topics]).
 */
require_once EPIKTETOS_DIR . '/inc/taxonomies/class-epiktetos-taxonomies.php';

/**
 * SEO, social metadata, and publication intelligence.
 */
require_once EPIKTETOS_DIR . '/inc/seo/class-epiktetos-seo.php';

/**
 * Admin settings page.
 */
require_once EPIKTETOS_DIR . '/inc/admin/class-epiktetos-settings.php';

/**
 * Admin product suite — System, Tools, Demo Content, Validator.
 */
require_once EPIKTETOS_DIR . '/inc/admin/class-epiktetos-admin.php';

/**
 * First-run setup wizard.
 */
require_once EPIKTETOS_DIR . '/inc/admin/class-epiktetos-wizard.php';
