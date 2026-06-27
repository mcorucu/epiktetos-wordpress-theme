<?php
/**
 * Epiktetos - search and discovery experience.
 *
 * Owns the header search panel, lightweight REST live search endpoint, and the
 * premium search results page rendered through [epiktetos_search].
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Search' ) ) {

	class Epiktetos_Search {

		const REST_NAMESPACE = 'epiktetos/v1';
		const REST_ROUTE     = '/search';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		}

		public static function register_shortcodes() {
			add_shortcode( 'epiktetos_search_panel', array( __CLASS__, 'render_panel' ) );
			add_shortcode( 'epiktetos_search', array( __CLASS__, 'render_page' ) );
		}

		public static function register_rest_routes() {
			register_rest_route(
				self::REST_NAMESPACE,
				self::REST_ROUTE,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_search' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
					),
				)
			);
		}

		/**
		 * Header search panel.
		 *
		 * @return string
		 */
		public static function render_panel() {
			$endpoint = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
			$recent   = self::recent_posts( 4 );
			$cats     = self::ordered_categories( 4 );

			$html  = '<div class="ts-search-panel" id="ts-search-panel" role="dialog" aria-modal="false" aria-label="' . esc_attr__( 'Search the site', 'epiktetos' ) . '" aria-hidden="true" data-ts-search-endpoint="' . esc_url( $endpoint ) . '">';
			$html .= '<div class="ts-search-panel__inner">';
			$html .= '<p class="ts-search-panel__label">' . esc_html__( 'Search', 'epiktetos' ) . '</p>';
			$html .= '<form role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '" class="ts-search-form" data-ts-search-form>';
			$html .= '<span class="ts-search-form__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg></span>';
			$html .= '<label class="screen-reader-text" for="ts-search-input">' . esc_html__( 'Search for:', 'epiktetos' ) . '</label>';
			$html .= '<input type="search" id="ts-search-input" name="s" class="ts-search-input" placeholder="' . esc_attr__( 'Search articles...', 'epiktetos' ) . '" autocomplete="off" aria-controls="ts-search-live-results" aria-describedby="ts-search-status" data-ts-search-input />';
			$html .= '<span class="ts-search-hint" aria-hidden="true"><kbd class="ts-kbd">Enter</kbd> ' . esc_html__( 'to search', 'epiktetos' ) . '<span class="ts-search-hint__sep">/</span><kbd class="ts-kbd">Esc</kbd> ' . esc_html__( 'to close', 'epiktetos' ) . '</span>';
			$html .= '</form>';
			$html .= '<div class="ts-search-status" id="ts-search-status" role="status" aria-live="polite" data-ts-search-status></div>';

			$html .= '<div class="ts-search-default" data-ts-search-default>';
			$html .= self::render_topic_section( __( 'Popular Topics', 'epiktetos' ), $cats, 'ts-search-topics' );
			$html .= self::render_recent_panel( $recent );
			$html .= self::render_suggested_topics( $cats );
			$html .= '</div>';

			$html .= '<div class="ts-search-results-panel" id="ts-search-live-results" role="listbox" aria-label="' . esc_attr__( 'Search results', 'epiktetos' ) . '" hidden data-ts-search-results></div>';
			$html .= '</div>';
			$html .= '</div>';

			return self::compress( $html );
		}

		/**
		 * REST callback for live search.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public static function rest_search( $request ) {
			$query = trim( (string) $request->get_param( 'search' ) );
			if ( strlen( $query ) < 2 ) {
				return rest_ensure_response( array() );
			}

			$posts = get_posts( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				's'                   => $query,
				'posts_per_page'      => 5,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			) );

			$results = array();
			foreach ( $posts as $post ) {
				$cat = self::primary_category( $post );
				$results[] = array(
					'title'        => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'category'     => $cat ? html_entity_decode( $cat->name, ENT_QUOTES, get_bloginfo( 'charset' ) ) : '',
					'categoryUrl'  => $cat ? get_category_link( $cat->term_id ) : '',
					'excerpt'      => self::excerpt_text( $post, 24 ),
					'permalink'    => get_permalink( $post ),
					'date'         => get_the_date( '', $post ),
					'readingTime'  => self::reading_time_label( $post ),
				);
			}

			return rest_ensure_response( $results );
		}

		/**
		 * Search results page.
		 *
		 * @return string
		 */
		public static function render_page() {
			global $wp_query;

			$query = get_search_query( false );
			$posts = ( $wp_query instanceof WP_Query && is_array( $wp_query->posts ) ) ? $wp_query->posts : array();
			$count = ( $wp_query instanceof WP_Query ) ? (int) $wp_query->found_posts : 0;

			$html  = '<section class="ts-search-page" aria-labelledby="ts-search-page-title">';
			$html .= '<div class="ts-search-page__inner">';
			$html .= '<header class="ts-search-page__header">';
			$html .= '<h1 class="ts-search-page__title" id="ts-search-page-title">' . esc_html__( 'Search Results', 'epiktetos' ) . '</h1>';
			$html .= '<form role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '" class="ts-search-page__form">';
			$html .= '<label class="screen-reader-text" for="ts-search-page-input">' . esc_html__( 'Search articles', 'epiktetos' ) . '</label>';
			$html .= '<input type="search" id="ts-search-page-input" name="s" value="' . esc_attr( $query ) . '" placeholder="' . esc_attr__( 'Search articles...', 'epiktetos' ) . '" autocomplete="off" />';
			$html .= '<button type="submit">' . esc_html__( 'Search', 'epiktetos' ) . '</button>';
			$html .= '</form>';
			if ( '' !== $query ) {
				$html .= '<p class="ts-search-page__summary">' . esc_html( sprintf( /* translators: 1: query, 2: count */ _n( '%2$d result for "%1$s"', '%2$d results for "%1$s"', $count, 'epiktetos' ), $query, $count ) ) . '</p>';
			} else {
				$html .= '<p class="ts-search-page__summary">' . esc_html__( 'Enter a word, idea, or title to search the archive.', 'epiktetos' ) . '</p>';
			}
			$html .= '</header>';
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$html .= Epiktetos_Reader::history_module( 'search' );
			}

			if ( '' !== $query && ! empty( $posts ) ) {
				$html .= '<section class="ts-search-page__results" aria-label="' . esc_attr__( 'Search results', 'epiktetos' ) . '">';
				foreach ( $posts as $post ) {
					$html .= self::render_result_row( $post );
				}
				$html .= '</section>';
				$html .= self::render_pagination( $wp_query );
			} elseif ( '' !== $query ) {
				$html .= self::render_no_results();
			}

			$html .= self::render_discovery();
			$html .= '</div>';
			$html .= '</section>';

			return self::compress( $html );
		}

		protected static function render_recent_panel( $posts ) {
			if ( empty( $posts ) ) {
				return '';
			}

			$html  = '<section class="ts-search-recents" aria-labelledby="ts-search-recents-title">';
			$html .= '<h2 class="ts-search-panel__heading" id="ts-search-recents-title">' . esc_html__( 'Recent Articles', 'epiktetos' ) . '</h2>';
			$html .= '<div class="ts-search-recents__list">';
			foreach ( $posts as $post ) {
				$cat = self::primary_category( $post );
				$html .= '<article class="ts-search-recent">';
				$html .= '<div class="ts-search-recent__meta">' . ( $cat ? '<span>' . esc_html( $cat->name ) . '</span>' : '' ) . '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></div>';
				$html .= '<h3 class="ts-search-recent__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>';
				$html .= '</article>';
			}
			$html .= '</div>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_topic_section( $title, $cats, $class ) {
			if ( empty( $cats ) ) {
				return '';
			}

			$html  = '<section class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $title ) . '">';
			$html .= '<h2 class="ts-search-panel__heading">' . esc_html( $title ) . '</h2>';
			$html .= '<ul>';
			foreach ( $cats as $cat ) {
				$html .= '<li><a href="' . esc_url( get_category_link( $cat->term_id ) ) . '"><span>' . esc_html( $cat->name ) . '</span><span>' . esc_html( sprintf( /* translators: %d: post count */ _n( '%d article', '%d articles', (int) $cat->count, 'epiktetos' ), (int) $cat->count ) ) . '</span></a></li>';
			}
			$html .= '</ul>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_suggested_topics( $cats ) {
			if ( empty( $cats ) ) {
				return '';
			}

			$html  = '<section class="ts-search-suggestions" aria-labelledby="ts-search-suggestions-title">';
			$html .= '<h2 class="ts-search-panel__heading" id="ts-search-suggestions-title">' . esc_html__( 'Suggested Topics', 'epiktetos' ) . '</h2>';
			$html .= '<div class="ts-search-suggestions__items">';
			foreach ( $cats as $cat ) {
				$desc = trim( wp_strip_all_tags( $cat->description ) );
				$html .= '<a href="' . esc_url( get_category_link( $cat->term_id ) ) . '">';
				$html .= '<span>' . esc_html( $cat->name ) . '</span>';
				if ( $desc ) {
					$html .= '<small>' . esc_html( $desc ) . '</small>';
				}
				$html .= '</a>';
			}
			$html .= '</div>';
			$html .= '</section>';

			return $html;
		}

		protected static function render_result_row( $post ) {
			$cat = self::primary_category( $post );
			$html  = '<article class="ts-search-result">';
			$html .= '<div class="ts-search-result__meta">';
			if ( $cat ) {
				$html .= '<a class="ts-search-result__category" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a>';
			}
			$html .= '<time class="ts-search-result__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
			$html .= '<span class="ts-search-result__readtime">' . esc_html( self::reading_time_label( $post ) ) . '</span>';
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$html .= Epiktetos_Reader::updated_badge( $post, 'ts-search-result__updated' );
			}
			$html .= '</div>';
			$html .= '<h2 class="ts-search-result__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';
			$html .= '<p class="ts-search-result__excerpt">' . esc_html( self::excerpt_text( $post, 36 ) ) . '</p>';
			$html .= '</article>';

			return $html;
		}

		protected static function render_no_results() {
			$latest = self::recent_posts( 3 );

			$html  = '<section class="ts-search-empty" role="status">';
			$html .= '<h2>' . esc_html__( 'No articles matched your search.', 'epiktetos' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'Try a broader word, or start again from the most-read paths through the archive.', 'epiktetos' ) . '</p>';
			if ( ! empty( $latest ) ) {
				$html .= '<div class="ts-search-empty__latest">';
				$html .= '<h3>' . esc_html__( 'Latest Articles', 'epiktetos' ) . '</h3>';
				foreach ( $latest as $post ) {
					$html .= '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
				}
				$html .= '</div>';
			}
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$html .= Epiktetos_Reader::editor_picks_module( 'search', 3 );
			}
			$html .= '</section>';

			return $html;
		}

		protected static function render_discovery() {
			$cats = self::ordered_categories( 4 );
			if ( empty( $cats ) ) {
				return '';
			}

			$html  = '<aside class="ts-search-discovery" aria-labelledby="ts-search-discovery-title">';
			$html .= '<h2 id="ts-search-discovery-title">' . esc_html__( 'Popular Categories', 'epiktetos' ) . '</h2>';
			$html .= '<div class="ts-search-discovery__grid">';
			foreach ( $cats as $cat ) {
				$desc = trim( wp_strip_all_tags( $cat->description ) );
				$html .= '<a class="ts-search-discovery__item" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">';
				$html .= '<span>' . esc_html( $cat->name ) . '</span>';
				$html .= '<small>' . esc_html( $desc ? $desc : sprintf( /* translators: %d: post count */ _n( '%d article', '%d articles', (int) $cat->count, 'epiktetos' ), (int) $cat->count ) ) . '</small>';
				$html .= '</a>';
			}
			$html .= '</div>';
			$html .= '</aside>';

			return $html;
		}

		protected static function render_pagination( $query ) {
			if ( ! $query instanceof WP_Query || (int) $query->max_num_pages <= 1 ) {
				return '';
			}

			$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
			$total = (int) $query->max_num_pages;
			$prev  = $paged > 1 ? get_pagenum_link( $paged - 1 ) : '';
			$next  = $paged < $total ? get_pagenum_link( $paged + 1 ) : '';

			$html  = '<nav class="ts-archive-pagination ts-search-page__pagination" aria-label="' . esc_attr__( 'Search pagination', 'epiktetos' ) . '">';
			$html .= $prev ? '<a class="ts-archive-pagination__link ts-archive-pagination__prev" href="' . esc_url( $prev ) . '" rel="prev">' . esc_html__( 'Previous', 'epiktetos' ) . '</a>' : '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__prev" aria-disabled="true">' . esc_html__( 'Previous', 'epiktetos' ) . '</span>';
			$html .= '<span class="ts-archive-pagination__count" aria-current="page">' . esc_html( sprintf( /* translators: 1: current page, 2: total pages */ __( 'Page %1$d of %2$d', 'epiktetos' ), $paged, $total ) ) . '</span>';
			$html .= $next ? '<a class="ts-archive-pagination__link ts-archive-pagination__next" href="' . esc_url( $next ) . '" rel="next">' . esc_html__( 'Next', 'epiktetos' ) . '</a>' : '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__next" aria-disabled="true">' . esc_html__( 'Next', 'epiktetos' ) . '</span>';
			$html .= '</nav>';

			return $html;
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

		protected static function ordered_categories( $limit = 0 ) {
			$cats = class_exists( 'Epiktetos_Categories' )
				? Epiktetos_Categories::ordered_categories()
				: get_categories( array( 'hide_empty' => true ) );

			$cats = is_array( $cats ) ? $cats : array();
			return $limit ? array_slice( $cats, 0, (int) $limit ) : $cats;
		}

		protected static function primary_category( $post ) {
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

		protected static function excerpt_text( $post, $words ) {
			$excerpt = has_excerpt( $post )
				? get_the_excerpt( $post )
				: wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

			return html_entity_decode( wp_trim_words( wp_strip_all_tags( $excerpt ), (int) $words ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		protected static function reading_time_label( $post ) {
			$minutes = class_exists( 'Epiktetos_Single' )
				? Epiktetos_Single::reading_time( $post->post_content )
				: max( 1, (int) ceil( str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) / 200 ) );

			return sprintf( /* translators: %d minutes */ _n( '%d min read', '%d min read', $minutes, 'epiktetos' ), $minutes );
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Search::init();
}
