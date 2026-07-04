<?php
/**
 * Epiktetos — premium editorial footer.
 *
 * Renders the site colophon via [epiktetos_footer], used in parts/footer.html.
 * Columns: brand · navigation · topics (dynamic categories) · subscribe/feed,
 * with a bottom bar. Footer text + toggles are admin-managed (Appearance →
 * Epiktetos → Footer) on their own option.
 *
 * Markup is wpautop-proof: every direct child of a processed container is
 * block-level (div / nav / ul / li / h2 / p / form).
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Footer' ) ) {

	class Epiktetos_Footer {

		const OPTION = 'epiktetos_footer_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_footer', array( __CLASS__, 'render' ) );
		}

		/* ---------------- Options ---------------- */

		public static function defaults() {
			return array(
				'description'      => __( 'A quiet editorial journal on technology, philosophy, psychology, and history.', 'epiktetos' ),
				'show_newsletter'  => 1,
				'newsletter_title' => __( 'Subscribe', 'epiktetos' ),
				'newsletter_text'  => __( 'Receive new essays quietly.', 'epiktetos' ),
				'show_rss'         => 1,
				'youtube_url'      => '',
				'x_url'            => '',
				'linkedin_url'     => '',
				'github_url'       => '',
				'credit'           => __( 'Built by Mehmet Can Orucu', 'epiktetos' ),
				'copyright'        => '',
			);
		}

		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$def  = self::defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) && '' !== $opts[ $key ] ) {
				return $opts[ $key ];
			}
			return isset( $def[ $key ] ) ? $def[ $key ] : '';
		}

		/* ---------------- Settings ---------------- */

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
				'epiktetos_footer',
				__( 'Footer', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'The editorial colophon shown at the bottom of every page.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$social_help = __( 'Leave empty to hide an icon. Links open in a new tab.', 'epiktetos' );
			$fields = array(
				'description'      => array( 'label' => __( 'Footer description', 'epiktetos' ), 'type' => 'textarea' ),
				'show_newsletter'  => array( 'label' => __( 'Show footer newsletter', 'epiktetos' ), 'type' => 'checkbox' ),
				'newsletter_title' => array( 'label' => __( 'Newsletter title', 'epiktetos' ), 'type' => 'text' ),
				'newsletter_text'  => array( 'label' => __( 'Newsletter text', 'epiktetos' ), 'type' => 'text' ),
				'show_rss'         => array( 'label' => __( 'Show RSS', 'epiktetos' ), 'type' => 'checkbox' ),
				'youtube_url'      => array( 'label' => __( 'YouTube URL', 'epiktetos' ), 'type' => 'url', 'desc' => $social_help ),
				'x_url'            => array( 'label' => __( 'X URL', 'epiktetos' ), 'type' => 'url' ),
				'linkedin_url'     => array( 'label' => __( 'LinkedIn URL', 'epiktetos' ), 'type' => 'url' ),
				'github_url'       => array( 'label' => __( 'GitHub URL', 'epiktetos' ), 'type' => 'url' ),
				'credit'           => array( 'label' => __( 'Credit text', 'epiktetos' ), 'type' => 'text' ),
				'copyright'        => array( 'label' => __( 'Copyright text', 'epiktetos' ), 'type' => 'text', 'desc' => __( 'Leave empty to show “© {year} {Site Name}”. Use %year% and %site% as placeholders.', 'epiktetos' ) ),
			);
			foreach ( $fields as $key => $f ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$f['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_footer',
					array_merge( array( 'key' => $key ), $f )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-footer-' . $key;

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
				case 'textarea':
					printf(
						'<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_textarea( $value )
					);
					break;
				case 'url':
					printf(
						'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="https://" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value )
					);
					break;
				default:
					printf(
						'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value )
					);
			}

			if ( ! empty( $args['desc'] ) ) {
				echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
			}
		}

		public static function sanitize( $input ) {
			$out = array();
			$out['description']      = isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '';
			$out['show_newsletter']  = ! empty( $input['show_newsletter'] ) ? 1 : 0;
			$out['newsletter_title'] = isset( $input['newsletter_title'] ) ? sanitize_text_field( $input['newsletter_title'] ) : '';
			$out['newsletter_text']  = isset( $input['newsletter_text'] ) ? sanitize_text_field( $input['newsletter_text'] ) : '';
			$out['show_rss']         = ! empty( $input['show_rss'] ) ? 1 : 0;
			foreach ( array( 'youtube_url', 'x_url', 'linkedin_url', 'github_url' ) as $url_key ) {
				$raw            = isset( $input[ $url_key ] ) ? trim( $input[ $url_key ] ) : '';
				$out[ $url_key ] = $raw ? esc_url_raw( $raw ) : '';
			}
			$out['credit']           = isset( $input['credit'] ) ? sanitize_text_field( $input['credit'] ) : '';
			$out['copyright']        = isset( $input['copyright'] ) ? sanitize_text_field( $input['copyright'] ) : '';
			return $out;
		}

		/* ---------------- Render ---------------- */

		public static function render() {
			$blogname = get_bloginfo( 'name', 'display' );
			$home     = home_url( '/' );

			/* --- Brand --- */
			$logo = class_exists( 'Epiktetos_Branding' )
				? Epiktetos_Branding::render_logo( 'footer', 150 )
				: '<span class="ts-logo__text">' . esc_html( $blogname ) . '</span>';
			$brand  = '<div class="ts-footer__col ts-footer__brand">';
			$brand .= '<div class="ts-footer__logo"><a href="' . esc_url( $home ) . '" rel="home" aria-label="' . esc_attr( $blogname ) . '">' . $logo . '</a></div>';
			$desc   = self::get( 'description' );
			if ( $desc ) {
				$brand .= '<p class="ts-footer__desc">' . esc_html( $desc ) . '</p>';
			}
			$brand .= '</div>';

			/* --- Navigation (WordPress menu; independent from Topics) --- */
			$nav_links = wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'ts-footer__links',
				'menu_id'        => 'ts-foot-nav',
				'depth'          => 2,
				'fallback_cb'    => false,
				'echo'           => false,
			) );
			if ( ! $nav_links ) {
				$nav_links = '<ul class="ts-footer__links" id="ts-foot-nav">' . self::fallback_menu_items() . '</ul>';
			}
			$nav  = '<nav class="ts-footer__col ts-footer__col--collapsible" aria-label="' . esc_attr__( 'Footer', 'epiktetos' ) . '">';
			$nav .= self::collapsible_title( __( 'Navigate', 'epiktetos' ), 'ts-foot-nav' );
			$nav .= $nav_links;
			$nav .= '</nav>';

			/* --- Topics (dynamic categories, admin order) --- */
			$topics_html = '';
			$cats = class_exists( 'Epiktetos_Categories' )
				? Epiktetos_Categories::ordered_categories()
				: self::fallback_categories();
			$topic_links = '';
			foreach ( $cats as $cat ) {
				$topic_links .= '<li><a href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a></li>';
			}
			if ( $topic_links ) {
				$topics_html  = '<div class="ts-footer__col ts-footer__col--collapsible">';
				$topics_html .= self::collapsible_title( __( 'Topics', 'epiktetos' ), 'ts-foot-topics' );
				$topics_html .= '<ul class="ts-footer__links" id="ts-foot-topics">' . $topic_links . '</ul>';
				$topics_html .= '</div>';
			}

			/* --- Subscribe + feed --- */
			$sub  = '<div class="ts-footer__col ts-footer__subscribe">';
			$sub .= '<h2 class="ts-footer__title">' . esc_html( self::get( 'newsletter_title' ) ) . '</h2>';

			if ( self::get( 'show_newsletter' ) ) {
				$text = self::get( 'newsletter_text' );
				if ( $text ) {
					$sub .= '<p class="ts-footer__desc">' . esc_html( $text ) . '</p>';
				}
				$sub .= '<form class="ts-news" method="post" action="#" novalidate>';
				$sub .= '<div class="ts-news__field">';
				$sub .= '<input type="email" class="ts-news__input" name="email" placeholder="' . esc_attr__( 'Your email', 'epiktetos' ) . '" aria-label="' . esc_attr__( 'Email address', 'epiktetos' ) . '" inputmode="email" autocomplete="email" />';
				$sub .= '<button type="submit" class="ts-news__submit" aria-label="' . esc_attr__( 'Subscribe', 'epiktetos' ) . '">';
				$sub .= '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
				$sub .= '</button>';
				$sub .= '</div>';
				$sub .= '</form>';
				$sub .= '<p class="ts-news__note" role="status" aria-live="polite">' . esc_html__( 'Subscribe via RSS while email delivery is offline.', 'epiktetos' ) . '</p>';
			}

			// Single icon row: RSS first, then any configured social profiles.
			$sub .= self::render_icon_row();

			$sub .= '</div>';

			/* --- Bottom bar --- */
			$year      = gmdate( 'Y' );
			$credit    = self::get( 'credit' );
			$copyright = self::get( 'copyright' );
			if ( '' !== trim( (string) $copyright ) ) {
				$copy_text = strtr( $copyright, array( '%year%' => $year, '%site%' => $blogname ) );
			} else {
				$copy_text = sprintf( /* translators: 1: year 2: site name */ __( '© %1$s %2$s', 'epiktetos' ), $year, $blogname );
			}
			$bottom  = '<div class="ts-footer__bottom">';
			$bottom .= '<p class="ts-footer__copy">' . esc_html( $copy_text ) . '</p>';
			if ( $credit ) {
				$bottom .= '<p class="ts-footer__credit">' . esc_html( $credit ) . '</p>';
			}
			$bottom .= '</div>';

			$html  = '<div class="ts-footer">';
			$html .= '<div class="ts-footer__inner">';
			$html .= '<div class="ts-footer__cols">' . $brand . $nav . $topics_html . $sub . '</div>';
			$html .= $bottom;
			$html .= '</div>';
			$html .= '</div>';

			return self::compress( $html );
		}

		/**
		 * One horizontal icon row — RSS first (if enabled), then any configured
		 * social profiles. No text labels. Returns '' if nothing to show.
		 *
		 * @return string
		 */
		protected static function render_icon_row() {
			$icons = '';

			// RSS first — a feed utility, but shown in the same row per design.
			if ( self::get( 'show_rss' ) ) {
				$rss = get_bloginfo( 'rss2_url' );
				$icons .= '<a class="ts-footer__icon ts-footer__icon-rss" href="' . esc_url( $rss ) . '"'
					. ' aria-label="' . esc_attr__( 'RSS feed', 'epiktetos' ) . '">'
					. '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="6.18" cy="17.82" r="2.18"/><path d="M4 4.44v2.83c7.03 0 12.73 5.7 12.73 12.73h2.83C19.56 11.4 12.6 4.44 4 4.44z"/><path d="M4 10.1v2.83c3.9 0 7.07 3.17 7.07 7.07h2.83C13.9 14.41 9.59 10.1 4 10.1z"/></svg>'
					. '</a>';
			}

			$socials = array(
				'youtube'  => array( self::get( 'youtube_url' ), __( 'YouTube', 'epiktetos' ) ),
				'x'        => array( self::get( 'x_url' ), __( 'X (Twitter)', 'epiktetos' ) ),
				'linkedin' => array( self::get( 'linkedin_url' ), __( 'LinkedIn', 'epiktetos' ) ),
				'github'   => array( self::get( 'github_url' ), __( 'GitHub', 'epiktetos' ) ),
			);
			foreach ( $socials as $slug => $info ) {
				list( $url, $label ) = $info;
				if ( ! $url ) {
					continue;
				}
				$icons .= '<a class="ts-footer__icon ts-footer__icon-' . esc_attr( $slug ) . '"'
					. ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"'
					. ' aria-label="' . esc_attr( $label ) . '">'
					. self::icon( $slug ) . '</a>';
			}

			if ( '' === $icons ) {
				return '';
			}

			return '<div class="ts-footer__icons">' . $icons . '</div>';
		}

		/**
		 * Read an inline SVG icon from assets/icons (reusing the header
		 * subsystem's loader when available).
		 *
		 * @param string $slug Icon slug.
		 * @return string
		 */
		protected static function icon( $slug ) {
			if ( class_exists( 'Epiktetos_Header' ) && method_exists( 'Epiktetos_Header', 'inline_icon' ) ) {
				return Epiktetos_Header::inline_icon( $slug );
			}
			$slug = preg_replace( '/[^a-z0-9_-]/', '', $slug );
			$path = trailingslashit( get_template_directory() ) . 'assets/icons/' . $slug . '.svg';
			return file_exists( $path ) ? self::compress( file_get_contents( $path ) ) : '';
		}

		/**
		 * A column title that doubles as a collapse toggle on mobile.
		 * Defaults to expanded (aria-expanded="true") so the list is visible
		 * without JS; the controller collapses it on small screens.
		 *
		 * @param string $label   Visible title.
		 * @param string $list_id id of the controlled <ul>.
		 * @return string
		 */
		protected static function collapsible_title( $label, $list_id ) {
			return '<h2 class="ts-footer__title">'
				. '<button type="button" class="ts-footer__toggle" aria-expanded="true" aria-controls="' . esc_attr( $list_id ) . '">'
				. '<span>' . esc_html( $label ) . '</span>'
				. '<svg class="ts-footer__chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>'
				. '</button></h2>';
		}

		protected static function fallback_menu_items() {
			$items = array(
				array( __( 'Home', 'epiktetos' ), home_url( '/' ), is_front_page() ),
				array( __( 'Topics', 'epiktetos' ), self::page_or_fallback_url( 'topics', home_url( '/topics/' ) ), is_page( 'topics' ) ),
				array( __( 'About', 'epiktetos' ), self::page_or_fallback_url( 'about', home_url( '/about/' ) ), is_page( 'about' ) ),
			);
			$privacy = get_privacy_policy_url();
			if ( $privacy ) {
				$items[] = array( __( 'Privacy Policy', 'epiktetos' ), $privacy, is_privacy_policy() );
			}
			$items[] = array( __( 'Contact', 'epiktetos' ), self::page_or_fallback_url( 'contact', home_url( '/contact/' ) ), is_page( 'contact' ) );

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

		protected static function fallback_categories() {
			$exclude = array();
			$uncat = get_category_by_slug( 'uncategorized' );
			if ( $uncat ) {
				$exclude[] = (int) $uncat->term_id;
			}
			return get_categories( array( 'hide_empty' => true, 'exclude' => $exclude ) );
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Footer::init();
}
