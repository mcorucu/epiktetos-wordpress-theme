<?php
/**
 * Epiktetos — editable homepage copy.
 *
 * Moves the previously hard-coded homepage editorial copy (hero button label,
 * the "Latest Articles" heading, the "View all" link text, and the sidebar
 * Editor's Note) into Appearance → Epiktetos → Homepage. Every default here
 * reproduces the shipped copy verbatim, so the rendered homepage is unchanged
 * until an editor overrides a field. The hero slides, latest posts, topics, and
 * publication stats stay fully dynamic.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Homepage' ) ) {

	class Epiktetos_Homepage {

		const OPTION = 'epiktetos_home_options';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_front_page', array( __CLASS__, 'render_front_page' ) );
		}

		/**
		 * Defaults for the small homepage labels that live inside dynamic modules
		 * (hero button, Latest Articles heading, "View all", sidebar Topics title).
		 * The Editor's Note and any homepage prose live in the Home page content.
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'hero_cta_label'       => __( 'Read article', 'epiktetos' ),
				'latest_title'         => __( 'Latest Articles', 'epiktetos' ),
				'viewall_text'         => __( 'View all', 'epiktetos' ),
				'sidebar_topics_title' => __( 'Topics', 'epiktetos' ),
			);
		}

		/**
		 * Get one value, falling back to the default when unset or blank.
		 *
		 * @param string $key Option key.
		 * @return string
		 */
		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$def  = self::defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) && '' !== $opts[ $key ] ) {
				return $opts[ $key ];
			}
			return isset( $def[ $key ] ) ? $def[ $key ] : '';
		}

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
				'epiktetos_home',
				__( 'Homepage', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Small labels for the homepage’s dynamic modules (hero button, Latest Articles heading, “View all”, and the sidebar Topics title). The homepage layout and its editable prose — including the Editor’s Note — are edited on the Home page in the block editor.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'hero_cta_label'       => array( 'label' => __( 'Hero button label', 'epiktetos' ), 'type' => 'text' ),
				'latest_title'         => array( 'label' => __( 'Latest Articles heading', 'epiktetos' ), 'type' => 'text' ),
				'viewall_text'         => array( 'label' => __( '“View all” link text', 'epiktetos' ), 'type' => 'text' ),
				'sidebar_topics_title' => array( 'label' => __( 'Sidebar Topics title', 'epiktetos' ), 'type' => 'text' ),
			);
			foreach ( $fields as $key => $f ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$f['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_home',
					array_merge( array( 'key' => $key ), $f )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-home-' . $key;

			if ( 'textarea' === $args['type'] ) {
				printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			} else {
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			}
			if ( ! empty( $args['desc'] ) ) {
				echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
			}
		}

		public static function sanitize( $input ) {
			$def = self::defaults();
			$out = array();
			foreach ( array( 'hero_cta_label', 'latest_title', 'viewall_text', 'sidebar_topics_title' ) as $key ) {
				$out[ $key ] = isset( $input[ $key ] ) && '' !== trim( (string) $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $def[ $key ];
			}
			return $out;
		}

		/**
		 * Front-page body. When a static Home page is set, its Gutenberg content
		 * is rendered (so the homepage is editable in the block editor). Otherwise
		 * the default homepage structure is rendered so the theme still works with
		 * "Your latest posts" as the front page.
		 *
		 * @return string
		 */
		public static function render_front_page() {
			if ( 'page' === get_option( 'show_on_front' ) ) {
				$front_id = (int) get_option( 'page_on_front' );
				$post     = $front_id ? get_post( $front_id ) : null;
				if ( $post instanceof WP_Post && '' !== trim( (string) $post->post_content ) ) {
					setup_postdata( $post );
					$content = apply_filters( 'the_content', $post->post_content );
					wp_reset_postdata();
					return $content;
				}
			}
			return do_shortcode( self::home_default_content() );
		}

		/**
		 * Default Home page content as Gutenberg blocks: the hero, the two-column
		 * home grid (latest + category showcase in the main column; an editable
		 * Editor's Note plus the dynamic sidebar in the aside). Seeds the Home page
		 * and acts as the fallback for a "latest posts" front page.
		 *
		 * @return string
		 */
		public static function home_default_content() {
			$note_title = __( 'Editor’s Note', 'epiktetos' );
			$note_text  = __( 'Epiktetos is a quiet journal on technology, philosophy, psychology, and history — slow essays for the reader who arrives unhurried, and stays.', 'epiktetos' );

			$note  = '<!-- wp:group {"className":"ts-side ts-side--note","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group ts-side ts-side--note">';
			$note .= '<!-- wp:heading {"level":3,"className":"ts-side__title"} -->' . "\n" . '<h3 class="wp-block-heading ts-side__title">' . esc_html( $note_title ) . '</h3>' . "\n" . '<!-- /wp:heading -->';
			$note .= "\n\n" . '<!-- wp:paragraph {"className":"ts-side__text"} -->' . "\n" . '<p class="ts-side__text">' . esc_html( $note_text ) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
			$note .= '</div>' . "\n" . '<!-- /wp:group -->';

			$blocks = array(
				'<!-- wp:shortcode -->' . "\n" . '[epiktetos_hero]' . "\n" . '<!-- /wp:shortcode -->',
				'<!-- wp:group {"className":"ts-home","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group ts-home">'
					. '<!-- wp:group {"className":"ts-home__content","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group ts-home__content">'
					. '<!-- wp:shortcode -->' . "\n" . '[epiktetos_latest_articles]' . "\n" . '<!-- /wp:shortcode -->'
					. "\n\n" . '<!-- wp:shortcode -->' . "\n" . '[epiktetos_category_showcase]' . "\n" . '<!-- /wp:shortcode -->'
					. '</div>' . "\n" . '<!-- /wp:group -->'
					. "\n\n" . '<!-- wp:group {"tagName":"div","className":"ts-home__aside","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group ts-home__aside">'
					. $note
					. "\n\n" . '<!-- wp:shortcode -->' . "\n" . '[epiktetos_sidebar]' . "\n" . '<!-- /wp:shortcode -->'
					. '</div>' . "\n" . '<!-- /wp:group -->'
					. '</div>' . "\n" . '<!-- /wp:group -->',
			);
			return implode( "\n\n", $blocks );
		}
	}

	Epiktetos_Homepage::init();
}
