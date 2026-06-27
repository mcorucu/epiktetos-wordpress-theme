<?php
/**
 * Epiktetos homepage hero.
 *
 * Renders a single featured article as an editorial hero via the
 * [epiktetos_hero] shortcode, used inside templates/front-page.html.
 *
 * Selection + display are resolved through getter methods with sensible
 * defaults, so a later phase can back them with admin settings without
 * touching the template or the markup.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Hero' ) ) {

	class Epiktetos_Hero {

		/** Hook. */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_hero', array( __CLASS__, 'render' ) );
		}

		/* ---------------- Config (future admin-controlled) ---------------- */

		/**
		 * Resolve the hero post.
		 *
		 * Priority: explicit option → first sticky post → most recent post.
		 * Filterable so a settings field (hero_post_id) can drive it later.
		 *
		 * @return WP_Post|null
		 */
		protected static function get_hero_post() {
			$post_id = 0;

			// Future: a settings field may store this.
			if ( class_exists( 'Epiktetos_Header' ) ) {
				$opts = get_option( 'epiktetos_options', array() );
				if ( ! empty( $opts['hero_post_id'] ) ) {
					$post_id = (int) $opts['hero_post_id'];
				}
			}

			/**
			 * Filter the hero post id before fallback resolution.
			 *
			 * @param int $post_id Selected hero post id (0 = auto).
			 */
			$post_id = (int) apply_filters( 'epiktetos_hero_post_id', $post_id );

			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post && 'publish' === $post->post_status ) {
					return $post;
				}
			}

			$stickies = get_option( 'sticky_posts' );
			if ( ! empty( $stickies ) ) {
				$post = get_post( (int) $stickies[0] );
				if ( $post && 'publish' === $post->post_status ) {
					return $post;
				}
			}

			$recent = get_posts( array(
				'numberposts'      => 1,
				'post_status'      => 'publish',
				'suppress_filters' => false,
			) );
			return $recent ? $recent[0] : null;
		}

		/** Display toggles — defaults now, admin-driven later. */
		protected static function show_category() {
			return (bool) apply_filters( 'epiktetos_hero_show_category', true );
		}
		protected static function show_excerpt() {
			return (bool) apply_filters( 'epiktetos_hero_show_excerpt', true );
		}
		protected static function cta_label() {
			return (string) apply_filters( 'epiktetos_hero_cta_label', __( 'Read article', 'epiktetos' ) );
		}

		/* ---------------- Render ---------------- */

		/**
		 * Category slugs that drive the slider, in order. Filterable so a
		 * later admin screen can manage the set.
		 *
		 * @return string[]
		 */
		protected static function slide_categories() {
			return (array) apply_filters( 'epiktetos_hero_categories', array( 'technology', 'philosophy', 'psychology', 'history' ) );
		}

		/**
		 * Build the list of slides: one latest post per configured category.
		 * Falls back to the single resolved hero post if no category posts
		 * exist (keeps the section meaningful on a fresh install).
		 *
		 * @return array[] Each: array( 'post' => WP_Post, 'term' => WP_Term|null )
		 */
		protected static function get_slides() {
			$slides = array();
			$seen   = array();

			foreach ( self::slide_categories() as $slug ) {
				$term = get_category_by_slug( $slug );
				if ( ! $term ) {
					continue;
				}
				$posts = get_posts( array(
					'numberposts'      => 1,
					'post_status'      => 'publish',
					'category'         => $term->term_id,
					'suppress_filters' => false,
				) );
				if ( $posts && ! in_array( $posts[0]->ID, $seen, true ) ) {
					$slides[] = array( 'post' => $posts[0], 'term' => $term );
					$seen[]   = $posts[0]->ID;
				}
			}

			if ( empty( $slides ) ) {
				$post = self::get_hero_post();
				if ( $post ) {
					$slides[] = array( 'post' => $post, 'term' => null );
				}
			}

			return $slides;
		}

		/**
		 * Public: the post IDs currently used as hero slides. Lets other
		 * sections (e.g. Latest Articles) avoid showing them twice.
		 *
		 * @return int[]
		 */
		public static function lead_post_ids() {
			$ids = array();
			foreach ( self::get_slides() as $slide ) {
				$ids[] = (int) $slide['post']->ID;
			}
			return $ids;
		}

		/**
		 * Hero shortcode renderer — a fade/translate slider of category leads.
		 *
		 * @return string
		 */
		public static function render() {
			$slides = self::get_slides();
			if ( empty( $slides ) ) {
				return '';
			}

			$total       = count( $slides );
			$is_slider   = $total > 1;
			$slides_html = '';
			$dots_html   = '';

			foreach ( $slides as $i => $slide ) {
				$active        = ( 0 === $i );
				$slides_html  .= self::render_slide( $slide['post'], $slide['term'], $i, $total, $active );

				if ( $is_slider ) {
					$label      = $slide['term'] ? $slide['term']->name : get_the_title( $slide['post'] );
					$dots_html .= '<button type="button" class="ts-hero__dot' . ( $active ? ' is-active' : '' ) . '"'
						. ' role="tab" aria-selected="' . ( $active ? 'true' : 'false' ) . '"'
						. ' aria-current="' . ( $active ? 'true' : 'false' ) . '"'
						. ' tabindex="' . ( $active ? '0' : '-1' ) . '"'
						. ' data-ts-hero-dot="' . (int) $i . '"'
						. ' aria-label="' . esc_attr( sprintf( /* translators: %s: category name */ __( 'Show: %s', 'epiktetos' ), $label ) ) . '">'
						. '<span class="ts-hero__dot-fill" aria-hidden="true"></span></button>';
				}
			}

			$attr  = $is_slider ? ' data-ts-hero aria-roledescription="carousel"' : '';
			$html  = '<section class="ts-hero" aria-label="' . esc_attr__( 'Featured articles', 'epiktetos' ) . '"' . $attr . '>';
			$html .= '<div class="ts-hero__viewport">' . $slides_html . '</div>';
			if ( $is_slider ) {
				$html .= '<div class="ts-hero__dots" role="tablist" aria-label="' . esc_attr__( 'Featured articles', 'epiktetos' ) . '">' . $dots_html . '</div>';
			}
			$html .= '</section>';

			return self::compress( $html );
		}

		/**
		 * Render a single slide. Markup matches the approved hero layout
		 * exactly; only a slide wrapper + state attributes are added.
		 *
		 * @param WP_Post      $post   The post.
		 * @param WP_Term|null $term   The category that placed it on this slide.
		 * @param int          $index  Zero-based slide index.
		 * @param int          $total  Total slides.
		 * @param bool         $active Whether this slide starts active.
		 * @return string
		 */
		protected static function render_slide( $post, $term, $index, $total, $active ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			// Category overline — the slide's category (fallback to primary).
			$cat_html = '';
			if ( self::show_category() ) {
				$cat = $term;
				if ( ! $cat ) {
					$cats = get_the_category( $post->ID );
					$cat  = ! empty( $cats ) ? $cats[0] : null;
				}
				if ( $cat ) {
					$cat_html = '<div class="ts-hero__kicker"><a class="ts-hero__category" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">'
						. esc_html( $cat->name ) . '</a></div>';
				}
			}

			// Excerpt.
			$excerpt_html = '';
			if ( self::show_excerpt() ) {
				$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 32 );
				if ( $excerpt ) {
					$excerpt_html = '<p class="ts-hero__excerpt">' . esc_html( $excerpt ) . '</p>';
				}
			}

			// Media — featured image, or a CSS-only placeholder.
			if ( has_post_thumbnail( $post ) ) {
				$img = get_the_post_thumbnail( $post, 'large', array(
					'class'         => 'ts-hero__img',
					'alt'           => esc_attr( $title ),
					'loading'       => $active ? 'eager' : 'lazy',
					'fetchpriority' => $active ? 'high' : 'auto',
					'decoding'      => 'async',
				) );
				$media = '<figure class="ts-hero__media"><a class="ts-hero__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a></figure>';
			} else {
				$media = '<figure class="ts-hero__media ts-hero__media--placeholder"><a class="ts-hero__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true"><span class="ts-hero__placeholder"></span></a></figure>';
			}

			$cta = '<div class="ts-hero__cta-wrap"><a class="ts-hero__cta" href="' . esc_url( $permalink ) . '">'
				. '<span>' . esc_html( self::cta_label() ) . '</span>'
				. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
				. '</a></div>';

			// Headings: only the active slide carries the page <h1>; the rest
			// use <h2> so the document has a single top-level heading.
			$htag = $active ? 'h1' : 'h2';

			$slide  = '<div class="ts-hero__slide' . ( $active ? ' is-active' : '' ) . '"'
				. ' role="group" aria-roledescription="slide"'
				. ' aria-label="' . esc_attr( sprintf( /* translators: 1: index, 2: total */ __( '%1$d of %2$d', 'epiktetos' ), $index + 1, $total ) ) . '"'
				. ' aria-hidden="' . ( $active ? 'false' : 'true' ) . '"'
				. ' data-ts-hero-slide="' . (int) $index . '">';
			$slide .= '<div class="ts-hero__inner">';
			$slide .= $media;
			$slide .= '<div class="ts-hero__body">';
			$slide .= $cat_html;
			$slide .= '<' . $htag . ' class="ts-hero__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></' . $htag . '>';
			$slide .= $excerpt_html;
			$slide .= $cta;
			$slide .= '</div>'; // body
			$slide .= '</div>'; // inner
			$slide .= '</div>'; // slide

			return $slide;
		}

		/**
		 * Collapse inter-tag whitespace so the core/shortcode block's wpautop
		 * pass can't inject <p>/<br> into the markup.
		 *
		 * @param string $html Raw HTML.
		 * @return string
		 */
		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Hero::init();
}
