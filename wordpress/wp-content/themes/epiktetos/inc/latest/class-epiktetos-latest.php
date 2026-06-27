<?php
/**
 * Epiktetos — homepage "Latest Articles" section.
 *
 * Renders a responsive editorial grid of recent posts via the
 * [epiktetos_latest_articles] shortcode, used inside front-page.html
 * below the hero slider.
 *
 * Markup is wpautop-proof: every direct child of a processed container is
 * block-level (article / figure / div / h2 / h3 / p), so the block-template
 * wpautop pass has no loose inline content to wrap.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Latest' ) ) {

	class Epiktetos_Latest {

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_latest_articles', array( __CLASS__, 'render' ) );
			// Sidebar is its own shortcode so it can live in a shared homepage
			// column (sticky across both Latest Articles and Category Showcase).
			add_shortcode( 'epiktetos_sidebar', array( __CLASS__, 'render_sidebar' ) );
		}

		/** How many article rows to show. Filterable. */
		protected static function count() {
			return (int) apply_filters( 'epiktetos_latest_count', 5 );
		}

		/**
		 * Recent posts, excluding the posts already featured in the hero so
		 * the homepage never shows the same article twice.
		 *
		 * @return WP_Post[]
		 */
		protected static function get_posts() {
			$exclude = class_exists( 'Epiktetos_Hero' ) ? Epiktetos_Hero::lead_post_ids() : array();

			$args = array(
				'numberposts'         => self::count(),
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'post__not_in'        => $exclude,
				'suppress_filters'    => false,
			);

			// Keep the default "Uncategorized" bucket out of the editorial grid.
			$default_cat = (int) get_option( 'default_category' );
			if ( $default_cat ) {
				$args['category__not_in'] = array( $default_cat );
			}

			return get_posts( $args );
		}

		/**
		 * Section renderer.
		 *
		 * @return string
		 */
		public static function render() {
			$posts = self::get_posts();
			if ( empty( $posts ) ) {
				return '';
			}

			$rows = '';
			foreach ( $posts as $post ) {
				$rows .= self::render_row( $post );
			}

			$heading = '<div class="ts-latest__head">'
				. '<h2 class="ts-latest__title" id="ts-latest-title">' . esc_html__( 'Latest Articles', 'epiktetos' ) . '</h2>'
				. '<div class="ts-latest__viewall"><a href="' . esc_url( home_url( '/' ) ) . '">'
				. esc_html__( 'View all', 'epiktetos' )
				. '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
				. '</a></div>'
				. '</div>';

			// Rows only — the sidebar now lives in the shared homepage column
			// (see [epiktetos_sidebar]) so it can stay sticky across sections.
			$html  = '<section class="ts-latest" aria-labelledby="ts-latest-title">';
			$html .= '<div class="ts-latest__inner">';
			$html .= $heading;
			$html .= '<div class="ts-latest__main">' . $rows . '</div>';
			$html .= '</div>';
			$html .= '</section>';

			return self::compress( $html );
		}

		/**
		 * Render one horizontal editorial row. All direct children of the
		 * processed containers are block-level (figure / div / h3 / p).
		 *
		 * @param WP_Post $post Post object.
		 * @return string
		 */
		protected static function render_row( $post ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			if ( has_post_thumbnail( $post ) ) {
				$img = get_the_post_thumbnail( $post, 'medium_large', array(
					'class'    => 'ts-row__img',
					'alt'      => esc_attr( $title ),
					'loading'  => 'lazy',
					'decoding' => 'async',
				) );
				$media = '<figure class="ts-row__media"><a class="ts-row__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a></figure>';
			} else {
				$media = '<figure class="ts-row__media ts-row__media--placeholder"><a class="ts-row__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true"><span class="ts-row__placeholder"></span></a></figure>';
			}

			$cats = get_the_category( $post->ID );
			$cat  = ! empty( $cats ) ? $cats[0] : null;
			$meta = '<div class="ts-row__meta">';
			if ( $cat ) {
				$meta .= '<a class="ts-row__category" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a>';
			}
			$meta .= '<time class="ts-row__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$meta .= Epiktetos_Reader::updated_badge( $post, 'ts-row__updated' );
			}
			$meta .= '</div>';

			// Longer excerpt (~28–34 words); fall back to trimmed content.
			$excerpt = has_excerpt( $post )
				? get_the_excerpt( $post )
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), 34 );
			$excerpt_html = $excerpt ? '<p class="ts-row__excerpt">' . esc_html( $excerpt ) . '</p>' : '';

			$more = '<div class="ts-row__more"><a class="ts-row__more-link" href="' . esc_url( $permalink ) . '">'
				. esc_html__( 'Read more', 'epiktetos' )
				. '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
				. '</a></div>';

			$row  = '<article class="ts-row">';
			$row .= $media;
			$row .= '<div class="ts-row__body">';
			$row .= $meta;
			$row .= '<h3 class="ts-row__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h3>';
			$row .= $excerpt_html;
			$row .= $more;
			$row .= '</div>';
			$row .= '</article>';

			return $row;
		}

		/**
		 * Editorial sidebar: a brief note, the topic index, and a quiet
		 * newsletter placeholder. Kept curated — no "most recent" list, which
		 * would duplicate the main column.
		 *
		 * @return string
		 */
		public static function render_sidebar() {
			// Topics — the four editorial categories.
			$topics = '';
			foreach ( array( 'technology', 'philosophy', 'psychology', 'history' ) as $slug ) {
				$term = get_category_by_slug( $slug );
				if ( $term ) {
					$topics .= '<li><a href="' . esc_url( get_category_link( $term->term_id ) ) . '">'
						. '<span>' . esc_html( $term->name ) . '</span>'
						. '<span class="ts-side__count">' . (int) $term->count . '</span>'
						. '</a></li>';
				}
			}

			$s  = '<aside class="ts-latest__sidebar" aria-label="' . esc_attr__( 'Editorial', 'epiktetos' ) . '">';

			// 1. Editor's Note.
			$s .= '<div class="ts-side ts-side--note">';
			$s .= '<h3 class="ts-side__title">' . esc_html__( 'Editor’s Note', 'epiktetos' ) . '</h3>';
			$s .= '<p class="ts-side__text">' . esc_html__( 'Epiktetos is a quiet journal on technology, philosophy, psychology, and history — slow essays for the reader who arrives unhurried, and stays.', 'epiktetos' ) . '</p>';
			$s .= '</div>';

			// 2. Topics.
			if ( $topics ) {
				$s .= '<div class="ts-side ts-side--topics">';
				$s .= '<h3 class="ts-side__title">' . esc_html__( 'Topics', 'epiktetos' ) . '</h3>';
				$s .= '<ul class="ts-side__topics">' . $topics . '</ul>';
				$s .= '</div>';
			}

			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$s .= Epiktetos_Reader::publication_stats_module();
				$s .= Epiktetos_Reader::editor_picks_module( 'home', 3 );
				$s .= Epiktetos_Reader::history_module( 'home' );
			}

			$s .= '</aside>';

			return self::compress( $s );
		}

		/**
		 * Collapse inter-tag whitespace so wpautop can't inject <br>.
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

	Epiktetos_Latest::init();
}
