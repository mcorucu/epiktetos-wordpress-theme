<?php
/**
 * Epiktetos — homepage Category Showcase.
 *
 * Renders one editorial block per public category (auto-discovered) via the
 * [epiktetos_category_showcase] shortcode, used in front-page.html below the
 * Latest Articles section.
 *
 * Per category: a featured article + up to two secondary articles, creating
 * editorial hierarchy. Category order is admin-managed (drag & drop in
 * Appearance → Epiktetos), stored in its own option, with alphabetical
 * fallback and automatic append of newly created categories.
 *
 * Markup is wpautop-proof: every direct child of a processed container is
 * block-level (section / article / figure / div / h2-h4 / p / ul / li).
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Categories' ) ) {

	class Epiktetos_Categories {

		const ORDER_OPTION = 'epiktetos_category_order';
		const CACHE_KEY    = 'epiktetos_cat_showcase';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'admin_init', array( __CLASS__, 'normalize_saved_order' ), 20 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

			// Cache invalidation.
			foreach ( array( 'save_post', 'deleted_post', 'edited_term', 'created_term', 'delete_term', 'switch_theme' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'flush_cache' ) );
			}
			foreach ( array( 'created_category', 'edited_category', 'delete_category' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'normalize_saved_order' ) );
			}
			add_action( 'update_option_' . self::ORDER_OPTION, array( __CLASS__, 'flush_cache' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_category_showcase', array( __CLASS__, 'render' ) );
		}

		public static function flush_cache() {
			delete_transient( self::CACHE_KEY );
		}

		/* ============================================================
		   Category ordering
		   ============================================================ */

		/**
		 * Registered public categories, excluding the default bucket.
		 *
		 * @return WP_Term[]
		 */
		protected static function all_categories() {
			$exclude = array();
			$uncat   = get_category_by_slug( 'uncategorized' );
			if ( $uncat ) {
				$exclude[] = (int) $uncat->term_id;
			}
			$cats    = get_categories( array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'exclude'    => $exclude,
			) );
			return is_array( $cats ) ? $cats : array();
		}

		/**
		 * Categories in the admin-saved order. New categories are appended
		 * alphabetically and deleted categories are ignored.
		 *
		 * @return WP_Term[]
		 */
		public static function ordered_categories() {
			$cats = self::all_categories();
			if ( empty( $cats ) ) {
				return array();
			}

			$by_id = array();
			foreach ( $cats as $c ) {
				$by_id[ (int) $c->term_id ] = $c;
			}

			$saved   = (array) get_option( self::ORDER_OPTION, array() );
			$ordered = array();

			foreach ( $saved as $id ) {
				$id = (int) $id;
				if ( isset( $by_id[ $id ] ) ) {
					$ordered[] = $by_id[ $id ];
					unset( $by_id[ $id ] );
				}
			}
			// Append any categories not present in the saved order (already
			// alphabetical from all_categories()).
			foreach ( $by_id as $c ) {
				$ordered[] = $c;
			}

			return $ordered;
		}

		/* ============================================================
		   Settings — Category Order (drag & drop)
		   ============================================================ */

		public static function register_settings() {
			register_setting(
				'epiktetos_settings',
				self::ORDER_OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_order' ),
					'default'           => array(),
				)
			);

			add_settings_section(
				'epiktetos_category_order',
				__( 'Category Order', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Drag to reorder how categories appear in the homepage showcase. New categories are appended automatically until reordered.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			add_settings_field(
				self::ORDER_OPTION,
				__( 'Showcase order', 'epiktetos' ),
				array( __CLASS__, 'render_order_field' ),
				'epiktetos-settings',
				'epiktetos_category_order'
			);
		}

		/**
		 * Sanitize the saved order into a list of valid term IDs.
		 *
		 * @param mixed $input Raw value (CSV string from the hidden field).
		 * @return int[]
		 */
		public static function sanitize_order( $input ) {
			if ( is_string( $input ) ) {
				$input = array_filter( array_map( 'trim', explode( ',', $input ) ) );
			}
			return self::canonical_order_ids( (array) $input );
		}

		public static function normalize_saved_order() {
			$current = (array) get_option( self::ORDER_OPTION, array() );
			$next    = self::canonical_order_ids( $current );
			if ( array_map( 'intval', $current ) !== $next ) {
				update_option( self::ORDER_OPTION, $next );
			}
		}

		protected static function canonical_order_ids( $input ) {
			$registered = array();
			foreach ( self::all_categories() as $cat ) {
				$registered[ (int) $cat->term_id ] = (int) $cat->term_id;
			}
			$ids = array();
			foreach ( (array) $input as $id ) {
				$id = (int) $id;
				if ( isset( $registered[ $id ] ) ) {
					$ids[] = $id;
					unset( $registered[ $id ] );
				}
			}
			foreach ( $registered as $id ) {
				$ids[] = $id;
			}
			return array_values( array_unique( $ids ) );
		}

		/** Render the sortable category list + hidden value field. */
		public static function render_order_field() {
			$cats = self::ordered_categories();
			$ids  = array();
			echo '<ul class="epi-sortable" id="epi-category-sortable">';
			foreach ( $cats as $c ) {
				$ids[] = (int) $c->term_id;
				printf(
					'<li class="epi-sortable__item" data-id="%1$d"><span class="epi-sortable__handle" aria-hidden="true">⋮⋮</span><span class="epi-sortable__name">%2$s</span><span class="epi-sortable__count">%3$d</span></li>',
					(int) $c->term_id,
					esc_html( $c->name ),
					(int) $c->count
				);
			}
			echo '</ul>';
			printf(
				'<input type="hidden" id="epi-category-order-value" name="%1$s" value="%2$s" />',
				esc_attr( self::ORDER_OPTION ),
				esc_attr( implode( ',', $ids ) )
			);
			echo '<p class="description">' . esc_html__( 'If you have not reordered yet, categories show alphabetically.', 'epiktetos' ) . '</p>';
		}

		/** Enqueue drag-and-drop assets on the settings page only. */
		public static function admin_assets( $hook ) {
			$settings_hook = isset( $GLOBALS['epiktetos_settings_hook'] ) ? $GLOBALS['epiktetos_settings_hook'] : '';
			if ( ! $settings_hook || $hook !== $settings_hook ) {
				return;
			}
			wp_enqueue_script(
				'epiktetos-category-order',
				get_template_directory_uri() . '/assets/js/admin-category-order.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/js/admin-category-order.js' ) : null,
				true
			);
		}

		/* ============================================================
		   Render
		   ============================================================ */

		/**
		 * Shortcode renderer (transient-cached).
		 *
		 * @return string
		 */
		public static function render() {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}

			$cats = self::ordered_categories();
			if ( empty( $cats ) ) {
				return '';
			}

			$blocks = '';
			foreach ( $cats as $cat ) {
				$blocks .= self::render_category( $cat );
			}
			if ( '' === $blocks ) {
				return '';
			}

			$html  = '<section class="ts-cats" aria-label="' . esc_attr__( 'Browse by topic', 'epiktetos' ) . '">';
			$html .= '<div class="ts-cats__inner">' . $blocks . '</div>';
			$html .= '</section>';

			$html = self::compress( $html );
			set_transient( self::CACHE_KEY, $html, DAY_IN_SECONDS );
			return $html;
		}

		/**
		 * Render one category block: header + featured + secondary grid.
		 *
		 * @param WP_Term $cat Category term.
		 * @return string
		 */
		protected static function render_category( $cat ) {
			$args = array(
				'numberposts'         => 5,
				'post_status'         => 'publish',
				'category'            => $cat->term_id,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			);

			/**
			 * Optionally exclude hero-lead posts from the showcase. Default is
			 * false: with a small library, excluding each category's newest
			 * post (its hero lead) would collapse most categories to a single
			 * card and defeat the featured + two-secondary hierarchy. Flip this
			 * filter to true once each category has enough non-lead posts.
			 */
			if ( apply_filters( 'epiktetos_category_exclude_leads', false ) && class_exists( 'Epiktetos_Hero' ) ) {
				$args['post__not_in'] = Epiktetos_Hero::lead_post_ids();
			}

			$posts = get_posts( $args );
			if ( empty( $posts ) ) {
				return '';
			}

			$featured  = array_shift( $posts );
			$secondary = $posts; // up to 2

			$archive = get_category_link( $cat->term_id );
			$labelid = 'ts-cat-' . (int) $cat->term_id;

			// Header.
			$head  = '<div class="ts-cat__head">';
			$head .= '<div class="ts-cat__heading">';
			$head .= '<h2 class="ts-cat__name" id="' . esc_attr( $labelid ) . '">' . esc_html( $cat->name ) . '</h2>';
			if ( $cat->description ) {
				$head .= '<p class="ts-cat__desc">' . esc_html( wp_strip_all_tags( $cat->description ) ) . '</p>';
			}
			$head .= '</div>';
			$head .= '<div class="ts-cat__viewall"><a href="' . esc_url( $archive ) . '">'
				. esc_html( epiktetos_label( 'showcase_view_all', __( 'View all', 'epiktetos' ) ) )
				. '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
				. '</a></div>';
			$head .= '</div>';

			$featured_html = self::render_article( $featured, $cat, true );

			// Secondary posts become a quiet pair-slider. Group into chunks of
			// two; each chunk is one fade slide.
			$secondary_html = '';
			if ( ! empty( $secondary ) ) {
				$groups     = array_chunk( $secondary, 2 );
				$group_total = count( $groups );
				$is_slider  = $group_total > 1;

				$slides = '';
				foreach ( $groups as $gi => $group ) {
					$cards = '';
					foreach ( $group as $p ) {
						$cards .= self::render_article( $p, $cat, false );
					}
					$single = ( count( $group ) === 1 ) ? ' ts-cat__slide--single' : '';
					$slides .= '<div class="ts-cat__slide' . ( 0 === $gi ? ' is-active' : '' ) . $single . '"'
						. ' role="group" aria-roledescription="slide"'
						. ' aria-hidden="' . ( 0 === $gi ? 'false' : 'true' ) . '"'
						. ' data-ts-catslide="' . (int) $gi . '">' . $cards . '</div>';
				}

				$attr = $is_slider ? ' data-ts-catslider' : '';
				$secondary_html  = '<div class="ts-cat__secondary"' . $attr . '>';
				$secondary_html .= '<div class="ts-cat__slides">' . $slides . '</div>';

				if ( $is_slider ) {
					$next_label = sprintf( /* translators: %s category name */ __( 'Next articles in %s', 'epiktetos' ), $cat->name );
					$more_label = sprintf( /* translators: %s category name */ __( 'More in %s', 'epiktetos' ), $cat->name );
					$secondary_html .= '<div class="ts-cat__slider-nav">';
					$secondary_html .= '<span class="ts-cat__slider-label">' . esc_html( $more_label ) . '</span>';
					$secondary_html .= '<div class="ts-cat__slider-controls">';
					$secondary_html .= '<span class="ts-cat__counter" data-ts-catcounter aria-hidden="true">1&thinsp;/&thinsp;' . (int) $group_total . '</span>';
					$secondary_html .= '<button type="button" class="ts-cat__arrow" data-ts-catnext aria-label="' . esc_attr( $next_label ) . '">';
					$secondary_html .= '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
					$secondary_html .= '</button>';
					$secondary_html .= '</div>';
					$secondary_html .= '</div>';
				}

				$secondary_html .= '</div>';
			}

			$block  = '<section class="ts-cat" aria-labelledby="' . esc_attr( $labelid ) . '">';
			$block .= $head;
			$block .= '<div class="ts-cat__grid">';
			$block .= $featured_html;
			$block .= $secondary_html;
			$block .= '</div>';
			$block .= '</section>';

			return $block;
		}

		/**
		 * Render an article (featured or secondary).
		 *
		 * @param WP_Post $post     Post.
		 * @param WP_Term $cat      The category context (for the meta label).
		 * @param bool    $featured Whether this is the featured article.
		 * @return string
		 */
		protected static function render_article( $post, $cat, $featured ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );
			$variant   = $featured ? 'ts-cat__featured' : 'ts-cat__card';
			$htag      = $featured ? 'h3' : 'h3';
			$titlecls  = $featured ? 'ts-cat__title ts-cat__title--lg' : 'ts-cat__title';

			if ( has_post_thumbnail( $post ) ) {
				$size  = $featured ? 'large' : 'medium_large';
				$img   = get_the_post_thumbnail( $post, $size, array(
					'class'    => 'ts-cat__img',
					'alt'      => esc_attr( $title ),
					'loading'  => 'lazy',
					'decoding' => 'async',
				) );
				$media = '<figure class="ts-cat__media"><a class="ts-cat__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a></figure>';
			} else {
				$media = '<figure class="ts-cat__media ts-cat__media--placeholder"><a class="ts-cat__media-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true"><span class="ts-cat__placeholder"></span></a></figure>';
			}

			$meta  = '<div class="ts-cat__meta">';
			$meta .= '<span class="ts-cat__cat">' . esc_html( $cat->name ) . '</span>';
			$meta .= '<time class="ts-cat__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
			$meta .= '</div>';

			$words   = $featured ? 50 : 24;
			$excerpt = has_excerpt( $post )
				? ( $featured ? get_the_excerpt( $post ) : wp_trim_words( get_the_excerpt( $post ), $words ) )
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), $words );
			$excerpt_html = $excerpt ? '<p class="ts-cat__excerpt">' . esc_html( $excerpt ) . '</p>' : '';

			$cta_label = $featured
				? epiktetos_label( 'showcase_cta_featured', __( 'Read Article', 'epiktetos' ) )
				: epiktetos_label( 'showcase_cta', __( 'Read More', 'epiktetos' ) );
			$more = '<div class="ts-cat__more"><a class="ts-cat__more-link" href="' . esc_url( $permalink ) . '">'
				. esc_html( $cta_label )
				. '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>'
				. '</a></div>';

			$a  = '<article class="' . $variant . '">';
			$a .= $media;
			$a .= '<div class="ts-cat__body">';
			$a .= $meta;
			$a .= '<' . $htag . ' class="' . $titlecls . '"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></' . $htag . '>';
			$a .= $excerpt_html;
			$a .= $more;
			$a .= '</div>';
			$a .= '</article>';

			return $a;
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

	Epiktetos_Categories::init();
}
