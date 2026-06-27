<?php
/**
 * Epiktetos - tag and topic taxonomy experience.
 *
 * Renders curated tag archives and the Topics index page without duplicating
 * the archive row system.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Taxonomies' ) ) {

	class Epiktetos_Taxonomies {

		const OPTION = 'epiktetos_taxonomy_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_filter( 'pre_handle_404', array( __CLASS__, 'allow_empty_tag_archives' ), 10, 2 );
		}

		public static function register_shortcodes() {
			add_shortcode( 'epiktetos_tag', array( __CLASS__, 'render_tag_archive' ) );
			add_shortcode( 'epiktetos_topics', array( __CLASS__, 'render_topics_page' ) );
		}

		public static function defaults() {
			return array(
				'show_related_topics' => 1,
				'tag_index_count'     => 24,
				'show_article_counts' => 1,
			);
		}

		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$defs = self::defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) ) {
				return $opts[ $key ];
			}
			return isset( $defs[ $key ] ) ? $defs[ $key ] : null;
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
				'epiktetos_taxonomies',
				__( 'Taxonomies', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Configure the tag archive and Topics index discovery surfaces.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'show_related_topics' => array( 'label' => __( 'Show related topics', 'epiktetos' ), 'type' => 'checkbox' ),
				'tag_index_count'     => array( 'label' => __( 'Topics index tag count', 'epiktetos' ), 'type' => 'number', 'min' => 6, 'max' => 80 ),
				'show_article_counts' => array( 'label' => __( 'Show article counts', 'epiktetos' ), 'type' => 'checkbox' ),
			);

			foreach ( $fields as $key => $field ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_taxonomies',
					array_merge( array( 'key' => $key ), $field )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-taxonomies-' . $key;

			if ( 'checkbox' === $args['type'] ) {
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html__( 'Enabled', 'epiktetos' )
				);
				return;
			}

			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( isset( $args['min'] ) ? $args['min'] : 1 ),
				esc_attr( isset( $args['max'] ) ? $args['max'] : 80 )
			);
		}

		public static function sanitize( $input ) {
			$defs = self::defaults();
			return array(
				'show_related_topics' => ! empty( $input['show_related_topics'] ) ? 1 : 0,
				'tag_index_count'     => max( 6, min( 80, (int) ( isset( $input['tag_index_count'] ) ? $input['tag_index_count'] : $defs['tag_index_count'] ) ) ),
				'show_article_counts' => ! empty( $input['show_article_counts'] ) ? 1 : 0,
			);
		}

		/**
		 * Let valid no-post tag archives render a calm empty state.
		 *
		 * @param bool     $preempt  Whether to short-circuit 404 handling.
		 * @param WP_Query $wp_query Main query.
		 * @return bool
		 */
		public static function allow_empty_tag_archives( $preempt, $wp_query ) {
			if ( ! $wp_query instanceof WP_Query || ! $wp_query->is_tag() || $wp_query->have_posts() ) {
				return $preempt;
			}

			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) || 'post_tag' !== $term->taxonomy ) {
				return $preempt;
			}

			$wp_query->is_404 = false;
			status_header( 200 );

			return true;
		}

		public static function render_tag_archive() {
			if ( ! is_tag() ) {
				return '';
			}

			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
				return '';
			}

			global $wp_query;
			$posts = ( $wp_query instanceof WP_Query && is_array( $wp_query->posts ) ) ? $wp_query->posts : array();
			$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

			$html  = '<section class="ts-tag" aria-labelledby="ts-tag-title">';
			$html .= '<div class="ts-tag__inner">';
			$html .= self::render_tag_header( $term );
			if ( (int) self::get( 'show_related_topics' ) ) {
				$html .= self::render_related_topics( $term );
			}

			if ( empty( $posts ) ) {
				$html .= '<div class="ts-tag__empty" role="status"><p>' . esc_html__( 'No articles have been published here yet.', 'epiktetos' ) . '</p></div>';
			} else {
				$html .= '<section class="ts-archive-list ts-tag__list" aria-label="' . esc_attr__( 'Tagged articles', 'epiktetos' ) . '">';
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

		protected static function render_tag_header( $term ) {
			$description = trim( wp_strip_all_tags( term_description( $term, 'post_tag' ) ) );
			if ( ! $description ) {
				$description = sprintf(
					/* translators: %s: tag name */
					__( 'Articles connected to %s.', 'epiktetos' ),
					$term->name
				);
			}

			$html  = '<header class="ts-tag__header">';
			$html .= '<p class="ts-tag__eyebrow">' . esc_html__( 'Topic', 'epiktetos' ) . '</p>';
			$html .= '<h1 class="ts-tag__title" id="ts-tag-title">' . esc_html( $term->name ) . '</h1>';
			$html .= '<p class="ts-tag__desc">' . esc_html( $description ) . '</p>';
			$html .= '<p class="ts-tag__count">' . esc_html( self::article_count_label( (int) $term->count ) ) . '</p>';
			$html .= '</header>';

			return $html;
		}

		protected static function render_related_topics( $term ) {
			$tags = self::related_tags_for_tag( $term );
			if ( empty( $tags ) ) {
				return '';
			}

			$html  = '<aside class="ts-topic-pills ts-tag__related" aria-labelledby="ts-related-topics-title">';
			$html .= '<h2 id="ts-related-topics-title">' . esc_html__( 'Related Topics', 'epiktetos' ) . '</h2>';
			$html .= '<div class="ts-topic-pills__items">';
			foreach ( $tags as $tag ) {
				$html .= self::topic_pill( $tag );
			}
			$html .= '</div>';
			$html .= '</aside>';

			return $html;
		}

		public static function render_topics_page() {
			if ( ! is_page() ) {
				return '';
			}

			$html  = '<article class="ts-page ts-topics" aria-labelledby="ts-topics-title">';
			$html .= '<div class="ts-page__inner">';
			$html .= '<header class="ts-page__header">';
			$html .= '<p class="ts-page__eyebrow">' . esc_html__( 'Discovery', 'epiktetos' ) . '</p>';
			$html .= '<h1 class="ts-page__title" id="ts-topics-title">' . esc_html__( 'Topics', 'epiktetos' ) . '</h1>';
			$html .= '<p class="ts-page__dek">' . esc_html__( 'A map of the recurring ideas, categories, and quiet obsessions in the archive.', 'epiktetos' ) . '</p>';
			$html .= '</header>';
			$html .= self::render_category_index();
			$html .= self::render_popular_tags();
			$html .= self::render_category_topic_groups();
			$html .= '</div>';
			$html .= '</article>';

			return self::compress( $html );
		}

		protected static function render_category_index() {
			$cats = self::ordered_categories();

			$html  = '<section class="ts-topics-section" aria-labelledby="ts-all-categories-title">';
			$html .= '<div class="ts-topics-section__head"><h2 id="ts-all-categories-title">' . esc_html__( 'All Categories', 'epiktetos' ) . '</h2></div>';
			if ( empty( $cats ) ) {
				$html .= '<p class="ts-topics-section__empty">' . esc_html__( 'No categories have been published yet.', 'epiktetos' ) . '</p>';
			} else {
				$html .= '<div class="ts-topic-groups">';
				foreach ( $cats as $cat ) {
					$desc = trim( wp_strip_all_tags( category_description( $cat->term_id ) ) );
					$html .= '<a class="ts-topic-group" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">';
					$html .= '<span class="ts-topic-group__name">' . esc_html( $cat->name ) . '</span>';
					$html .= '<span class="ts-topic-group__desc">' . esc_html( $desc ? $desc : self::article_count_label( (int) $cat->count, false ) ) . '</span>';
					$html .= '</a>';
				}
				$html .= '</div>';
			}
			$html .= '</section>';

			return $html;
		}

		protected static function render_popular_tags() {
			$tags = get_terms( array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => (int) self::get( 'tag_index_count' ),
			) );
			$tags = is_array( $tags ) && ! is_wp_error( $tags ) ? $tags : array();

			$html  = '<section class="ts-topics-section" aria-labelledby="ts-popular-topics-title">';
			$html .= '<div class="ts-topics-section__head"><h2 id="ts-popular-topics-title">' . esc_html__( 'Popular Topics', 'epiktetos' ) . '</h2></div>';
			if ( empty( $tags ) ) {
				$html .= '<p class="ts-topics-section__empty">' . esc_html__( 'No topics have been added yet.', 'epiktetos' ) . '</p>';
			} else {
				$html .= '<div class="ts-topic-pills__items ts-topic-pills__items--large">';
				foreach ( $tags as $tag ) {
					$html .= self::topic_pill( $tag );
				}
				$html .= '</div>';
			}
			$html .= '</section>';

			return $html;
		}

		protected static function render_category_topic_groups() {
			$cats = array_slice( self::ordered_categories(), 0, 8 );
			if ( empty( $cats ) ) {
				return '';
			}

			$html  = '<section class="ts-topics-section" aria-labelledby="ts-topic-groups-title">';
			$html .= '<div class="ts-topics-section__head"><h2 id="ts-topic-groups-title">' . esc_html__( 'Topic Paths', 'epiktetos' ) . '</h2></div>';
			$html .= '<div class="ts-topic-paths">';
			foreach ( $cats as $cat ) {
				$tags = self::tags_for_category( $cat->term_id, 8 );
				if ( empty( $tags ) ) {
					continue;
				}
				$html .= '<section class="ts-topic-path" aria-labelledby="ts-topic-path-' . (int) $cat->term_id . '">';
				$html .= '<h3 id="ts-topic-path-' . (int) $cat->term_id . '">' . esc_html( $cat->name ) . '</h3>';
				$html .= '<div class="ts-topic-pills__items">';
				foreach ( $tags as $tag ) {
					$html .= self::topic_pill( $tag );
				}
				$html .= '</div>';
				$html .= '</section>';
			}
			$html .= '</div>';
			$html .= '</section>';

			return $html;
		}

		protected static function related_tags_for_tag( $term ) {
			$ids = get_posts( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'tag_id'              => (int) $term->term_id,
				'posts_per_page'      => 50,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			) );
			if ( empty( $ids ) ) {
				return array();
			}

			$tags = wp_get_object_terms( $ids, 'post_tag' );
			if ( ! is_array( $tags ) || is_wp_error( $tags ) ) {
				return array();
			}

			$counts = array();
			foreach ( $tags as $tag ) {
				if ( (int) $tag->term_id === (int) $term->term_id ) {
					continue;
				}
				$key = (int) $tag->term_id;
				if ( ! isset( $counts[ $key ] ) ) {
					$counts[ $key ] = array( 'term' => $tag, 'seen' => 0 );
				}
				$counts[ $key ]['seen']++;
			}

			usort(
				$counts,
				function ( $a, $b ) {
					if ( $a['seen'] === $b['seen'] ) {
						return strcasecmp( $a['term']->name, $b['term']->name );
					}
					return $b['seen'] - $a['seen'];
				}
			);

			return array_map(
				function ( $item ) {
					return $item['term'];
				},
				array_slice( $counts, 0, 6 )
			);
		}

		protected static function tags_for_category( $cat_id, $limit ) {
			$ids = get_posts( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'cat'                 => (int) $cat_id,
				'posts_per_page'      => 30,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			) );
			if ( empty( $ids ) ) {
				return array();
			}

			$tags = wp_get_object_terms( $ids, 'post_tag', array(
				'orderby' => 'count',
				'order'   => 'DESC',
			) );

			if ( ! is_array( $tags ) || is_wp_error( $tags ) ) {
				return array();
			}

			return array_slice( $tags, 0, (int) $limit );
		}

		protected static function topic_pill( $tag ) {
			$count = (int) $tag->count;
			$label = (int) self::get( 'show_article_counts' )
				? sprintf(
					/* translators: 1: tag name, 2: article count */
					__( '%1$s, %2$s', 'epiktetos' ),
					$tag->name,
					self::article_count_label( $count, false )
				)
				: $tag->name;

			$html  = '<a class="ts-topic-pill" href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" aria-label="' . esc_attr( $label ) . '">';
			$html .= '<span>' . esc_html( $tag->name ) . '</span>';
			if ( (int) self::get( 'show_article_counts' ) ) {
				$html .= '<small>' . esc_html( (string) $count ) . '</small>';
			}
			$html .= '</a>';

			return $html;
		}

		protected static function render_archive_row( $post ) {
			if ( class_exists( 'Epiktetos_Archive' ) && method_exists( 'Epiktetos_Archive', 'render_row' ) ) {
				return Epiktetos_Archive::render_row( $post );
			}

			return '<article class="ts-archive-row"><div class="ts-archive-row__body"><h2 class="ts-archive-row__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2></div></article>';
		}

		protected static function ordered_categories() {
			$cats = class_exists( 'Epiktetos_Categories' )
				? Epiktetos_Categories::ordered_categories()
				: get_categories( array( 'hide_empty' => true ) );

			return is_array( $cats ) ? $cats : array();
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
	}

	Epiktetos_Taxonomies::init();
}
