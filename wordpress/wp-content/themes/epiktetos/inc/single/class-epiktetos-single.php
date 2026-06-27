<?php
/**
 * Epiktetos — single post reading experience.
 *
 * Renders the full editorial article via [epiktetos_single], used in
 * templates/single.html. One renderer keeps full control over heading-anchor
 * injection (for the TOC), reading time, related posts, author box, and the
 * newsletter CTA.
 *
 * Body content runs through the_content (so paragraphs stay correct); the
 * surrounding chrome is whitespace-collapsed so the block-template wpautop
 * pass can't inject stray <p>/<br>.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Single' ) ) {

	class Epiktetos_Single {

		const OPTION = 'epiktetos_single_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_single', array( __CLASS__, 'render' ) );
		}

		/* ---------------- Options ---------------- */

		public static function defaults() {
			return array(
				'show_reading_time' => 1,
				'show_progress'     => 1,
				'show_toc'          => 1,
				'show_related'      => 1,
				'show_newsletter'   => 1,
			);
		}

		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$def  = self::defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) ) {
				return $opts[ $key ];
			}
			return isset( $def[ $key ] ) ? $def[ $key ] : null;
		}

		public static function register_settings() {
			register_setting(
				'epiktetos_settings',
				self::OPTION,
				array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => self::defaults() )
			);
			add_settings_section(
				'epiktetos_single',
				__( 'Single Post', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Toggle reading aids shown on single articles.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);
			$fields = array(
				'show_reading_time' => __( 'Show reading time', 'epiktetos' ),
				'show_progress'     => __( 'Show reading progress bar', 'epiktetos' ),
				'show_toc'          => __( 'Show table of contents', 'epiktetos' ),
				'show_related'      => __( 'Show related articles', 'epiktetos' ),
				'show_newsletter'   => __( 'Show newsletter CTA', 'epiktetos' ),
			);
			foreach ( $fields as $key => $label ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$label,
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_single',
					array( 'key' => $key )
				);
			}
		}

		public static function render_field( $args ) {
			$key = $args['key'];
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				checked( 1, (int) self::get( $key ), false ),
				esc_html__( 'Enabled', 'epiktetos' )
			);
		}

		public static function sanitize( $input ) {
			$out = array();
			foreach ( array_keys( self::defaults() ) as $key ) {
				$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
			}
			return $out;
		}

		/* ---------------- Helpers ---------------- */

		/**
		 * Estimated reading time in whole minutes (≥1), at 200 wpm.
		 *
		 * @param string $content Raw post content.
		 * @return int
		 */
		public static function reading_time( $content ) {
			$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
			return max( 1, (int) ceil( $words / 200 ) );
		}

		/**
		 * Inject ids into <h2> headings and collect them for the TOC.
		 *
		 * @param string $html  Rendered content.
		 * @param array  $toc   Filled with array( id, text ) per h2 (by ref).
		 * @return string Content with heading ids.
		 */
		protected static function add_heading_anchors( $html, &$toc ) {
			$toc = array();
			$i   = 0;
			$html = preg_replace_callback(
				'/<h2\b([^>]*)>(.*?)<\/h2>/is',
				function ( $m ) use ( &$toc, &$i ) {
					$i++;
					$attrs = $m[1];
					$text  = trim( wp_strip_all_tags( $m[2] ) );
					// Reuse an existing id, otherwise inject one.
					if ( preg_match( '/\bid=("|\')(.*?)\1/', $attrs, $idm ) ) {
						$id = $idm[2];
					} else {
						$id     = 'section-' . $i;
						$attrs .= ' id="' . esc_attr( $id ) . '"';
					}
					$toc[] = array( 'id' => $id, 'text' => $text );
					return '<h2' . $attrs . '>' . $m[2] . '</h2>';
				},
				$html
			);
			return $html;
		}

		/* ---------------- Render ---------------- */

		public static function render() {
			if ( ! is_singular( 'post' ) ) {
				return '';
			}
			$post = get_post();
			if ( ! $post ) {
				return '';
			}
			setup_postdata( $post );

			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			/* ---- Body (the_content) with TOC anchors ---- */
			$body = apply_filters( 'the_content', $post->post_content );
			$toc  = array();
			if ( self::get( 'show_toc' ) ) {
				$body = self::add_heading_anchors( $body, $toc );
			}

			/* ---- Article header ---- */
			$cats     = get_the_category( $post->ID );
			$cat      = ! empty( $cats ) ? $cats[0] : null;
			$head  = '<header class="ts-article__head">';
			$head .= '<div class="ts-article__headinner">';
			if ( $cat ) {
				$head .= '<div class="ts-article__kicker"><a class="ts-article__category" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">' . esc_html( $cat->name ) . '</a></div>';
			}
			$head .= '<h1 class="ts-article__title">' . esc_html( $title ) . '</h1>';
			$deck = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
			if ( $deck ) {
				$head .= '<p class="ts-article__deck">' . esc_html( $deck ) . '</p>';
			}

			// Meta row: author · date · reading time.
			$meta  = '<div class="ts-article__meta">';
			$author_id   = (int) $post->post_author;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			if ( $author_name ) {
				$meta .= '<span class="ts-article__author">' . esc_html( $author_name ) . '</span>';
			}
			$meta .= '<time class="ts-article__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
			if ( self::get( 'show_reading_time' ) ) {
				$rt = self::reading_time( $post->post_content );
				$meta .= '<span class="ts-article__readtime">' . esc_html( sprintf( /* translators: %d minutes */ _n( '%d min read', '%d min read', $rt, 'epiktetos' ), $rt ) ) . '</span>';
			}
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$meta .= Epiktetos_Reader::completion_badge( $post );
				$meta .= Epiktetos_Reader::updated_badge( $post, 'ts-article__updated' );
			}
			$meta .= '</div>';
			$head .= $meta;
			$head .= '</div>'; // headinner

			// Featured image (wide).
			if ( has_post_thumbnail( $post ) ) {
				$head .= '<figure class="ts-article__media">'
					. get_the_post_thumbnail( $post, 'large', array( 'class' => 'ts-article__img', 'alt' => esc_attr( $title ), 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) )
					. '</figure>';
			}
			$head .= '</header>';

			/* ---- Tools (copy link + share) ---- */
			$tools  = '<div class="ts-article__tools" aria-label="' . esc_attr__( 'Article tools', 'epiktetos' ) . '">';
			$tools .= '<button type="button" class="ts-tool ts-tool--copy" data-ts-copy data-url="' . esc_url( $permalink ) . '" data-copy-label="' . esc_attr__( 'Copy link', 'epiktetos' ) . '" data-copied-label="' . esc_attr__( 'Link copied', 'epiktetos' ) . '" aria-label="' . esc_attr__( 'Copy link', 'epiktetos' ) . '" aria-describedby="ts-copy-status"><span class="ts-tool__label">' . esc_html__( 'Copy link', 'epiktetos' ) . '</span></button>';
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$tools .= Epiktetos_Reader::save_button( $post, 'ts-tool' );
			}
			$tools .= '<button type="button" class="ts-tool ts-tool--native" data-ts-native-share data-share-title="' . esc_attr( $title ) . '" data-share-text="' . esc_attr( $deck ? $deck : $title ) . '" data-share-url="' . esc_url( $permalink ) . '" aria-label="' . esc_attr__( 'Share article', 'epiktetos' ) . '" hidden><span class="ts-tool__label">' . esc_html__( 'Share', 'epiktetos' ) . '</span></button>';
			$tools .= '<a class="ts-tool ts-tool--icon" href="https://twitter.com/intent/tweet?url=' . rawurlencode( $permalink ) . '&text=' . rawurlencode( $title ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'Share on X', 'epiktetos' ) . '">' . self::icon( 'x' ) . '</a>';
			$tools .= '<a class="ts-tool ts-tool--icon" href="https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $permalink ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'Share on LinkedIn', 'epiktetos' ) . '">' . self::icon( 'linkedin' ) . '</a>';
			$tools .= '<span class="screen-reader-text" id="ts-copy-status" role="status" aria-live="polite" data-ts-copy-status></span>';
			$tools .= '</div>';

			/* ---- TOC ---- */
			$toc_html = '';
			if ( self::get( 'show_toc' ) && count( $toc ) >= 2 ) {
				$items = '';
				foreach ( $toc as $t ) {
					$items .= '<li><a href="#' . esc_attr( $t['id'] ) . '">' . esc_html( $t['text'] ) . '</a></li>';
				}
				$toc_html  = '<aside class="ts-toc" aria-label="' . esc_attr__( 'Table of contents', 'epiktetos' ) . '">';
				$toc_html .= '<p class="ts-toc__title">' . esc_html__( 'Contents', 'epiktetos' ) . '</p>';
				$toc_html .= '<ul class="ts-toc__list">' . $items . '</ul>';
				$toc_html .= '</aside>';
			}

			/* ---- Body wrapper (the_content is left intact) ---- */
			$body_html = '<div class="ts-article__body">' . $tools . $body . '</div>';

			/* ---- End matter ---- */
			$end  = '<div class="ts-article__end">';
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				$end .= self::compress( Epiktetos_Reader::article_finish_module( $post, $cat ) );
				$end .= self::compress( Epiktetos_Reader::streak_module() );
				$end .= self::compress( Epiktetos_Reader::history_module( 'single' ) );
			}
			$end .= self::compress( self::render_author( $author_id ) );
			$end .= self::compress( self::render_prevnext( $post ) );
			$end .= self::compress( self::render_related( $post, $cat ) );
			if ( class_exists( 'Epiktetos_Comments' ) && method_exists( 'Epiktetos_Comments', 'render_statistics' ) ) {
				$end .= Epiktetos_Comments::render_statistics( $post );
			}
			if ( class_exists( 'Epiktetos_Comments' ) && method_exists( 'Epiktetos_Comments', 'render' ) ) {
				$end .= Epiktetos_Comments::render( $post );
			}
			if ( self::get( 'show_newsletter' ) ) {
				$end .= self::compress( self::render_cta() );
			}
			$end .= '</div>';

			/* ---- Progress bar ---- */
			$progress = self::get( 'show_progress' )
				? '<div class="ts-reading-progress" aria-hidden="true"><span class="ts-reading-progress__bar" data-ts-progress></span></div>'
				: '';

			$chrome = self::compress( $progress . $head . '<div class="ts-article__layout">' );
			$chrome_close = self::compress( ( $toc_html ? $toc_html : '' ) . '</div>' . $end );

			wp_reset_postdata();

			// Body kept un-collapsed so the_content structure is preserved.
			$attrs = class_exists( 'Epiktetos_Reader' ) ? Epiktetos_Reader::article_data_attrs( $post ) : '';
			return '<article class="ts-article"' . $attrs . '>' . $chrome . $body_html . $chrome_close . '</article>';
		}

		/* ---- End-matter pieces ---- */

		protected static function render_author( $author_id ) {
			if ( class_exists( 'Epiktetos_Pages' ) && method_exists( 'Epiktetos_Pages', 'render_single_author_box' ) ) {
				return Epiktetos_Pages::render_single_author_box( $author_id );
			}

			$name = get_the_author_meta( 'display_name', $author_id );
			if ( ! $name ) {
				return '';
			}
			$bio = get_the_author_meta( 'description', $author_id );
			$url = get_author_posts_url( $author_id );

			$h  = '<div class="ts-author">';
			$h .= '<div class="ts-author__body">';
			$h .= '<p class="ts-author__eyebrow">' . esc_html__( 'Written by', 'epiktetos' ) . '</p>';
			$h .= '<p class="ts-author__name"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></p>';
			if ( $bio ) {
				$h .= '<p class="ts-author__bio">' . esc_html( $bio ) . '</p>';
			}
			$h .= '</div>';
			$h .= '</div>';
			return $h;
		}

		protected static function render_prevnext( $post ) {
			$prev = get_previous_post();
			$next = get_next_post();
			if ( ! $prev && ! $next ) {
				return '';
			}
			$h = '<nav class="ts-prevnext" aria-label="' . esc_attr__( 'More articles', 'epiktetos' ) . '">';
			if ( $prev ) {
				$h .= '<a class="ts-prevnext__item ts-prevnext__prev" href="' . esc_url( get_permalink( $prev ) ) . '">'
					. '<span class="ts-prevnext__label">' . esc_html__( 'Previous', 'epiktetos' ) . '</span>'
					. '<span class="ts-prevnext__title">' . esc_html( get_the_title( $prev ) ) . '</span></a>';
			}
			if ( $next ) {
				$h .= '<a class="ts-prevnext__item ts-prevnext__next" href="' . esc_url( get_permalink( $next ) ) . '">'
					. '<span class="ts-prevnext__label">' . esc_html__( 'Next', 'epiktetos' ) . '</span>'
					. '<span class="ts-prevnext__title">' . esc_html( get_the_title( $next ) ) . '</span></a>';
			}
			$h .= '</nav>';
			return $h;
		}

		protected static function render_related( $post, $cat ) {
			if ( ! self::get( 'show_related' ) ) {
				return '';
			}
			$args = array(
				'numberposts'         => 3,
				'post_status'         => 'publish',
				'post__not_in'        => array( $post->ID ),
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			);
			if ( $cat ) {
				$args['category'] = $cat->term_id;
			}
			$related = get_posts( $args );
			if ( count( $related ) < 3 ) {
				// Fallback: top up with newest posts (no duplicates).
				$have = wp_list_pluck( $related, 'ID' );
				$fill = get_posts( array(
					'numberposts'  => 3,
					'post_status'  => 'publish',
					'post__not_in' => array_merge( array( $post->ID ), $have ),
				) );
				$related = array_slice( array_merge( $related, $fill ), 0, 3 );
			}
			if ( empty( $related ) ) {
				return '';
			}

			$cards = '';
			foreach ( $related as $r ) {
				$rp = get_permalink( $r );
				$rt = get_the_title( $r );
				$rc = get_the_category( $r->ID );
				$rcn = ! empty( $rc ) ? $rc[0]->name : '';
				$media = has_post_thumbnail( $r )
					? '<figure class="ts-rel__media"><a class="ts-rel__media-link" href="' . esc_url( $rp ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $r, 'medium_large', array( 'class' => 'ts-rel__img', 'alt' => esc_attr( $rt ), 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</a></figure>'
					: '<figure class="ts-rel__media ts-rel__media--ph"><a class="ts-rel__media-link" href="' . esc_url( $rp ) . '" tabindex="-1" aria-hidden="true"><span class="ts-rel__ph"></span></a></figure>';
				$cards .= '<article class="ts-rel__card">' . $media
					. '<div class="ts-rel__body">'
					. ( $rcn ? '<div class="ts-rel__cat">' . esc_html( $rcn ) . '</div>' : '' )
					. '<h3 class="ts-rel__title"><a href="' . esc_url( $rp ) . '">' . esc_html( $rt ) . '</a></h3>'
					. '</div></article>';
			}
			return '<section class="ts-related" aria-label="' . esc_attr__( 'Related articles', 'epiktetos' ) . '">'
				. '<h2 class="ts-related__title">' . esc_html__( 'Related Articles', 'epiktetos' ) . '</h2>'
				. '<div class="ts-related__grid">' . $cards . '</div></section>';
		}

		protected static function render_cta() {
			return '<section class="ts-cta" aria-label="' . esc_attr__( 'Subscribe', 'epiktetos' ) . '">'
				. '<h2 class="ts-cta__title">' . esc_html__( 'Receive new essays quietly.', 'epiktetos' ) . '</h2>'
				. '<p class="ts-cta__text">' . esc_html__( 'A short note when something worth reading is published. No noise.', 'epiktetos' ) . '</p>'
				. '<form class="ts-news" method="post" action="#" novalidate>'
				. '<div class="ts-news__field">'
				. '<input type="email" class="ts-news__input" name="email" placeholder="' . esc_attr__( 'Your email', 'epiktetos' ) . '" aria-label="' . esc_attr__( 'Email address', 'epiktetos' ) . '" inputmode="email" autocomplete="email" />'
				. '<button type="submit" class="ts-news__submit" aria-label="' . esc_attr__( 'Subscribe', 'epiktetos' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>'
				. '</div></form>'
				. '<p class="ts-news__note" role="status" aria-live="polite">' . esc_html__( 'Subscribe via RSS while email delivery is offline.', 'epiktetos' ) . '</p>'
				. '</section>';
		}

		protected static function icon( $slug ) {
			if ( class_exists( 'Epiktetos_Header' ) && method_exists( 'Epiktetos_Header', 'inline_icon' ) ) {
				return Epiktetos_Header::inline_icon( $slug );
			}
			$slug = preg_replace( '/[^a-z0-9_-]/', '', $slug );
			$path = trailingslashit( get_template_directory() ) . 'assets/icons/' . $slug . '.svg';
			return file_exists( $path ) ? self::compress( file_get_contents( $path ) ) : '';
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Single::init();
}
