<?php
/**
 * Epiktetos - reader intelligence and publication polish.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Reader' ) ) {

	class Epiktetos_Reader {

		const OPTION = 'epiktetos_reader_options';
		const PICKS_TRANSIENT = 'epiktetos_editor_picks';
		const STATS_TRANSIENT = 'epiktetos_publication_stats';
		const SAVED_QUERY_VAR = 'epiktetos_saved';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
			add_action( 'init', array( __CLASS__, 'register_saved_route' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
			add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
			add_action( 'save_post_post', array( __CLASS__, 'clear_editor_picks_cache' ) );
		}

		public static function register_shortcodes() {
			add_shortcode( 'epiktetos_saved', array( __CLASS__, 'render_saved_page' ) );
		}

		public static function register_saved_route() {
			add_rewrite_rule( '^saved/?$', 'index.php?' . self::SAVED_QUERY_VAR . '=1', 'top' );
		}

		public static function query_vars( $vars ) {
			$vars[] = self::SAVED_QUERY_VAR;
			return $vars;
		}

		public static function template_include( $template ) {
			$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
			if ( get_query_var( self::SAVED_QUERY_VAR ) || 'saved' === $path ) {
				$saved = trailingslashit( get_template_directory() ) . 'templates/saved.php';
				if ( file_exists( $saved ) ) {
					status_header( 200 );
					return $saved;
				}
			}
			return $template;
		}

		public static function defaults() {
			return array(
				'enable_history'       => 1,
				'enable_read_later'    => 1,
				'enable_completion'    => 1,
				'enable_quote_copy'    => 1,
				'enable_image_zoom'    => 1,
				'enable_streak'        => 1,
				'enable_stats'         => 1,
				'enable_editor_picks'  => 1,
				'editor_picks'         => array(),
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
				'epiktetos_reader_features',
				__( 'Reader features', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Local-only reader convenience features. No account data is stored on the server.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);
			add_settings_section(
				'epiktetos_editor_picks',
				__( 'Editor Picks', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Curated articles used by recommendations and discovery modules.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'enable_history'      => __( 'Enable Reading History', 'epiktetos' ),
				'enable_read_later'   => __( 'Enable Read Later', 'epiktetos' ),
				'enable_completion'   => __( 'Enable Estimated Completion', 'epiktetos' ),
				'enable_quote_copy'   => __( 'Enable Quote Copy', 'epiktetos' ),
				'enable_image_zoom'   => __( 'Enable Image Zoom', 'epiktetos' ),
				'enable_streak'       => __( 'Enable Reading Streak', 'epiktetos' ),
				'enable_stats'        => __( 'Enable Publication Stats', 'epiktetos' ),
				'enable_editor_picks' => __( 'Enable Editor Picks', 'epiktetos' ),
			);

			foreach ( $fields as $key => $label ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$label,
					array( __CLASS__, 'render_toggle_field' ),
					'epiktetos-settings',
					'epiktetos_reader_features',
					array( 'key' => $key )
				);
			}

			add_settings_field(
				self::OPTION . '_editor_picks',
				__( 'Editor Picks', 'epiktetos' ),
				array( __CLASS__, 'render_editor_picks_field' ),
				'epiktetos-settings',
				'epiktetos_editor_picks',
				array( 'key' => 'editor_picks' )
			);
		}

		public static function render_toggle_field( $args ) {
			$key = $args['key'];
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				checked( 1, (int) self::get( $key ), false ),
				esc_html__( 'Enabled', 'epiktetos' )
			);
		}

		public static function render_editor_picks_field() {
			$value = self::get( 'editor_picks' );
			$value = is_array( $value ) ? array_map( 'intval', $value ) : array();
			$posts = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 80,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'suppress_filters'    => false,
				)
			);

			echo '<div class="epi-picks-field">';
			for ( $i = 0; $i < 10; $i++ ) {
				$selected = isset( $value[ $i ] ) ? (int) $value[ $i ] : 0;
				echo '<label><span>' . esc_html( sprintf( /* translators: %d: pick number */ __( 'Pick %d', 'epiktetos' ), $i + 1 ) ) . '</span>';
				echo '<select name="' . esc_attr( self::OPTION ) . '[editor_picks][]">';
				echo '<option value="0">' . esc_html__( 'Select article', 'epiktetos' ) . '</option>';
				foreach ( $posts as $post ) {
					printf(
						'<option value="%1$d" %2$s>%3$s</option>',
						(int) $post->ID,
						selected( $selected, (int) $post->ID, false ),
						esc_html( get_the_title( $post ) )
					);
				}
				echo '</select></label>';
			}
			echo '<p class="description">' . esc_html__( 'Up to 10 posts used by homepage, search fallback, About, and article recommendations.', 'epiktetos' ) . '</p>';
			echo '</div>';
		}

		public static function sanitize( $input ) {
			$defs = self::defaults();
			$out  = array();
			foreach ( array( 'enable_history', 'enable_read_later', 'enable_completion', 'enable_quote_copy', 'enable_image_zoom', 'enable_streak', 'enable_stats', 'enable_editor_picks' ) as $key ) {
				$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
			}
			$picks = isset( $input['editor_picks'] ) && is_array( $input['editor_picks'] ) ? $input['editor_picks'] : array();
			$picks = array_values( array_unique( array_filter( array_map( 'intval', $picks ) ) ) );
			$out['editor_picks'] = array_slice( $picks, 0, 10 );
			delete_transient( self::PICKS_TRANSIENT );
			return array_merge( $defs, $out );
		}

		public static function clear_editor_picks_cache() {
			delete_transient( self::PICKS_TRANSIENT );
			delete_transient( self::STATS_TRANSIENT );
		}

		public static function editor_picks( $limit = 4, $exclude = array() ) {
			if ( ! (int) self::get( 'enable_editor_picks' ) ) {
				return array();
			}
			$ids = get_transient( self::PICKS_TRANSIENT );
			if ( false === $ids ) {
				$ids = self::get( 'editor_picks' );
				$ids = is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
				set_transient( self::PICKS_TRANSIENT, $ids, HOUR_IN_SECONDS );
			}
			$exclude = array_map( 'intval', (array) $exclude );
			$ids = array_values( array_diff( $ids, $exclude ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$ids = array_slice( $ids, 0, (int) $limit );
			$posts = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'post__in'            => $ids,
					'orderby'             => 'post__in',
					'posts_per_page'      => count( $ids ),
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'suppress_filters'    => false,
				)
			);
			return $posts;
		}

		public static function article_data_attrs( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return '';
			}
			$minutes = class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : 1;
			$attrs = array(
				'data-ts-article-id'       => (int) $post->ID,
				'data-ts-article-title'    => get_the_title( $post ),
				'data-ts-article-url'      => get_permalink( $post ),
				'data-ts-article-minutes'  => $minutes,
				'data-ts-reader-settings'  => wp_json_encode( self::client_settings() ),
			);
			$out = '';
			foreach ( $attrs as $key => $value ) {
				$out .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			return $out;
		}

		public static function client_settings() {
			return array(
				'history'    => (bool) self::get( 'enable_history' ),
				'readLater'  => (bool) self::get( 'enable_read_later' ),
				'completion' => (bool) self::get( 'enable_completion' ),
				'quoteCopy'  => (bool) self::get( 'enable_quote_copy' ),
				'imageZoom'  => (bool) self::get( 'enable_image_zoom' ),
				'streak'     => (bool) self::get( 'enable_streak' ),
				'savedUrl'   => home_url( '/saved/' ),
			);
		}

		public static function completion_badge( $post ) {
			if ( ! (int) self::get( 'enable_completion' ) || ! $post instanceof WP_Post ) {
				return '';
			}
			$minutes = class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : 1;
			return '<span class="ts-article__remaining" data-ts-completion data-total-minutes="' . esc_attr( $minutes ) . '">' . esc_html( sprintf( /* translators: %d: minutes */ _n( '%d min left', '%d min left', $minutes, 'epiktetos' ), $minutes ) ) . '</span>';
		}

		public static function save_button( $post, $class = 'ts-tool' ) {
			if ( ! (int) self::get( 'enable_read_later' ) || ! $post instanceof WP_Post ) {
				return '';
			}
			return '<button type="button" class="' . esc_attr( $class . ' ts-save-button' ) . '" data-ts-save data-article-id="' . esc_attr( $post->ID ) . '" data-title="' . esc_attr( get_the_title( $post ) ) . '" data-url="' . esc_url( get_permalink( $post ) ) . '" data-saved-label="' . esc_attr__( 'Saved', 'epiktetos' ) . '" data-unsaved-label="' . esc_attr__( 'Save for later', 'epiktetos' ) . '" aria-pressed="false" aria-label="' . esc_attr__( 'Save for later', 'epiktetos' ) . '"><span class="ts-save-button__icon" aria-hidden="true">☆</span><span class="ts-tool__label ts-save-button__label">' . esc_html__( 'Save', 'epiktetos' ) . '</span></button>';
		}

		public static function updated_badge( $post, $class = '' ) {
			if ( ! $post instanceof WP_Post || ! self::is_recently_updated( $post ) ) {
				return '';
			}
			$class = $class ? ' ' . $class : '';
			return '<span class="ts-updated-badge' . esc_attr( $class ) . '">' . esc_html__( 'Updated', 'epiktetos' ) . '</span>';
		}

		public static function is_recently_updated( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return false;
			}
			$published = get_post_time( 'U', true, $post );
			$modified  = get_post_modified_time( 'U', true, $post );
			return $modified && $published && ( $modified - $published ) > DAY_IN_SECONDS;
		}

		public static function history_module( $context = 'default' ) {
			if ( ! (int) self::get( 'enable_history' ) ) {
				return '';
			}
			$title = epiktetos_label( 'continue_reading_title', __( 'Continue Reading', 'epiktetos' ) );
			return '<aside class="ts-reader-card ts-reader-history" data-ts-history-module data-context="' . esc_attr( $context ) . '" hidden><h2>' . esc_html( $title ) . '</h2><div class="ts-reader-history__list" data-ts-history-list></div></aside>';
		}

		public static function streak_module() {
			if ( ! (int) self::get( 'enable_streak' ) ) {
				return '';
			}
			return '<div class="ts-reader-card ts-reading-streak" data-ts-streak hidden><p class="ts-reading-streak__label">' . esc_html__( 'Today you have read', 'epiktetos' ) . '</p><p class="ts-reading-streak__value" data-ts-streak-value></p></div>';
		}

		public static function article_finish_module( $post, $cat = null ) {
			$recommendation = self::recommended_post( $post, $cat );
			$html  = '<section class="ts-article-finished" aria-labelledby="ts-article-finished-title" data-ts-finished>';
			$html .= '<p class="ts-article-finished__mark" aria-hidden="true">✓</p>';
			$html .= '<h2 id="ts-article-finished-title">' . esc_html__( 'Finished reading', 'epiktetos' ) . '</h2>';
			if ( $recommendation ) {
				$html .= '<p>' . esc_html__( 'Recommended next article', 'epiktetos' ) . '</p>';
				$html .= '<a href="' . esc_url( get_permalink( $recommendation ) ) . '">' . esc_html( get_the_title( $recommendation ) ) . '</a>';
			}
			$html .= '</section>';
			return $html;
		}

		public static function recommended_post( $post, $cat = null ) {
			if ( ! $post instanceof WP_Post ) {
				return null;
			}
			$picks = self::editor_picks( 1, array( $post->ID ) );
			if ( ! empty( $picks ) ) {
				return $picks[0];
			}
			$args = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'post__not_in'        => array( $post->ID ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			);
			if ( $cat instanceof WP_Term ) {
				$args['cat'] = (int) $cat->term_id;
			}
			$posts = get_posts( $args );
			return ! empty( $posts ) ? $posts[0] : null;
		}

		public static function editor_picks_module( $context = 'default', $limit = 3, $exclude = array() ) {
			$posts = self::editor_picks( $limit, $exclude );
			if ( empty( $posts ) ) {
				return '';
			}
			$html  = '<aside class="ts-reader-card ts-editor-picks ts-editor-picks--' . esc_attr( $context ) . '" aria-labelledby="ts-editor-picks-' . esc_attr( $context ) . '">';
			$html .= '<h2 id="ts-editor-picks-' . esc_attr( $context ) . '">' . esc_html( epiktetos_label( 'editor_picks_title', __( 'Editor Picks', 'epiktetos' ) ) ) . '</h2>';
			$html .= '<div class="ts-editor-picks__list">';
			foreach ( $posts as $post ) {
				$html .= '<article><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3><p>' . esc_html( get_the_date( '', $post ) ) . '</p></article>';
			}
			$html .= '</div></aside>';
			return $html;
		}

		public static function publication_stats_module() {
			if ( ! (int) self::get( 'enable_stats' ) ) {
				return '';
			}
			$data  = self::publication_stats();
			// Labels are applied at render time (not cached) so Settings edits take
			// effect immediately; only the computed values are cached.
			$rows  = array(
				epiktetos_label( 'stats_label_articles', __( 'Articles', 'epiktetos' ) )              => $data['articles'],
				epiktetos_label( 'stats_label_categories', __( 'Categories', 'epiktetos' ) )          => $data['categories'],
				epiktetos_label( 'stats_label_topics', __( 'Topics', 'epiktetos' ) )                  => $data['topics'],
				epiktetos_label( 'stats_label_reading', __( 'Average reading time', 'epiktetos' ) )   => $data['reading'],
				epiktetos_label( 'stats_label_updated', __( 'Last updated', 'epiktetos' ) )           => '' !== $data['updated'] ? $data['updated'] : epiktetos_label( 'stats_updated_soon', __( 'Soon', 'epiktetos' ) ),
			);
			$html  = '<aside class="ts-reader-card ts-publication-stats" aria-labelledby="ts-publication-stats-title">';
			$html .= '<h2 id="ts-publication-stats-title">' . esc_html( epiktetos_label( 'stats_title', __( 'Publication Stats', 'epiktetos' ) ) ) . '</h2>';
			$html .= '<dl>';
			foreach ( $rows as $label => $value ) {
				$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			$html .= '</dl></aside>';
			return $html;
		}

		protected static function publication_stats() {
			$cached = get_transient( self::STATS_TRANSIENT );
			// Only reuse the cache if it is the current (label-free) data shape.
			if ( is_array( $cached ) && isset( $cached['articles'] ) ) {
				return $cached;
			}
			$post_count = (int) wp_count_posts( 'post' )->publish;
			$cats_count = wp_count_terms( 'category', array( 'hide_empty' => true ) );
			$tags_count = wp_count_terms( 'post_tag', array( 'hide_empty' => true ) );
			$cats       = is_wp_error( $cats_count ) ? 0 : (int) $cats_count;
			$tags       = is_wp_error( $tags_count ) ? 0 : (int) $tags_count;
			$latest     = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 1,
					'orderby'             => 'modified',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);
			$sample = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 30,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'fields'              => 'ids',
				)
			);
			$total = 0;
			foreach ( $sample as $id ) {
				$post = get_post( $id );
				$total += $post && class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : 1;
			}
			$avg = ! empty( $sample ) ? max( 1, (int) round( $total / count( $sample ) ) ) : 1;
			// Data only, keyed by stable internal keys. Visible labels + the empty
			// "Last updated" fallback are applied (and editable) at render time.
			$stats = array(
				'articles'   => number_format_i18n( $post_count ),
				'categories' => number_format_i18n( $cats ),
				'topics'     => number_format_i18n( $tags ),
				'reading'    => sprintf( /* translators: %d: minutes */ _n( '%d min', '%d min', $avg, 'epiktetos' ), $avg ),
				'updated'    => ! empty( $latest ) ? get_the_modified_date( '', $latest[0] ) : '',
			);
			set_transient( self::STATS_TRANSIENT, $stats, 30 * MINUTE_IN_SECONDS );
			return $stats;
		}

		public static function render_saved_page() {
			$html  = '<section class="ts-saved-page" aria-labelledby="ts-saved-title" data-ts-saved-page>';
			$html .= '<div class="ts-saved-page__inner">';
			$html .= '<header class="ts-saved-page__header"><p class="ts-page__eyebrow">' . esc_html__( 'Read Later', 'epiktetos' ) . '</p><h1 id="ts-saved-title">' . esc_html__( 'Saved Articles', 'epiktetos' ) . '</h1><p>' . esc_html__( 'Articles saved in this browser stay private and local.', 'epiktetos' ) . '</p></header>';
			$html .= '<div class="ts-saved-page__list" data-ts-saved-list></div>';
			$html .= '<div class="ts-saved-page__empty" data-ts-saved-empty role="status"><p>' . esc_html__( 'No articles saved yet.', 'epiktetos' ) . '</p></div>';
			$html .= '</div></section>';
			return self::compress( $html );
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Reader::init();
}
