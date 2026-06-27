<?php
/**
 * Epiktetos header — settings registration, renderers, no-FOUC theme script.
 *
 * Architecture:
 * - Settings are saved on a single option (`epiktetos_options`) under
 *   the `epiktetos_settings` group, so the existing Appearance → Epiktetos
 *   page can manage them with the Settings API.
 * - Two shortcodes (`[epiktetos_logo]`, `[epiktetos_header_actions]`) are
 *   used inside the block-theme header template part. This keeps the Site
 *   Editor compatible while letting PHP own the dynamic output.
 * - An inline <head> script applies the persisted theme mode *before paint*
 *   so there is no flash.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Header' ) ) {

	/**
	 * Header configuration + renderers.
	 */
	class Epiktetos_Header {

		const OPTION = 'epiktetos_options';

		/** Hook everything up. */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_no_fouc_script' ), 1 );
			add_action( 'wp_head', array( __CLASS__, 'print_dynamic_css' ), 20 );
			add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		}

		/* ---------------- Defaults & getters ---------------- */

		/**
		 * Default option values.
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'logo_width'         => 160,
				'sticky_header'      => 1,
				'transparent_header' => 0,
				/* Default is Light. When the user hasn't expressed a choice we
				   ship a Light reading experience — System/Dark only on opt-in. */
				'default_theme_mode' => 'light',
				'show_rss'           => 1,
				'rss_url'            => '',
			);
		}

		/**
		 * Get a single option with default fallback.
		 *
		 * @param string $key Option key.
		 * @return mixed
		 */
		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$defaults = self::defaults();
			if ( isset( $opts[ $key ] ) ) {
				return $opts[ $key ];
			}
			return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
		}

		/* ---------------- Settings API ---------------- */

		/** Register the option, sections, and fields. */
		public static function register_settings() {
			register_setting(
				'epiktetos_settings',
				self::OPTION,
				array(
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'default'           => self::defaults(),
				)
			);

			add_settings_section(
				'epiktetos_header',
				__( 'Header', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Configure the wordmark, RSS, and theme switcher in the site header.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$header_fields = array(
				'logo_width'         => array( 'label' => __( 'Logo width (px)', 'epiktetos' ), 'type' => 'number', 'min' => 60, 'max' => 320 ),
				'sticky_header'      => array( 'label' => __( 'Sticky header', 'epiktetos' ), 'type' => 'checkbox', 'desc' => __( 'Keeps the header visible while readers move through the page.', 'epiktetos' ) ),
				'transparent_header' => array( 'label' => __( 'Transparent header', 'epiktetos' ), 'type' => 'checkbox', 'desc' => __( 'Makes the homepage header transparent until the reader starts scrolling.', 'epiktetos' ) ),
				'default_theme_mode' => array( 'label' => __( 'Default theme mode', 'epiktetos' ), 'type' => 'select', 'choices' => array( 'light' => __( 'Light (recommended)', 'epiktetos' ), 'dark' => __( 'Dark', 'epiktetos' ) ) ),
				'show_rss'           => array( 'label' => __( 'Show RSS', 'epiktetos' ), 'type' => 'checkbox' ),
				'rss_url'            => array( 'label' => __( 'RSS URL', 'epiktetos' ), 'type' => 'url', 'placeholder' => home_url( '/feed/' ) ),
			);

			foreach ( $header_fields as $key => $field ) {
				add_settings_field(
					$key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_header',
					array_merge( array( 'key' => $key ), $field )
				);
			}
		}

		/**
		 * Render a single settings field.
		 *
		 * @param array $args Field args.
		 */
		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-' . $key;

			switch ( $args['type'] ) {
				case 'checkbox':
					printf(
						'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
						esc_attr( $id ),
						esc_attr( $name ),
						checked( 1, (int) $value, false ),
						esc_html__( 'Enabled', 'epiktetos' )
					);
					break;
				case 'number':
					printf(
						'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						esc_attr( isset( $args['min'] ) ? $args['min'] : '' ),
						esc_attr( isset( $args['max'] ) ? $args['max'] : '' )
					);
					break;
				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $args['choices'] as $choice_value => $choice_label ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( $choice_value ),
							selected( $value, $choice_value, false ),
							esc_html( $choice_label )
						);
					}
					echo '</select>';
					break;
				case 'url':
					printf(
						'<input type="url" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="regular-text" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
					);
					break;
			}

			if ( ! empty( $args['desc'] ) ) {
				echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
			}
		}

		/**
		 * Sanitize the options array before save.
		 *
		 * @param array $input Raw POST values.
		 * @return array
		 */
		public static function sanitize( $input ) {
			$defaults = self::defaults();
			$out      = array();

			$out['logo_width']         = max( 60, min( 320, (int) ( isset( $input['logo_width'] ) ? $input['logo_width'] : $defaults['logo_width'] ) ) );
			$out['sticky_header']      = ! empty( $input['sticky_header'] ) ? 1 : 0;
			$out['transparent_header'] = ! empty( $input['transparent_header'] ) ? 1 : 0;
			$mode                      = isset( $input['default_theme_mode'] ) ? $input['default_theme_mode'] : $defaults['default_theme_mode'];
			$out['default_theme_mode'] = in_array( $mode, array( 'light', 'dark' ), true ) ? $mode : 'light';

			foreach ( array( 'show_rss' ) as $bool_key ) {
				$out[ $bool_key ] = ! empty( $input[ $bool_key ] ) ? 1 : 0;
			}

			foreach ( array( 'rss_url' ) as $url_key ) {
				$raw            = isset( $input[ $url_key ] ) ? trim( $input[ $url_key ] ) : '';
				$out[ $url_key ] = $raw ? esc_url_raw( $raw ) : '';
			}

			return $out;
		}

		/* ---------------- Shortcodes ---------------- */

		public static function register_shortcodes() {
			add_shortcode( 'epiktetos_logo', array( __CLASS__, 'shortcode_logo' ) );
			add_shortcode( 'epiktetos_header_nav', array( __CLASS__, 'shortcode_nav' ) );
			add_shortcode( 'epiktetos_header_actions', array( __CLASS__, 'shortcode_actions' ) );
		}

		/**
		 * Logo shortcode — outputs the SVG wordmark inside a home link.
		 *
		 * @return string
		 */
		public static function shortcode_logo() {
			$width    = (int) self::get( 'logo_width' );
			$blogname = get_bloginfo( 'name', 'display' );
			$logo     = class_exists( 'Epiktetos_Branding' )
				? Epiktetos_Branding::render_logo( 'header', $width )
				: '<span class="ts-logo__text">' . esc_html( $blogname ) . '</span>';

			/* Wordmark is rendered as live text in the display font: always
			   crisp, scalable, theme-aware (currentColor), and immune to the
			   SVG-text + webfont timing problems. The previous SVG file added
			   a stray decorative dot and a too-wide viewBox — both removed. */
			$html = '<a class="ts-logo" href="' . esc_url( home_url( '/' ) ) . '" rel="home"'
				. ' aria-label="' . esc_attr( $blogname ) . '"'
				. ' style="--ts-logo-w:' . $width . 'px;">'
				. $logo
				. '</a>';

			return self::compress_html( $html );
		}

		/**
		 * Header navigation shortcode.
		 *
		 * Category links are generated by WordPress and ordered by the existing
		 * admin category-order system when available, so plain and pretty
		 * permalink modes both point to the real archive URLs.
		 *
		 * @return string
		 */
		public static function shortcode_nav() {
			$open_svg = '<svg class="ts-nav-toggle__open" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>';
			$close_svg = '<svg class="ts-nav-toggle__close" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';

			$menu = wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'ts-nav__list',
				'menu_id'        => '',
				'depth'          => 3,
				'fallback_cb'    => false,
				'echo'           => false,
			) );
			if ( ! $menu ) {
				$menu = '<ul class="ts-nav__list">' . self::fallback_menu_items( 'primary' ) . '</ul>';
			}

			$html  = '<nav class="ts-header__nav" aria-label="' . esc_attr__( 'Primary', 'epiktetos' ) . '">';
			$html .= '<div class="ts-nav-toggle-wrap"><button type="button" class="ts-icon-btn ts-nav-toggle" aria-label="' . esc_attr__( 'Open menu', 'epiktetos' ) . '" aria-controls="ts-nav-panel" aria-expanded="false">' . $open_svg . $close_svg . '</button></div>';
			$html .= '<div class="ts-nav-panel" id="ts-nav-panel">';
			$html .= $menu;
			$html .= '<div class="ts-nav__extras">';
			$html .= '<span class="ts-nav__divider" aria-hidden="true"></span>';
			$html .= '<a class="ts-nav__rss" href="' . esc_url( get_bloginfo( 'rss2_url' ) ) . '">' . self::inline_icon( 'rss' ) . '<span>' . esc_html__( 'RSS feed', 'epiktetos' ) . '</span></a>';
			$html .= '<button type="button" class="ts-nav__theme ts-theme-toggle" data-ts-theme-toggle aria-pressed="false" aria-label="' . esc_attr__( 'Switch to dark mode', 'epiktetos' ) . '" data-label-light="' . esc_attr__( 'Switch to dark mode', 'epiktetos' ) . '" data-label-dark="' . esc_attr__( 'Switch to light mode', 'epiktetos' ) . '">' . self::inline_icon( 'sun' ) . self::inline_icon( 'moon' ) . '<span class="ts-nav__theme-label">' . esc_html__( 'Theme', 'epiktetos' ) . '</span></button>';
			$html .= '</div></div></nav>';

			return self::compress_html( $html );
		}

		protected static function fallback_menu_items( $context = 'primary' ) {
			$items = array(
				array( __( 'Home', 'epiktetos' ), home_url( '/' ), is_front_page() ),
				array( __( 'Topics', 'epiktetos' ), self::page_or_fallback_url( 'topics', home_url( '/topics/' ) ), is_page( 'topics' ) ),
				array( __( 'About', 'epiktetos' ), self::page_or_fallback_url( 'about', home_url( '/about/' ) ), is_page( 'about' ) ),
			);
			if ( 'primary' === $context ) {
				$items[] = array( __( 'Contact', 'epiktetos' ), self::page_or_fallback_url( 'contact', home_url( '/contact/' ) ), is_page( 'contact' ) );
				$items[] = array( __( 'Search', 'epiktetos' ), home_url( '/?s=' ), is_search() );
			}

			$html = '';
			$seen = array();
			foreach ( $items as $item ) {
				list( $label, $url, $current ) = $item;
				if ( ! $url || isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$class = $current ? ' class="menu-item current-menu-item"' : ' class="menu-item"';
				$aria  = $current ? ' aria-current="page"' : '';
				$html .= '<li' . $class . '><a href="' . esc_url( $url ) . '"' . $aria . '>' . esc_html( $label ) . '</a></li>';
			}
			return $html;
		}

		protected static function page_or_fallback_url( $slug, $fallback ) {
			$page = get_page_by_path( $slug );
			return $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : $fallback;
		}

		/**
		 * Header actions shortcode — search, RSS, social, theme toggle.
		 *
		 * @return string
		 */
		public static function shortcode_actions() {
			$show_rss = (int) self::get( 'show_rss' );
			$rss_url  = self::get( 'rss_url' );
			if ( ! $rss_url ) {
				$rss_url = get_bloginfo( 'rss2_url' );
			}

			$search_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>';

			$html  = '<div class="ts-header__actions">';

			$html .= '<button type="button" class="ts-icon-btn ts-search-toggle"'
				. ' aria-label="' . esc_attr__( 'Search', 'epiktetos' ) . '"'
				. ' data-tooltip="' . esc_attr__( 'Search', 'epiktetos' ) . '"'
				. ' aria-expanded="false" aria-controls="ts-search-panel" aria-haspopup="dialog">'
				. $search_svg . '</button>';

			if ( $show_rss && $rss_url ) {
				$html .= '<a class="ts-icon-btn ts-icon-rss" href="' . esc_url( $rss_url ) . '"'
					. ' aria-label="' . esc_attr__( 'RSS feed', 'epiktetos' ) . '"'
					. ' data-tooltip="' . esc_attr__( 'RSS feed', 'epiktetos' ) . '">'
					. self::inline_icon( 'rss' ) . '</a>';
			}

			$html .= '<button type="button" class="ts-icon-btn ts-theme-toggle" data-ts-theme-toggle'
				. ' aria-label="' . esc_attr__( 'Switch to dark mode', 'epiktetos' ) . '"'
				. ' data-tooltip="' . esc_attr__( 'Theme', 'epiktetos' ) . '"'
				. ' data-label-light="' . esc_attr__( 'Switch to dark mode', 'epiktetos' ) . '"'
				. ' data-label-dark="' . esc_attr__( 'Switch to light mode', 'epiktetos' ) . '"'
				. ' aria-pressed="false">'
				. self::inline_icon( 'sun' ) . self::inline_icon( 'moon' )
				. '</button>';

			$html .= '</div>';

			return self::compress_html( $html );
		}

		/**
		 * Collapse whitespace between tags and strip newlines so the
		 * `core/shortcode` block's wpautop pass cannot inject <p>/<br>
		 * into header markup (which previously broke layout and shifted
		 * nth-of-type icon selectors).
		 *
		 * @param string $html Raw HTML.
		 * @return string
		 */
		protected static function compress_html( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}

		/**
		 * Read an SVG asset and return it inline (so currentColor inverts),
		 * with whitespace collapsed so it stays a single clean element.
		 *
		 * @param string $slug Icon slug.
		 * @return string
		 */
		public static function inline_icon( $slug ) {
			$slug = preg_replace( '/[^a-z0-9_-]/', '', $slug );
			if ( ! $slug ) {
				return '';
			}
			$path = trailingslashit( get_template_directory() ) . 'assets/icons/' . $slug . '.svg';
			if ( ! file_exists( $path ) ) {
				return '';
			}
			return self::compress_html( file_get_contents( $path ) );
		}

		/* ---------------- Theme mode (no-FOUC) ---------------- */

		/**
		 * Inline script run as early as possible: reads localStorage / system
		 * preference and sets data-theme on <html> before paint.
		 */
		public static function print_no_fouc_script() {
			$default = self::get( 'default_theme_mode' );
			if ( ! in_array( $default, array( 'light', 'dark' ), true ) ) {
				$default = 'light';
			}
			$default = esc_js( $default );
			?>
<script>(function(){try{var k='ts-theme';var s=localStorage.getItem(k);var m=(s==='light'||s==='dark')?s:'<?php echo $default; ?>';var r=document.documentElement;r.setAttribute('data-theme',m);r.style.colorScheme=m;}catch(e){}})();</script>
			<?php
		}

		/**
		 * Inject dynamic CSS tied to options (logo width only).
		 *
		 * Header positioning/state is driven entirely by the body state classes
		 * added in {@see body_class()} and the rules in frontend.css — no inline
		 * !important overrides.
		 */
		public static function print_dynamic_css() {
			$logo_w = (int) self::get( 'logo_width' );
			echo '<style id="epiktetos-dynamic">:root{--ts-logo-w:' . (int) $logo_w . 'px;}</style>';
		}

		/**
		 * Whether the transparent header should apply on the current request.
		 * Limited to the front page so overlaying never harms readability on
		 * content routes (single, archives, search, pages, admin).
		 */
		public static function transparent_active() {
			return (int) self::get( 'transparent_header' ) && is_front_page();
		}

		/**
		 * Add header-state body classes so all header CSS/JS can key off a single
		 * source of truth: sticky vs static, and transparent vs solid.
		 *
		 * @param array $classes Existing classes.
		 * @return array
		 */
		public static function body_class( $classes ) {
			$classes[] = self::get( 'sticky_header' ) ? 'epiktetos-header-sticky' : 'epiktetos-header-static';
			$classes[] = self::transparent_active() ? 'epiktetos-header-transparent' : 'epiktetos-header-solid';
			return $classes;
		}
	}

	Epiktetos_Header::init();
}
