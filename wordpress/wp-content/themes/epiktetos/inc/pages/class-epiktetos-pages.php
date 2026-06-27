<?php
/**
 * Epiktetos - author and static page experience.
 *
 * Owns publication identity surfaces: author archives, About, Contact, and
 * the reusable editorial page shell.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Pages' ) ) {

	class Epiktetos_Pages {

		const ABOUT_OPTION = 'epiktetos_about_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_about_settings' ) );
			add_filter( 'pre_handle_404', array( __CLASS__, 'allow_empty_author_archives' ), 10, 2 );
		}

		public static function register_shortcodes() {
			add_shortcode( 'epiktetos_author', array( __CLASS__, 'render_author_archive' ) );
			add_shortcode( 'epiktetos_page', array( __CLASS__, 'render_page' ) );
			add_shortcode( 'epiktetos_about', array( __CLASS__, 'render_about' ) );
			add_shortcode( 'epiktetos_contact', array( __CLASS__, 'render_contact' ) );
			add_shortcode( 'epiktetos_404', array( __CLASS__, 'render_404' ) );
		}

		public static function about_defaults() {
			return array(
				'headline'              => __( 'A quiet journal for technology, philosophy, psychology and history.', 'epiktetos' ),
				'intro'                 => __( 'Epiktetos is built for slower thinking in a faster web — essays that prefer depth over noise.', 'epiktetos' ),
				'mission_text'          => __( 'The mission is simple: make room for durable questions, careful language, and ideas that can be returned to without losing their shape.', 'epiktetos' ),
				'what_is_text'          => __( 'Epiktetos is an independent editorial space for essays, notes, and reflections on systems, attention, culture, and the examined life.', 'epiktetos' ),
				'why_exists_text'       => __( 'The web rewards reaction. Epiktetos exists to practice a different rhythm: slower reading, clearer thinking, and essays that do not need to become noise.', 'epiktetos' ),
				'principles'            => "Depth over velocity\nClarity over spectacle\nReading over scrolling\nSystems over trends\nHuman consequences over empty novelty",
				'show_editorial_pillars' => 1,
				'show_start_reading'    => 1,
				'show_author_section'   => 1,
				'show_newsletter_section' => 1,
				'colophon_text'         => __( 'Built by Mehmet Can Orucu. Powered by WordPress.', 'epiktetos' ),
				'about_page_id'         => 0,
			);
		}

		public static function about_get( $key ) {
			$opts = get_option( self::ABOUT_OPTION, array() );
			$defs = self::about_defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) && '' !== $opts[ $key ] ) {
				return $opts[ $key ];
			}
			return isset( $defs[ $key ] ) ? $defs[ $key ] : null;
		}

		public static function register_about_settings() {
			register_setting(
				'epiktetos_settings',
				self::ABOUT_OPTION,
				array(
					'sanitize_callback' => array( __CLASS__, 'sanitize_about' ),
					'default'           => self::about_defaults(),
				)
			);

			add_settings_section(
				'epiktetos_about',
				__( 'About Page', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Shape the About page manifesto while preserving editable WordPress page content.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'headline'              => array( 'label' => __( 'About headline', 'epiktetos' ), 'type' => 'text' ),
				'intro'                 => array( 'label' => __( 'About intro', 'epiktetos' ), 'type' => 'textarea' ),
				'mission_text'          => array( 'label' => __( 'Mission text', 'epiktetos' ), 'type' => 'textarea' ),
				'what_is_text'          => array( 'label' => __( 'What Epiktetos Is text', 'epiktetos' ), 'type' => 'textarea' ),
				'why_exists_text'       => array( 'label' => __( 'Why It Exists text', 'epiktetos' ), 'type' => 'textarea' ),
				'principles'            => array( 'label' => __( 'Editorial principles', 'epiktetos' ), 'type' => 'textarea', 'desc' => __( 'One principle per line.', 'epiktetos' ) ),
				'show_editorial_pillars' => array( 'label' => __( 'Show editorial pillars', 'epiktetos' ), 'type' => 'checkbox' ),
				'show_start_reading'    => array( 'label' => __( 'Show Start Reading', 'epiktetos' ), 'type' => 'checkbox' ),
				'show_author_section'   => array( 'label' => __( 'Show author section', 'epiktetos' ), 'type' => 'checkbox' ),
				'show_newsletter_section' => array( 'label' => __( 'Show newsletter section', 'epiktetos' ), 'type' => 'checkbox' ),
				'colophon_text'         => array( 'label' => __( 'Colophon text', 'epiktetos' ), 'type' => 'textarea' ),
				'about_page_id'         => array( 'label' => __( 'About page', 'epiktetos' ), 'type' => 'page', 'desc' => __( 'Optional reference page for editors. The About template still renders wherever the shortcode is used.', 'epiktetos' ) ),
			);

			foreach ( $fields as $key => $field ) {
				add_settings_field(
					self::ABOUT_OPTION . '_' . $key,
					$field['label'],
					array( __CLASS__, 'render_about_field' ),
					'epiktetos-settings',
					'epiktetos_about',
					array_merge( array( 'key' => $key ), $field )
				);
			}
		}

		public static function render_about_field( $args ) {
			$key = $args['key'];
			$value = self::about_get( $key );
			$name = self::ABOUT_OPTION . '[' . $key . ']';
			$id = 'epiktetos-about-' . $key;

			if ( 'checkbox' === $args['type'] ) {
				printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( 1, (int) $value, false ), esc_html__( 'Enabled', 'epiktetos' ) );
			} elseif ( 'page' === $args['type'] ) {
				wp_dropdown_pages(
					array(
						'name'              => $name,
						'id'                => $id,
						'selected'          => (int) $value,
						'show_option_none'  => __( 'Select a page', 'epiktetos' ),
						'option_none_value' => 0,
					)
				);
			} elseif ( 'number' === $args['type'] ) {
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" min="0" step="1" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (int) $value ) );
			} elseif ( 'textarea' === $args['type'] ) {
				printf( '<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			} else {
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			}
			if ( ! empty( $args['desc'] ) ) {
				echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
			}
		}

		public static function sanitize_about( $input ) {
			$defs = self::about_defaults();
			$out = array();
			foreach ( array( 'headline', 'intro', 'mission_text', 'what_is_text', 'why_exists_text', 'principles', 'colophon_text' ) as $key ) {
				$out[ $key ] = isset( $input[ $key ] ) && '' !== trim( (string) $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : $defs[ $key ];
			}
			foreach ( array( 'show_editorial_pillars', 'show_start_reading', 'show_author_section', 'show_newsletter_section' ) as $key ) {
				$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
			}
			$out['about_page_id'] = isset( $input['about_page_id'] ) ? max( 0, (int) $input['about_page_id'] ) : 0;
			return $out;
		}

		/**
		 * Let valid zero-post author archives render the editorial empty state.
		 *
		 * @param bool     $preempt  Whether to short-circuit 404 handling.
		 * @param WP_Query $wp_query Main query.
		 * @return bool
		 */
		public static function allow_empty_author_archives( $preempt, $wp_query ) {
			if ( ! $wp_query instanceof WP_Query || ! $wp_query->is_author() || $wp_query->have_posts() ) {
				return $preempt;
			}

			$author_id = (int) $wp_query->get( 'author' );
			if ( ! $author_id ) {
				$author_id = (int) get_queried_object_id();
			}

			if ( ! $author_id || ! get_user_by( 'id', $author_id ) ) {
				return $preempt;
			}

			$wp_query->is_404 = false;
			status_header( 200 );

			return true;
		}

		/**
		 * Author archive page.
		 *
		 * @return string
		 */
		public static function render_author_archive() {
			if ( ! is_author() ) {
				return '';
			}

			$author = get_queried_object();
			if ( ! $author instanceof WP_User ) {
				$author = get_user_by( 'id', (int) get_queried_object_id() );
			}
			if ( ! $author instanceof WP_User ) {
				return '';
			}

			global $wp_query;
			$posts = ( $wp_query instanceof WP_Query && is_array( $wp_query->posts ) ) ? $wp_query->posts : array();
			$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
			$count = self::author_article_count( (int) $author->ID );

			$html  = '<section class="ts-author-page" aria-labelledby="ts-author-page-title">';
			$html .= '<div class="ts-author-page__inner">';
			$html .= self::render_author_header( $author, $count );
			$html .= self::render_author_distribution( (int) $author->ID );

			if ( empty( $posts ) ) {
				$html .= '<div class="ts-author-page__empty" role="status"><p>' . esc_html__( 'No articles have been published here yet.', 'epiktetos' ) . '</p></div>';
			} else {
				$html .= '<section class="ts-archive-list ts-author-page__list" aria-label="' . esc_attr__( 'Author articles', 'epiktetos' ) . '">';
				$html .= '<div class="ts-archive-list__rows">';
				foreach ( $posts as $post ) {
					$html .= self::render_archive_row( $post );
				}
				$html .= '</div>';
				$html .= '</section>';
				if ( class_exists( 'Epiktetos_Archive' ) && method_exists( 'Epiktetos_Archive', 'render_pagination' ) && $wp_query instanceof WP_Query ) {
					$html .= Epiktetos_Archive::render_pagination( $wp_query, $paged );
				}
			}

			$html .= '</div>';
			$html .= '</section>';

			return self::compress( $html );
		}

		protected static function render_author_header( $author, $count ) {
			$name = $author->display_name ? $author->display_name : $author->user_login;
			$bio  = get_the_author_meta( 'description', (int) $author->ID );
			if ( ! $bio ) {
				$bio = __( 'Essays from the Epiktetos archive.', 'epiktetos' );
			}

			$html  = '<header class="ts-author-page__header">';
			$html .= '<div class="ts-author-page__avatar">' . self::author_avatar( (int) $author->ID, 128, $name ) . '</div>';
			$html .= '<div class="ts-author-page__identity">';
			$html .= '<h1 class="ts-author-page__title" id="ts-author-page-title">' . esc_html( $name ) . '</h1>';
			$html .= '<p class="ts-author-page__bio">' . esc_html( $bio ) . '</p>';
			$html .= '<p class="ts-author-page__count">' . esc_html( self::article_count_label( $count ) ) . '</p>';
			$html .= '</div>';
			$html .= '</header>';

			return $html;
		}

		protected static function render_author_distribution( $author_id ) {
			$items = self::author_category_distribution( $author_id );
			if ( empty( $items ) ) {
				return '';
			}

			$html  = '<aside class="ts-author-topics" aria-labelledby="ts-author-topics-title">';
			$html .= '<h2 id="ts-author-topics-title">' . esc_html__( 'Topics', 'epiktetos' ) . '</h2>';
			$html .= '<ul>';
			foreach ( $items as $item ) {
				$cat = $item['category'];
				$html .= '<li><a href="' . esc_url( get_category_link( $cat->term_id ) ) . '"><span>' . esc_html( $cat->name ) . '</span><span>' . esc_html( self::article_count_label( (int) $item['count'], false ) ) . '</span></a></li>';
			}
			$html .= '</ul>';
			$html .= '</aside>';

			return $html;
		}

		/**
		 * Generic static page shell.
		 *
		 * @return string
		 */
		public static function render_page() {
			if ( ! is_page() ) {
				return '';
			}

			$post = get_post();
			if ( ! $post instanceof WP_Post ) {
				return '';
			}

			setup_postdata( $post );
			$title   = get_the_title( $post );
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
			$content = apply_filters( 'the_content', $post->post_content );
			wp_reset_postdata();

			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				$content = '<p>' . esc_html__( 'This page is being prepared.', 'epiktetos' ) . '</p>';
			}

			$html  = '<article class="ts-page" aria-labelledby="ts-page-title">';
			$html .= '<div class="ts-page__inner">';
			$html .= '<header class="ts-page__header">';
			$html .= '<h1 class="ts-page__title" id="ts-page-title">' . esc_html( $title ) . '</h1>';
			if ( $excerpt ) {
				$html .= '<p class="ts-page__dek">' . esc_html( $excerpt ) . '</p>';
			}
			$html .= '</header>';
			$html .= '<div class="ts-page__content ts-article__body">' . $content . '</div>';
			$html .= '</div>';
			$html .= '</article>';

			return self::compress_outer( $html );
		}

		/**
		 * About page.
		 *
		 * @return string
		 */
		public static function render_about() {
			if ( ! is_page() ) {
				return '';
			}

			$post      = get_post();
			$author_id = self::publication_author_id();
			$settings  = array(
				'headline'              => self::about_get( 'headline' ),
				'intro'                 => self::about_get( 'intro' ),
				'mission_text'          => self::about_get( 'mission_text' ),
				'what_is_text'          => self::about_get( 'what_is_text' ),
				'why_exists_text'       => self::about_get( 'why_exists_text' ),
				'principles'            => self::about_get( 'principles' ),
				'show_editorial_pillars' => (int) self::about_get( 'show_editorial_pillars' ),
				'show_start_reading'    => (int) self::about_get( 'show_start_reading' ),
				'show_author_section'   => (int) self::about_get( 'show_author_section' ),
				'show_newsletter_section' => (int) self::about_get( 'show_newsletter_section' ),
				'colophon_text'         => self::about_get( 'colophon_text' ),
			);

			$html  = '<article class="ts-page ts-about" aria-labelledby="ts-about-title">';
			$html .= '<div class="ts-page__inner">';
			$html .= '<header class="ts-page__header ts-about__header">';
			$html .= '<p class="ts-page__eyebrow">' . esc_html__( 'About the publication', 'epiktetos' ) . '</p>';
			$html .= '<h1 class="ts-page__title" id="ts-about-title">' . esc_html( $settings['headline'] ) . '</h1>';
			$html .= '<p class="ts-page__dek">' . esc_html( $settings['intro'] ) . '</p>';
			$html .= '</header>';

			$html .= self::render_about_nav( $settings );
			$html .= self::render_about_manifesto( $settings );
			$html .= self::render_about_editor_content( $post );

			$html .= '<div class="ts-about__grid">';
			$html .= self::about_section( __( 'What Epiktetos Is', 'epiktetos' ), $settings['what_is_text'], 'ts-about-what' );
			$html .= self::about_section( __( 'Why It Exists', 'epiktetos' ), $settings['why_exists_text'], 'ts-about-why' );
			$html .= '</div>';

			if ( $settings['show_editorial_pillars'] ) {
				$html .= self::render_about_pillars();
			}
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$html .= Epiktetos_Reader::editor_picks_module( 'about', 3 );
			}
			$html .= self::render_about_principles( $settings['principles'] );
			if ( $settings['show_start_reading'] ) {
				$html .= self::render_start_here();
			}
			if ( $settings['show_author_section'] ) {
				$html .= self::render_about_author( $author_id );
			}
			if ( $settings['show_newsletter_section'] ) {
				$html .= self::render_page_newsletter();
			}
			$html .= self::render_about_colophon( $settings['colophon_text'] );
			$html .= '</div>';
			$html .= '</article>';

			return self::compress_outer( $html );
		}

		/**
		 * Contact page.
		 *
		 * @return string
		 */
		public static function render_contact() {
			if ( ! is_page() ) {
				return '';
			}

			$email = 'hello@epiktetos.local';

			$html  = '<article class="ts-page ts-contact" aria-labelledby="ts-contact-title">';
			$html .= '<div class="ts-page__inner">';
			$html .= '<header class="ts-page__header">';
			$html .= '<p class="ts-page__eyebrow">' . esc_html__( 'Contact', 'epiktetos' ) . '</p>';
			$html .= '<h1 class="ts-page__title" id="ts-contact-title">' . esc_html__( 'A quiet line in.', 'epiktetos' ) . '</h1>';
			$html .= '<p class="ts-page__dek">' . esc_html__( 'For notes, thoughtful responses, corrections, and future collaboration ideas.', 'epiktetos' ) . '</p>';
			$html .= '</header>';
			$html .= '<section class="ts-contact__panel" aria-labelledby="ts-contact-email-title">';
			$html .= '<h2 id="ts-contact-email-title">' . esc_html__( 'Email', 'epiktetos' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'The contact form will arrive later. For now, this placeholder keeps the page ready without adding unnecessary machinery.', 'epiktetos' ) . '</p>';
			$html .= '<p class="ts-contact__email-line"><a class="ts-contact__email" href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
			$html .= '</section>';
			$html .= self::render_social_links();
			$html .= '</div>';
			$html .= '</article>';

			return self::compress( $html );
		}

		/**
		 * 404 page.
		 *
		 * @return string
		 */
		public static function render_404() {
			if ( ! is_404() ) {
				return '';
			}

			$html  = '<article class="ts-page ts-not-found" aria-labelledby="ts-not-found-title">';
			$html .= '<div class="ts-page__inner">';
			$html .= '<header class="ts-page__header">';
			$html .= '<p class="ts-page__eyebrow">' . esc_html__( '404', 'epiktetos' ) . '</p>';
			$html .= '<h1 class="ts-page__title" id="ts-not-found-title">' . esc_html__( 'This page drifted out of view.', 'epiktetos' ) . '</h1>';
			$html .= '<p class="ts-page__dek">' . esc_html__( 'The address may have changed, or the article may no longer be available. The archive is still a good place to continue reading.', 'epiktetos' ) . '</p>';
			$html .= '</header>';
			$html .= '<nav class="ts-page__content" aria-label="' . esc_attr__( 'Not found navigation', 'epiktetos' ) . '">';
			$html .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Return home', 'epiktetos' ) . '</a></p>';
			$html .= '<p><a href="' . esc_url( home_url( '/?s=' ) ) . '">' . esc_html__( 'Search the archive', 'epiktetos' ) . '</a></p>';
			$html .= '</nav>';
			$html .= '</div>';
			$html .= '</article>';

			return self::compress( $html );
		}

		public static function render_single_author_box( $author_id ) {
			$name = get_the_author_meta( 'display_name', $author_id );
			if ( ! $name ) {
				return '';
			}

			$bio   = get_the_author_meta( 'description', $author_id );
			$url   = get_author_posts_url( $author_id );
			$count = self::author_article_count( $author_id );

			$html  = '<div class="ts-author ts-author--rich">';
			$html .= '<div class="ts-author__avatar">' . self::author_avatar( $author_id, 72, $name ) . '</div>';
			$html .= '<div class="ts-author__body">';
			$html .= '<p class="ts-author__eyebrow">' . esc_html__( 'Written by', 'epiktetos' ) . '</p>';
			$html .= '<p class="ts-author__name"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></p>';
			if ( $bio ) {
				$html .= '<p class="ts-author__bio">' . esc_html( $bio ) . '</p>';
			}
			$html .= '<p class="ts-author__meta">' . esc_html( self::article_count_label( $count ) ) . '</p>';
			$html .= '<p class="ts-author__archive"><a href="' . esc_url( $url ) . '">' . esc_html__( 'View author archive', 'epiktetos' ) . '</a></p>';
			$html .= '</div>';
			$html .= '</div>';

			return $html;
		}

		public static function author_article_count( $author_id ) {
			return (int) count_user_posts( (int) $author_id, 'post', true );
		}

		public static function author_avatar( $author_id, $size, $name = '' ) {
			return get_avatar(
				(int) $author_id,
				(int) $size,
				'',
				$name,
				array(
					'class'         => 'ts-avatar',
					'force_display' => true,
				)
			);
		}

		protected static function about_section( $title, $copy, $id = '' ) {
			$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';
			return '<section class="ts-about__section"' . $id_attr . '><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $copy ) . '</p></section>';
		}

		protected static function render_about_nav( $settings ) {
			$items = array(
				'ts-about-mission' => __( 'Mission', 'epiktetos' ),
				'ts-about-what'    => __( 'What', 'epiktetos' ),
				'ts-about-why'     => __( 'Why', 'epiktetos' ),
			);
			if ( ! empty( $settings['show_editorial_pillars'] ) ) {
				$items['ts-about-pillars-title'] = __( 'Pillars', 'epiktetos' );
			}
			$items['ts-about-principles-title'] = __( 'Principles', 'epiktetos' );
			if ( ! empty( $settings['show_start_reading'] ) ) {
				$items['ts-start-here-title'] = __( 'Start Reading', 'epiktetos' );
			}
			if ( ! empty( $settings['show_author_section'] ) ) {
				$items['ts-about-author-title'] = __( 'Author', 'epiktetos' );
			}

			$html = '<nav class="ts-about__nav" aria-label="' . esc_attr__( 'About page sections', 'epiktetos' ) . '">';
			foreach ( $items as $target => $label ) {
				$html .= '<a href="#' . esc_attr( $target ) . '">' . esc_html( $label ) . '</a>';
			}
			$html .= '</nav>';

			return $html;
		}

		protected static function render_about_manifesto( $settings ) {
			$html  = '<section class="ts-about__manifesto" id="ts-about-mission" aria-labelledby="ts-about-mission-title">';
			$html .= '<p class="ts-about__label">' . esc_html__( 'Opening Manifesto', 'epiktetos' ) . '</p>';
			$html .= '<h2 id="ts-about-mission-title">' . esc_html__( 'A place for slower thought.', 'epiktetos' ) . '</h2>';
			$html .= '<p>' . esc_html( $settings['mission_text'] ) . '</p>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_about_editor_content( $post ) {
			if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_content ) ) {
				return '';
			}

			$raw = preg_replace( '/\[epiktetos_about[^\]]*\]/i', '', (string) $post->post_content );
			if ( '' === trim( $raw ) ) {
				return '';
			}

			setup_postdata( $post );
			$content = apply_filters( 'the_content', $raw );
			wp_reset_postdata();

			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				return '';
			}

			$html  = '<section class="ts-about__editor ts-article__body" aria-label="' . esc_attr__( 'About page editor content', 'epiktetos' ) . '">';
			$html .= $content;
			$html .= '</section>';

			return $html;
		}

		protected static function render_about_pillars() {
			$cats = self::ordered_categories( 4 );

			$html  = '<section class="ts-about-pillars" aria-labelledby="ts-about-pillars-title">';
			$html .= '<div class="ts-about-pillars__head">';
			$html .= '<p class="ts-about__label">' . esc_html__( 'Editorial Pillars', 'epiktetos' ) . '</p>';
			$html .= '<h2 id="ts-about-pillars-title">' . esc_html__( 'Four paths through the archive.', 'epiktetos' ) . '</h2>';
			$html .= '</div>';
			if ( empty( $cats ) ) {
				$html .= '<p class="ts-about-pillars__empty">' . esc_html__( 'The archive is still taking shape.', 'epiktetos' ) . '</p>';
			} else {
				$html .= '<div class="ts-about-pillars__grid">';
				foreach ( $cats as $cat ) {
					$desc = term_description( $cat->term_id, 'category' );
					$desc = $desc ? trim( wp_strip_all_tags( $desc ) ) : sprintf( __( 'Essays and notes filed under %s.', 'epiktetos' ), $cat->name );
					$html .= '<article class="ts-about-pillar">';
					$html .= '<p class="ts-about-pillar__count">' . esc_html( self::article_count_label( (int) $cat->count ) ) . '</p>';
					$html .= '<h3><a href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a></h3>';
					$html .= '<p>' . esc_html( $desc ) . '</p>';
					$html .= '</article>';
				}
				$html .= '</div>';
			}
			$html .= '</section>';

			return $html;
		}

		protected static function render_about_principles( $principles ) {
			$items = array_filter( array_map( 'trim', preg_split( '/\R+/', (string) $principles ) ) );
			if ( empty( $items ) ) {
				return '';
			}

			$html  = '<section class="ts-about-principles" aria-labelledby="ts-about-principles-title">';
			$html .= '<h2 id="ts-about-principles-title">' . esc_html__( 'Editorial principles', 'epiktetos' ) . '</h2>';
			$html .= '<ul>';
			foreach ( $items as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_start_here() {
			$items = self::latest_posts_by_category( self::ordered_categories( 4 ) );
			if ( empty( $items ) ) {
				return '';
			}

			$html  = '<section class="ts-start-here" aria-labelledby="ts-start-here-title">';
			$html .= '<div class="ts-start-here__head">';
			$html .= '<h2 id="ts-start-here-title">' . esc_html__( 'Start Reading', 'epiktetos' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'The newest doorway into each major category.', 'epiktetos' ) . '</p>';
			$html .= '</div>';
			$html .= '<div class="ts-start-here__list">';
			foreach ( $items as $item ) {
				$post = $item['post'];
				$cat  = $item['category'];
				$html .= '<article class="ts-start-item">';
				$html .= '<p class="ts-start-item__meta">' . ( $cat ? esc_html( $cat->name ) . ' / ' : '' ) . esc_html( self::reading_time_label( $post ) ) . '</p>';
				$html .= '<h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
				$html .= '</article>';
			}
			$html .= '</div>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_about_colophon( $copy ) {
			if ( '' === trim( (string) $copy ) ) {
				return '';
			}

			return '<section class="ts-about-colophon" aria-labelledby="ts-about-colophon-title"><h2 id="ts-about-colophon-title">' . esc_html__( 'Colophon', 'epiktetos' ) . '</h2><p>' . esc_html( $copy ) . '</p></section>';
		}

		protected static function render_about_author( $author_id ) {
			if ( ! $author_id ) {
				return '';
			}

			$name = get_the_author_meta( 'display_name', $author_id );
			$bio  = get_the_author_meta( 'description', $author_id );
			if ( ! $name ) {
				return '';
			}

			$html  = '<section class="ts-about-author" aria-labelledby="ts-about-author-title">';
			$html .= '<div class="ts-about-author__avatar">' . self::author_avatar( $author_id, 88, $name ) . '</div>';
			$html .= '<div>';
			$html .= '<h2 id="ts-about-author-title">' . esc_html__( 'Author', 'epiktetos' ) . '</h2>';
			$html .= '<p class="ts-about-author__name">' . esc_html( $name ) . '</p>';
			if ( $bio ) {
				$html .= '<p>' . esc_html( $bio ) . '</p>';
			}
			$html .= '<p class="ts-about-author__archive"><a href="' . esc_url( get_author_posts_url( $author_id ) ) . '">' . esc_html__( 'Read the author archive', 'epiktetos' ) . '</a></p>';
			$html .= '</div>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_page_newsletter() {
			return '<section class="ts-page-newsletter" aria-labelledby="ts-page-newsletter-title"><h2 id="ts-page-newsletter-title">' . esc_html__( 'Newsletter', 'epiktetos' ) . '</h2><p>' . esc_html__( 'A short note when something worth reading is published. No noise, no performance theatre.', 'epiktetos' ) . '</p><form class="ts-news" method="post" action="#" novalidate><div class="ts-news__field"><input type="email" class="ts-news__input" name="email" placeholder="' . esc_attr__( 'Your email', 'epiktetos' ) . '" aria-label="' . esc_attr__( 'Email address', 'epiktetos' ) . '" inputmode="email" autocomplete="email" /><button type="submit" class="ts-news__submit" aria-label="' . esc_attr__( 'Subscribe', 'epiktetos' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button></div></form><p class="ts-news__note" role="status" aria-live="polite">' . esc_html__( 'Subscribe via RSS while email delivery is offline.', 'epiktetos' ) . '</p></section>';
		}

		protected static function render_social_links() {
			if ( ! class_exists( 'Epiktetos_Footer' ) || ! method_exists( 'Epiktetos_Footer', 'get' ) ) {
				return '';
			}

			$items = array(
				'youtube'  => __( 'YouTube', 'epiktetos' ),
				'x'        => __( 'X', 'epiktetos' ),
				'linkedin' => __( 'LinkedIn', 'epiktetos' ),
				'github'   => __( 'GitHub', 'epiktetos' ),
			);

			$links = '';
			foreach ( $items as $key => $label ) {
				$url = Epiktetos_Footer::get( $key . '_url' );
				if ( ! $url ) {
					continue;
				}
				$links .= '<a href="' . esc_url( $url ) . '" rel="me noopener noreferrer" target="_blank">' . esc_html( $label ) . '</a>';
			}

			if ( ! $links ) {
				return '';
			}

			return '<nav class="ts-contact__social" aria-label="' . esc_attr__( 'Social links', 'epiktetos' ) . '">' . $links . '</nav>';
		}

		protected static function render_archive_row( $post ) {
			if ( class_exists( 'Epiktetos_Archive' ) && method_exists( 'Epiktetos_Archive', 'render_row' ) ) {
				return Epiktetos_Archive::render_row( $post );
			}

			$title = get_the_title( $post );
			return '<article class="ts-archive-row"><div class="ts-archive-row__body"><h2 class="ts-archive-row__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></h2></div></article>';
		}

		protected static function publication_author_id() {
			$post = get_post();
			if ( $post instanceof WP_Post && $post->post_author ) {
				return (int) $post->post_author;
			}

			$users = get_users( array(
				'number'              => 1,
				'has_published_posts' => array( 'post' ),
				'orderby'             => 'post_count',
				'order'               => 'DESC',
				'fields'              => array( 'ID' ),
			) );

			return ! empty( $users ) ? (int) $users[0]->ID : 0;
		}

		protected static function author_category_distribution( $author_id ) {
			$ids = get_posts( array(
				'author'              => (int) $author_id,
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			) );

			if ( empty( $ids ) ) {
				return array();
			}

			$counts = array();
			foreach ( $ids as $id ) {
				$cat = self::primary_category( get_post( $id ) );
				if ( ! $cat ) {
					continue;
				}
				$key = (int) $cat->term_id;
				if ( ! isset( $counts[ $key ] ) ) {
					$counts[ $key ] = array( 'category' => $cat, 'count' => 0 );
				}
				$counts[ $key ]['count']++;
			}

			$ordered = array();
			foreach ( self::ordered_categories() as $cat ) {
				$key = (int) $cat->term_id;
				if ( isset( $counts[ $key ] ) ) {
					$ordered[] = $counts[ $key ];
					unset( $counts[ $key ] );
				}
			}
			foreach ( $counts as $item ) {
				$ordered[] = $item;
			}

			return array_slice( $ordered, 0, 6 );
		}

		protected static function recent_posts( $limit ) {
			return get_posts( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => (int) $limit,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			) );
		}

		protected static function latest_posts_by_category( $categories ) {
			if ( empty( $categories ) || ! is_array( $categories ) ) {
				return array();
			}

			$items = array();
			foreach ( array_slice( $categories, 0, 4 ) as $cat ) {
				if ( ! $cat instanceof WP_Term ) {
					continue;
				}
				$posts = get_posts(
					array(
						'post_type'           => 'post',
						'post_status'         => 'publish',
						'posts_per_page'      => 1,
						'cat'                 => (int) $cat->term_id,
						'ignore_sticky_posts' => true,
						'no_found_rows'       => true,
						'suppress_filters'    => false,
					)
				);
				if ( empty( $posts ) ) {
					continue;
				}
				$items[] = array(
					'category' => $cat,
					'post'     => $posts[0],
				);
			}

			return $items;
		}

		protected static function ordered_categories( $limit = 0 ) {
			$cats = class_exists( 'Epiktetos_Categories' )
				? Epiktetos_Categories::ordered_categories()
				: get_categories( array( 'hide_empty' => true ) );

			$cats = is_array( $cats ) ? $cats : array();
			return $limit ? array_slice( $cats, 0, (int) $limit ) : $cats;
		}

		protected static function primary_category( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return null;
			}

			$cats = get_the_category( $post->ID );
			if ( empty( $cats ) ) {
				return null;
			}
			if ( 1 === count( $cats ) || ! class_exists( 'Epiktetos_Categories' ) ) {
				return $cats[0];
			}

			$post_cat_ids = array_map( 'intval', wp_list_pluck( $cats, 'term_id' ) );
			foreach ( Epiktetos_Categories::ordered_categories() as $ordered ) {
				if ( in_array( (int) $ordered->term_id, $post_cat_ids, true ) ) {
					return $ordered;
				}
			}

			return $cats[0];
		}

		protected static function reading_time_label( $post ) {
			$minutes = class_exists( 'Epiktetos_Single' )
				? Epiktetos_Single::reading_time( $post->post_content )
				: max( 1, (int) ceil( str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) / 200 ) );

			return sprintf( /* translators: %d minutes */ _n( '%d min read', '%d min read', $minutes, 'epiktetos' ), $minutes );
		}

		protected static function article_count_label( $count, $capitalized = true ) {
			$single = $capitalized ? '%d Article' : '%d article';
			$plural = $capitalized ? '%d Articles' : '%d articles';
			return sprintf( _n( $single, $plural, (int) $count, 'epiktetos' ), (int) $count );
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}

		protected static function compress_outer( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			return trim( $html );
		}
	}

	Epiktetos_Pages::init();
}
