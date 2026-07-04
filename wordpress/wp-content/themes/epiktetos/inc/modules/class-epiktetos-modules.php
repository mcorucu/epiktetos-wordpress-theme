<?php
/**
 * Epiktetos — editable labels for shortcode-rendered modules.
 *
 * The theme's dynamic sections (homepage Latest/Showcase/sidebar, the Topics
 * discovery index, and the About page modules) are rendered by shortcodes, so
 * their small visible labels/headings can't be edited in the block editor.
 * This exposes that copy in Appearance → Epiktetos (Homepage + Editorial tabs).
 *
 * Only reusable labels/headings/short descriptions live here — dynamic data
 * (post titles, category names, counts, dates, URLs, media) stays system-driven,
 * and page-specific prose stays in the Page block content. Every default
 * reproduces the shipped text, so output is unchanged until a field is edited.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Modules' ) ) {

	class Epiktetos_Modules {

		const OPTION = 'epiktetos_module_labels';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}

		/**
		 * Field map: key => [ label, section, type, default, (desc) ].
		 * Sections: epiktetos_home_modules (Homepage tab), epiktetos_page_modules (Editorial tab).
		 *
		 * @return array
		 */
		public static function fields() {
			return array(
				// --- Homepage modules (Latest / Showcase / sidebar) ---
				'latest_read_more'        => array( 'label' => __( 'Latest articles: “Read more” link', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Read more', 'epiktetos' ) ),
				'showcase_view_all'       => array( 'label' => __( 'Category showcase: “View all” link', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'View all', 'epiktetos' ) ),
				'showcase_cta_featured'   => array( 'label' => __( 'Category showcase: featured article button', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Read Article', 'epiktetos' ) ),
				'showcase_cta'            => array( 'label' => __( 'Category showcase: article button', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Read More', 'epiktetos' ) ),
				'stats_title'             => array( 'label' => __( 'Sidebar: Publication Stats heading', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Publication Stats', 'epiktetos' ) ),
				'stats_label_articles'    => array( 'label' => __( 'Stat label: Articles', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Articles', 'epiktetos' ) ),
				'stats_label_categories'  => array( 'label' => __( 'Stat label: Categories', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Categories', 'epiktetos' ) ),
				'stats_label_topics'      => array( 'label' => __( 'Stat label: Topics', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Topics', 'epiktetos' ) ),
				'stats_label_reading'     => array( 'label' => __( 'Stat label: Average reading time', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Average reading time', 'epiktetos' ) ),
				'stats_label_updated'     => array( 'label' => __( 'Stat label: Last updated', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Last updated', 'epiktetos' ) ),
				'stats_updated_soon'      => array( 'label' => __( 'Stat value: “Last updated” fallback', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Soon', 'epiktetos' ) ),
				'editor_picks_title'      => array( 'label' => __( 'Editor Picks heading', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Editor Picks', 'epiktetos' ) ),
				'continue_reading_title'  => array( 'label' => __( 'Sidebar: Continue Reading heading', 'epiktetos' ), 'section' => 'epiktetos_home_modules', 'type' => 'text', 'default' => __( 'Continue Reading', 'epiktetos' ) ),

				// --- Topics index module ([epiktetos_topics_index]) ---
				'topics_all_categories'       => array( 'label' => __( 'Topics: All Categories heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'All Categories', 'epiktetos' ) ),
				'topics_all_categories_empty' => array( 'label' => __( 'Topics: All Categories empty state', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'No categories have been published yet.', 'epiktetos' ) ),
				'topics_popular'              => array( 'label' => __( 'Topics: Popular Topics heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Popular Topics', 'epiktetos' ) ),
				'topics_popular_empty'        => array( 'label' => __( 'Topics: Popular Topics empty state', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'No topics have been added yet.', 'epiktetos' ) ),
				'topics_paths'                => array( 'label' => __( 'Topics: Topic Paths heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Topic Paths', 'epiktetos' ) ),

				// --- About modules ([epiktetos_about_modules]) ---
				'about_pillars_label'  => array( 'label' => __( 'About pillars: eyebrow', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Editorial Pillars', 'epiktetos' ) ),
				'about_pillars_title'  => array( 'label' => __( 'About pillars: heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Four paths through the archive.', 'epiktetos' ) ),
				'about_pillars_empty'  => array( 'label' => __( 'About pillars: empty state', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'The archive is still taking shape.', 'epiktetos' ) ),
				'about_pillars_desc'   => array( 'label' => __( 'About pillars: category description fallback', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Essays and notes filed under %s.', 'epiktetos' ), 'desc' => __( 'Used when a category has no description. %s is replaced with the category name.', 'epiktetos' ) ),
				'about_start_title'    => array( 'label' => __( 'About Start Reading: heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Start Reading', 'epiktetos' ) ),
				'about_start_desc'     => array( 'label' => __( 'About Start Reading: description', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'textarea', 'default' => __( 'The newest doorway into each major category.', 'epiktetos' ) ),
				'about_author_title'   => array( 'label' => __( 'About Author: heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Author', 'epiktetos' ) ),
				'about_author_link'    => array( 'label' => __( 'About Author: archive link', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Read the author archive', 'epiktetos' ) ),
				'about_newsletter_title' => array( 'label' => __( 'About Newsletter: heading', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'text', 'default' => __( 'Newsletter', 'epiktetos' ) ),
				'about_newsletter_copy'  => array( 'label' => __( 'About Newsletter: description', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'textarea', 'default' => __( 'A short note when something worth reading is published. No noise, no performance theatre.', 'epiktetos' ) ),
				'about_newsletter_note'  => array( 'label' => __( 'About Newsletter: status note', 'epiktetos' ), 'section' => 'epiktetos_page_modules', 'type' => 'textarea', 'default' => __( 'Subscribe via RSS while email delivery is offline.', 'epiktetos' ) ),
			);
		}

		public static function defaults() {
			$out = array();
			foreach ( self::fields() as $key => $f ) {
				$out[ $key ] = $f['default'];
			}
			return $out;
		}

		/**
		 * Get a label, falling back to the shipped default when unset or blank.
		 *
		 * @param string $key Field key.
		 * @return string
		 */
		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$def  = self::defaults();
			if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
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
				'epiktetos_home_modules',
				__( 'Homepage module labels', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Editable labels for the homepage shortcode modules — Latest Articles, the Category Showcase, and the sidebar (stats, Editor Picks, Continue Reading). Leave a field empty to restore its default.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			add_settings_section(
				'epiktetos_page_modules',
				__( 'Page module labels', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Editable labels for the Topics discovery index and the About page modules (pillars, Start Reading, Author, Newsletter). Leave a field empty to restore its default.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			foreach ( self::fields() as $key => $f ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$f['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					$f['section'],
					array_merge( array( 'key' => $key ), $f )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-module-' . $key;

			if ( isset( $args['type'] ) && 'textarea' === $args['type'] ) {
				printf( '<textarea id="%1$s" name="%2$s" rows="2" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
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
			foreach ( self::fields() as $key => $f ) {
				$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
				if ( '' === trim( $raw ) ) {
					$out[ $key ] = $def[ $key ];
				} elseif ( isset( $f['type'] ) && 'textarea' === $f['type'] ) {
					$out[ $key ] = sanitize_textarea_field( $raw );
				} else {
					$out[ $key ] = sanitize_text_field( $raw );
				}
			}
			return $out;
		}
	}

	Epiktetos_Modules::init();
}
