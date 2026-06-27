<?php
/**
 * Epiktetos — archive and category experience.
 *
 * Renders archive pages through [epiktetos_archive]. The renderer consumes the
 * main query that WordPress has already prepared for the current archive, so
 * archive pagination, taxonomy filtering, and empty states remain native while
 * the markup stays fully editorial and wpautop-safe.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Archive' ) ) {

	class Epiktetos_Archive {

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_filter( 'pre_handle_404', array( __CLASS__, 'allow_empty_category_archives' ), 10, 2 );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_archive', array( __CLASS__, 'render' ) );
		}

		/**
		 * Let valid zero-post category archives render their empty state.
		 *
		 * @param bool     $preempt  Whether to short-circuit 404 handling.
		 * @param WP_Query $wp_query Main query.
		 * @return bool
		 */
		public static function allow_empty_category_archives( $preempt, $wp_query ) {
			if ( ! $wp_query instanceof WP_Query || ! $wp_query->is_category() || $wp_query->have_posts() ) {
				return $preempt;
			}

			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) || 'category' !== $term->taxonomy ) {
				return $preempt;
			}

			$wp_query->is_404 = false;
			status_header( 200 );

			return true;
		}

		/**
		 * Archive shortcode renderer.
		 *
		 * @return string
		 */
		public static function render() {
			if ( ! is_archive() && ! is_home() ) {
				return '';
			}

			global $wp_query;
			if ( ! $wp_query instanceof WP_Query ) {
				return '';
			}

			$posts       = is_array( $wp_query->posts ) ? $wp_query->posts : array();
			$is_category = is_category();
			$paged       = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

			$heading_id = 'ts-archive-title';
			$html  = '<section class="ts-archive" aria-labelledby="' . esc_attr( $heading_id ) . '" data-ts-archive>';
			$html .= '<div class="ts-archive__inner">';
			$html .= self::render_header( $heading_id );
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$html .= Epiktetos_Reader::history_module( 'archive' );
			}

			if ( empty( $posts ) ) {
				$html .= self::render_empty();
			} else {
				$featured = null;
				if ( $is_category && 1 === $paged ) {
					$featured = array_shift( $posts );
				}

				if ( $featured ) {
					$html .= self::render_featured( $featured );
				}

				if ( ! empty( $posts ) ) {
					$html .= self::render_rows( $posts, $featured ? __( 'More articles', 'epiktetos' ) : __( 'Articles', 'epiktetos' ) );
				} elseif ( $featured ) {
					$html .= '<div class="ts-archive__single-note"><p>' . esc_html__( 'No older articles have been published here yet.', 'epiktetos' ) . '</p></div>';
				}

				$html .= self::render_pagination( $wp_query, $paged );
			}

			$html .= '</div>';
			$html .= '</section>';

			return self::compress( $html );
		}

		/**
		 * Render the archive masthead.
		 *
		 * @param string $heading_id Heading id used by aria-labelledby.
		 * @return string
		 */
		protected static function render_header( $heading_id ) {
			$title = self::archive_title();
			$desc  = self::archive_description();
			$count = self::archive_count();

			$html  = '<header class="ts-archive__header">';
			$html .= '<h1 class="ts-archive__title" id="' . esc_attr( $heading_id ) . '">' . esc_html( $title ) . '</h1>';
			if ( $desc ) {
				$html .= '<p class="ts-archive__desc">' . esc_html( $desc ) . '</p>';
			}
			if ( null !== $count ) {
				$html .= '<p class="ts-archive__count">' . esc_html( sprintf( /* translators: %d: number of posts */ _n( '%d Article', '%d Articles', $count, 'epiktetos' ), $count ) ) . '</p>';
			}
			$html .= '</header>';

			return $html;
		}

		/**
		 * Human archive title without default WordPress prefixes.
		 *
		 * @return string
		 */
		protected static function archive_title() {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
					return $term->name;
				}
			}
			if ( is_post_type_archive() ) {
				return post_type_archive_title( '', false );
			}
			if ( is_author() ) {
				return get_the_author_meta( 'display_name', (int) get_queried_object_id() );
			}
			if ( is_day() ) {
				return get_the_date();
			}
			if ( is_month() ) {
				return get_the_date( 'F Y' );
			}
			if ( is_year() ) {
				return get_the_date( 'Y' );
			}
			if ( is_home() ) {
				return __( 'Articles', 'epiktetos' );
			}
			return __( 'Archive', 'epiktetos' );
		}

		/**
		 * Archive description, stripped to text so templates avoid wpautop issues.
		 *
		 * @return string
		 */
		protected static function archive_description() {
			$desc = '';
			if ( is_category() || is_tag() || is_tax() ) {
				$desc = term_description();
			} elseif ( is_author() ) {
				$desc = get_the_author_meta( 'description', (int) get_queried_object_id() );
			}
			return trim( wp_strip_all_tags( $desc ) );
		}

		/**
		 * Count displayed in the masthead.
		 *
		 * @return int|null
		 */
		protected static function archive_count() {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term && ! is_wp_error( $term ) && isset( $term->count ) ) {
					return (int) $term->count;
				}
			}
			global $wp_query;
			if ( $wp_query instanceof WP_Query ) {
				return (int) $wp_query->found_posts;
			}
			return null;
		}

		/**
		 * Render the one featured article used on category archive page 1.
		 *
		 * @param WP_Post $post Featured post.
		 * @return string
		 */
		protected static function render_featured( $post ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );
			$label_id  = 'ts-archive-featured-title-' . (int) $post->ID;

			$html  = '<section class="ts-archive-featured" aria-labelledby="' . esc_attr( $label_id ) . '">';
			$html .= '<p class="ts-archive-featured__eyebrow">' . esc_html__( 'Latest', 'epiktetos' ) . '</p>';
			$html .= '<article class="ts-archive-featured__article">';
			$html .= self::render_media( $post, 'ts-archive-featured', 'large', true );
			$html .= '<div class="ts-archive-featured__body">';
			$html .= self::render_meta( $post, 'ts-archive-featured' );
			$html .= '<h2 class="ts-archive-featured__title" id="' . esc_attr( $label_id ) . '"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h2>';
			$html .= self::render_excerpt( $post, 'ts-archive-featured__excerpt', 58 );
			$html .= self::render_read_link( $permalink, __( 'Read Article', 'epiktetos' ), 'ts-archive-featured__more' );
			$html .= '</div>';
			$html .= '</article>';
			$html .= '</section>';

			return $html;
		}

		/**
		 * Render the remaining archive rows.
		 *
		 * @param WP_Post[] $posts Posts to render.
		 * @param string    $label Section label.
		 * @return string
		 */
		protected static function render_rows( $posts, $label ) {
			$html  = '<section class="ts-archive-list" aria-label="' . esc_attr( $label ) . '">';
			$html .= '<div class="ts-archive-list__rows">';
			foreach ( $posts as $post ) {
				$html .= self::render_row( $post );
			}
			$html .= '</div>';
			$html .= '</section>';
			return $html;
		}

		/**
		 * Render one archive row.
		 *
		 * @param WP_Post $post Post object.
		 * @return string
		 */
		public static function render_row( $post ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			$html  = '<article class="ts-archive-row">';
			$html .= self::render_media( $post, 'ts-archive-row', 'medium_large', false );
			$html .= '<div class="ts-archive-row__body">';
			$html .= self::render_meta( $post, 'ts-archive-row' );
			$html .= '<h2 class="ts-archive-row__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h2>';
			$html .= self::render_excerpt( $post, 'ts-archive-row__excerpt', 34 );
			$html .= self::render_read_link( $permalink, __( 'Read More', 'epiktetos' ), 'ts-archive-row__more' );
			$html .= '</div>';
			$html .= '</article>';

			return $html;
		}

		/**
		 * Shared media renderer.
		 *
		 * @param WP_Post $post Post object.
		 * @param string  $base Class prefix.
		 * @param string  $size Image size.
		 * @param bool    $eager Whether image should load eagerly.
		 * @return string
		 */
		protected static function render_media( $post, $base, $size, $eager ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			if ( has_post_thumbnail( $post ) ) {
				$image = get_the_post_thumbnail( $post, $size, array(
					'class'    => $base . '__img',
					'alt'      => esc_attr( $title ),
					'loading'  => $eager ? 'eager' : 'lazy',
					'decoding' => 'async',
				) );
				return '<figure class="' . esc_attr( $base . '__media' ) . '"><a class="' . esc_attr( $base . '__media-link' ) . '" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a></figure>';
			}

			return '<figure class="' . esc_attr( $base . '__media ' . $base . '__media--placeholder' ) . '"><a class="' . esc_attr( $base . '__media-link' ) . '" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true"><span class="' . esc_attr( $base . '__placeholder' ) . '"></span></a></figure>';
		}

		/**
		 * Meta line: category, date, reading time.
		 *
		 * @param WP_Post $post Post object.
		 * @param string  $base Class prefix.
		 * @return string
		 */
		protected static function render_meta( $post, $base ) {
			$meta = '<div class="' . esc_attr( $base . '__meta' ) . '">';
			$cat  = self::primary_category( $post );
			if ( $cat ) {
				$meta .= '<a class="' . esc_attr( $base . '__category' ) . '" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a>';
			}
			$meta .= '<time class="' . esc_attr( $base . '__date' ) . '" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
			if ( self::show_reading_time() ) {
				$minutes = class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : self::fallback_reading_time( $post->post_content );
				$meta .= '<span class="' . esc_attr( $base . '__readtime' ) . '">' . esc_html( sprintf( /* translators: %d minutes */ _n( '%d min read', '%d min read', $minutes, 'epiktetos' ), $minutes ) ) . '</span>';
			}
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$meta .= Epiktetos_Reader::updated_badge( $post, $base . '__updated' );
			}
			$meta .= '</div>';

			return $meta;
		}

		/**
		 * Select a category using the existing admin category order as priority.
		 *
		 * @param WP_Post $post Post object.
		 * @return WP_Term|null
		 */
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

		/**
		 * Whether archive rows should show reading time.
		 *
		 * @return bool
		 */
		protected static function show_reading_time() {
			if ( class_exists( 'Epiktetos_Single' ) && method_exists( 'Epiktetos_Single', 'get' ) ) {
				return (bool) Epiktetos_Single::get( 'show_reading_time' );
			}
			return true;
		}

		/**
		 * Fallback only used if the single subsystem is unavailable.
		 *
		 * @param string $content Raw post content.
		 * @return int
		 */
		protected static function fallback_reading_time( $content ) {
			$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
			return max( 1, (int) ceil( $words / 200 ) );
		}

		/**
		 * Excerpt helper.
		 *
		 * @param WP_Post $post  Post object.
		 * @param string  $class CSS class.
		 * @param int     $words Word count.
		 * @return string
		 */
		protected static function render_excerpt( $post, $class, $words ) {
			$excerpt = has_excerpt( $post )
				? wp_trim_words( get_the_excerpt( $post ), $words )
				: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), $words );

			return $excerpt ? '<p class="' . esc_attr( $class ) . '">' . esc_html( $excerpt ) . '</p>' : '';
		}

		/**
		 * Shared read link.
		 *
		 * @param string $permalink Post URL.
		 * @param string $label     Link label.
		 * @param string $class     Wrapper class.
		 * @return string
		 */
		protected static function render_read_link( $permalink, $label, $class ) {
			return '<div class="' . esc_attr( $class ) . '"><a href="' . esc_url( $permalink ) . '">' . esc_html( $label ) . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a></div>';
		}

		/**
		 * Elegant archive pagination.
		 *
		 * @param WP_Query $query Main query.
		 * @param int      $paged Current page number.
		 * @return string
		 */
		public static function render_pagination( $query, $paged ) {
			$total = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 1;
			if ( $total <= 1 ) {
				return '';
			}

			$prev = $paged > 1 ? get_pagenum_link( $paged - 1 ) : '';
			$next = $paged < $total ? get_pagenum_link( $paged + 1 ) : '';

			$html  = '<nav class="ts-archive-pagination" aria-label="' . esc_attr__( 'Archive pagination', 'epiktetos' ) . '">';
			$html .= $prev
				? '<a class="ts-archive-pagination__link ts-archive-pagination__prev" href="' . esc_url( $prev ) . '" rel="prev">' . esc_html__( 'Previous', 'epiktetos' ) . '</a>'
				: '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__prev" aria-disabled="true">' . esc_html__( 'Previous', 'epiktetos' ) . '</span>';
			$html .= '<span class="ts-archive-pagination__count" aria-current="page">' . esc_html( sprintf( /* translators: 1: current page, 2: total pages */ __( 'Page %1$d of %2$d', 'epiktetos' ), $paged, $total ) ) . '</span>';
			$html .= $next
				? '<a class="ts-archive-pagination__link ts-archive-pagination__next" href="' . esc_url( $next ) . '" rel="next">' . esc_html__( 'Next', 'epiktetos' ) . '</a>'
				: '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__next" aria-disabled="true">' . esc_html__( 'Next', 'epiktetos' ) . '</span>';
			$html .= '</nav>';

			return $html;
		}

		/**
		 * Empty archive state.
		 *
		 * @return string
		 */
		protected static function render_empty() {
			return '<div class="ts-archive-empty" role="status"><p>' . esc_html__( 'No articles have been published here yet.', 'epiktetos' ) . '</p></div>';
		}

		/**
		 * Collapse inter-tag whitespace so block-template wpautop stays quiet.
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

	Epiktetos_Archive::init();
}
