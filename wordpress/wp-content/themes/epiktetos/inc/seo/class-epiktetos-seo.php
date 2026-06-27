<?php
/**
 * Epiktetos - SEO, metadata, and publication intelligence.
 *
 * Owns document titles, meta descriptions, canonicals, social cards,
 * JSON-LD, robots directives, and internal article intelligence metrics.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_SEO' ) ) {

	class Epiktetos_SEO {

		const OPTION = 'epiktetos_seo_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'remove_core_canonical' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
			add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_meta' ), 6 );
			add_action( 'save_post_post', array( __CLASS__, 'store_post_intelligence' ), 10, 3 );
			add_action( 'wp', array( __CLASS__, 'maybe_refresh_current_post_intelligence' ) );
		}

		public static function defaults() {
			return array(
				'publication_name'        => 'Epiktetos',
				'publication_description' => 'Essays on philosophy, psychology, history, technology, attention, and the examined life.',
				'default_og_image'        => '',
				'twitter_handle'          => '',
				'enable_json_ld'          => 1,
				'enable_open_graph'       => 1,
				'enable_canonicals'       => 1,
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

		public static function remove_core_canonical() {
			remove_action( 'wp_head', 'rel_canonical' );
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
				'epiktetos_seo_metadata',
				__( 'Metadata', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Publication-wide naming and description defaults.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);
			add_settings_section(
				'epiktetos_seo_social',
				__( 'Open Graph and social cards', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Social sharing metadata used when a post does not provide its own image.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);
			add_settings_section(
				'epiktetos_seo_technical',
				__( 'Technical output', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Control structured data, Open Graph tags, and canonical URLs.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'publication_name'        => array( 'label' => __( 'Publication Name', 'epiktetos' ), 'section' => 'epiktetos_seo_metadata', 'type' => 'text' ),
				'publication_description' => array( 'label' => __( 'Publication Description', 'epiktetos' ), 'section' => 'epiktetos_seo_metadata', 'type' => 'textarea' ),
				'default_og_image'        => array( 'label' => __( 'Default OG Image URL', 'epiktetos' ), 'section' => 'epiktetos_seo_social', 'type' => 'url', 'placeholder' => 'https://example.com/social-card.jpg' ),
				'twitter_handle'          => array( 'label' => __( 'Twitter/X Handle', 'epiktetos' ), 'section' => 'epiktetos_seo_social', 'type' => 'text', 'placeholder' => '@epiktetos' ),
				'enable_json_ld'          => array( 'label' => __( 'Enable JSON-LD', 'epiktetos' ), 'section' => 'epiktetos_seo_technical', 'type' => 'checkbox' ),
				'enable_open_graph'       => array( 'label' => __( 'Enable Open Graph', 'epiktetos' ), 'section' => 'epiktetos_seo_technical', 'type' => 'checkbox' ),
				'enable_canonicals'       => array( 'label' => __( 'Enable Canonicals', 'epiktetos' ), 'section' => 'epiktetos_seo_technical', 'type' => 'checkbox' ),
			);

			foreach ( $fields as $key => $field ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					$field['section'],
					array_merge( array( 'key' => $key ), $field )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-seo-' . $key;

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

			if ( 'textarea' === $args['type'] ) {
				printf(
					'<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				return;
			}

			printf(
				'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" class="regular-text" />',
				esc_attr( 'url' === $args['type'] ? 'url' : 'text' ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
			);
		}

		public static function sanitize( $input ) {
			$defs = self::defaults();
			$handle = isset( $input['twitter_handle'] ) ? trim( (string) $input['twitter_handle'] ) : '';
			$handle = $handle ? '@' . ltrim( sanitize_text_field( $handle ), '@' ) : '';

			return array(
				'publication_name'        => isset( $input['publication_name'] ) && '' !== trim( (string) $input['publication_name'] ) ? sanitize_text_field( $input['publication_name'] ) : $defs['publication_name'],
				'publication_description' => isset( $input['publication_description'] ) && '' !== trim( (string) $input['publication_description'] ) ? sanitize_textarea_field( $input['publication_description'] ) : $defs['publication_description'],
				'default_og_image'        => isset( $input['default_og_image'] ) ? esc_url_raw( trim( (string) $input['default_og_image'] ) ) : '',
				'twitter_handle'          => $handle,
				'enable_json_ld'          => ! empty( $input['enable_json_ld'] ) ? 1 : 0,
				'enable_open_graph'       => ! empty( $input['enable_open_graph'] ) ? 1 : 0,
				'enable_canonicals'       => ! empty( $input['enable_canonicals'] ) ? 1 : 0,
			);
		}

		public static function document_title( $title ) {
			if ( is_admin() ) {
				return $title;
			}

			return self::title();
		}

		public static function robots( $robots ) {
			if ( is_search() || is_404() ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
			}
			return $robots;
		}

		public static function print_meta() {
			if ( is_admin() ) {
				return;
			}

			$context = self::context();
			$title   = self::title();
			$desc    = self::description();
			$url     = self::canonical_url();
			$image   = self::image_url();
			$type    = is_singular( 'post' ) ? 'article' : 'website';

			echo "\n" . '<!-- Epiktetos SEO -->' . "\n";
			if ( $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
			if ( $url && (int) self::get( 'enable_canonicals' ) ) {
				echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			}

			if ( (int) self::get( 'enable_open_graph' ) ) {
				echo '<meta property="og:site_name" content="' . esc_attr( self::publication_name() ) . '">' . "\n";
				echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
				echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
				echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
				echo '<meta property="og:url" content="' . esc_url( $url ? $url : home_url( '/' ) ) . '">' . "\n";
				if ( $image ) {
					echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
				}
				echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
				echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
				echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
				if ( $image ) {
					echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
				}
				$handle = self::get( 'twitter_handle' );
				if ( $handle ) {
					echo '<meta name="twitter:site" content="' . esc_attr( $handle ) . '">' . "\n";
				}
			}

			if ( (int) self::get( 'enable_json_ld' ) ) {
				$graph = self::json_ld_graph( $context, $title, $desc, $url, $image );
				if ( ! empty( $graph ) ) {
					echo '<script type="application/ld+json">' . wp_json_encode(
						array(
							'@context' => 'https://schema.org',
							'@graph'   => $graph,
						),
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					) . '</script>' . "\n";
				}
			}
			echo '<!-- /Epiktetos SEO -->' . "\n";
		}

		protected static function context() {
			if ( is_front_page() ) {
				return 'home';
			}
			if ( is_singular( 'post' ) ) {
				return 'single';
			}
			if ( is_category() ) {
				return 'category';
			}
			if ( is_tag() ) {
				return 'tag';
			}
			if ( is_author() ) {
				return 'author';
			}
			if ( is_page() && self::is_topics_page() ) {
				return 'topics';
			}
			if ( is_search() ) {
				return 'search';
			}
			if ( is_archive() ) {
				return 'archive';
			}
			if ( is_page() ) {
				return 'page';
			}
			return 'site';
		}

		protected static function title() {
			$name = self::publication_name();
			$sep  = ' — ';
			if ( is_front_page() ) {
				return sprintf( __( '%s — Philosophy, Psychology, History & Technology', 'epiktetos' ), $name );
			}
			if ( is_home() ) {
				return __( 'Articles', 'epiktetos' ) . $sep . $name;
			}
			if ( is_singular() ) {
				return get_the_title() . $sep . $name;
			}
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term instanceof WP_Term ) {
					return $term->name . $sep . $name;
				}
			}
			if ( is_author() ) {
				return get_the_author_meta( 'display_name', (int) get_queried_object_id() ) . $sep . $name;
			}
			if ( is_search() ) {
				return sprintf( __( 'Search Results for %s — %s', 'epiktetos' ), get_search_query( false ), $name );
			}
			if ( is_day() ) {
				return self::date_archive_title( 'day' ) . $sep . $name;
			}
			if ( is_month() ) {
				return self::date_archive_title( 'month' ) . $sep . $name;
			}
			if ( is_year() ) {
				return self::date_archive_title( 'year' ) . $sep . $name;
			}
			return get_bloginfo( 'name', 'display' ) . $sep . $name;
		}

		protected static function description() {
			$context = self::context();
			$text = '';

			if ( 'single' === $context || 'page' === $context || 'topics' === $context ) {
				$post = get_post();
				if ( $post instanceof WP_Post ) {
					$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : self::excerpt_from_content( $post->post_content );
				}
			} elseif ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term instanceof WP_Term ) {
					$text = trim( wp_strip_all_tags( term_description( $term ) ) );
					if ( ! $text ) {
						$text = sprintf( __( 'Articles connected to %s.', 'epiktetos' ), $term->name );
					}
				}
			} elseif ( is_author() ) {
				$text = get_the_author_meta( 'description', (int) get_queried_object_id() );
			} elseif ( is_search() ) {
				$text = sprintf( __( 'Search the Epiktetos archive for essays and notes connected to %s.', 'epiktetos' ), get_search_query( false ) );
			} elseif ( is_archive() ) {
				$text = __( 'Browse essays from the Epiktetos archive.', 'epiktetos' );
			}

			if ( ! $text ) {
				$text = self::get( 'publication_description' );
			}

			return self::trim_description( $text );
		}

		protected static function canonical_url() {
			if ( is_front_page() ) {
				return home_url( '/' );
			}
			if ( is_singular() ) {
				return get_permalink();
			}
			if ( is_category() ) {
				return get_category_link( get_queried_object_id() );
			}
			if ( is_tag() ) {
				return get_tag_link( get_queried_object_id() );
			}
			if ( is_author() ) {
				return get_author_posts_url( get_queried_object_id() );
			}
			if ( is_search() ) {
				return home_url( '/?s=' . rawurlencode( get_search_query( false ) ) );
			}
			if ( is_archive() ) {
				return get_pagenum_link( max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ) );
			}
			return home_url( add_query_arg( null, null ) );
		}

		protected static function image_url() {
			if ( is_singular() ) {
				$post = get_post();
				if ( $post instanceof WP_Post && has_post_thumbnail( $post ) ) {
					$image = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
					if ( $image ) {
						return $image;
					}
				}
			}

			$default = self::get( 'default_og_image' );
			if ( $default ) {
				return $default;
			}

			if ( class_exists( 'Epiktetos_Branding' ) && method_exists( 'Epiktetos_Branding', 'default_og_image_url' ) ) {
				$branding_image = Epiktetos_Branding::default_og_image_url();
				if ( $branding_image ) {
					return $branding_image;
				}
			}

			$icon = get_site_icon_url( 512 );
			return $icon ? $icon : '';
		}

		protected static function json_ld_graph( $context, $title, $desc, $url, $image ) {
			$graph = array();
			$org_id = home_url( '/#organization' );
			$site_id = home_url( '/#website' );

			if ( 'home' === $context ) {
				$org = array(
					'@type'       => 'Organization',
					'@id'         => $org_id,
					'name'        => self::publication_name(),
					'url'         => home_url( '/' ),
					'description' => self::get( 'publication_description' ),
				);
				if ( $image ) {
					$org['image'] = $image;
				}
				$graph[] = $org;
				$graph[] = array(
					'@type'           => 'WebSite',
					'@id'             => $site_id,
					'name'            => self::publication_name(),
					'url'             => home_url( '/' ),
					'description'     => self::get( 'publication_description' ),
					'publisher'       => array( '@id' => $org_id ),
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => home_url( '/?s={search_term_string}' ),
						'query-input' => 'required name=search_term_string',
					),
				);
			}

			if ( 'single' === $context ) {
				$post = get_post();
				if ( $post instanceof WP_Post ) {
					$author_id = (int) $post->post_author;
					$article = array(
						'@type'            => 'Article',
						'@id'              => get_permalink( $post ) . '#article',
						'headline'         => get_the_title( $post ),
						'description'      => $desc,
						'mainEntityOfPage' => get_permalink( $post ),
						'author'           => array(
							'@type' => 'Person',
							'name'  => get_the_author_meta( 'display_name', $author_id ),
							'url'   => get_author_posts_url( $author_id ),
						),
						'publisher'        => array( '@id' => $org_id ),
						'datePublished'    => get_the_date( 'c', $post ),
						'dateModified'     => get_the_modified_date( 'c', $post ),
					);
					if ( $image ) {
						$article['image'] = array( $image );
					}
					$graph[] = array(
						'@type' => 'Organization',
						'@id'   => $org_id,
						'name'  => self::publication_name(),
						'url'   => home_url( '/' ),
					);
					$graph[] = $article;
				}
			}

			if ( in_array( $context, array( 'category', 'tag', 'topics', 'archive' ), true ) ) {
				$graph[] = array(
					'@type'       => 'CollectionPage',
					'@id'         => $url ? $url . '#collection' : home_url( '/#collection' ),
					'name'        => $title,
					'description' => $desc,
					'url'         => $url,
					'isPartOf'    => array( '@id' => $site_id ),
				);
			}

			if ( 'author' === $context ) {
				$author_id = (int) get_queried_object_id();
				$graph[] = array(
					'@type'       => 'Person',
					'@id'         => get_author_posts_url( $author_id ) . '#person',
					'name'        => get_the_author_meta( 'display_name', $author_id ),
					'description' => $desc,
					'url'         => get_author_posts_url( $author_id ),
				);
			}

			$breadcrumb = self::breadcrumb_schema( $context, self::breadcrumb_label(), $url );
			if ( $breadcrumb ) {
				$graph[] = $breadcrumb;
			}

			return $graph;
		}

		protected static function breadcrumb_schema( $context, $title, $url ) {
			$items = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => self::publication_name(),
					'item'     => home_url( '/' ),
				),
			);

			if ( 'home' === $context ) {
				return null;
			}

			if ( 'single' === $context ) {
				$cat = self::primary_category( get_post() );
				if ( $cat ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => count( $items ) + 1,
						'name'     => $cat->name,
						'item'     => get_category_link( $cat->term_id ),
					);
				}
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => wp_strip_all_tags( $title ),
				'item'     => $url ? $url : home_url( '/' ),
			);

			return array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}

		public static function maybe_refresh_current_post_intelligence() {
			if ( is_singular( 'post' ) ) {
				$post = get_post();
				if ( $post instanceof WP_Post ) {
					self::store_post_intelligence_if_stale( $post );
				}
			}
		}

		public static function store_post_intelligence( $post_id, $post, $update ) {
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof WP_Post ) {
				return;
			}
			self::store_post_intelligence_if_stale( $post );
		}

		protected static function store_post_intelligence_if_stale( $post ) {
			$hash = md5( $post->post_content . '|' . $post->post_modified_gmt );
			if ( get_post_meta( $post->ID, '_epiktetos_intelligence_hash', true ) === $hash ) {
				return;
			}

			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$words = str_word_count( $content );
			$sentences = preg_match_all( '/[.!?]+/', $content );
			$reading_time = class_exists( 'Epiktetos_Single' )
				? Epiktetos_Single::reading_time( $post->post_content )
				: max( 1, (int) ceil( $words / 200 ) );
			$heading_count = preg_match_all( '/<h[2-4]\b/i', $post->post_content );
			$avg_sentence = $sentences > 0 ? $words / $sentences : $words;
			$reading_level = $avg_sentence <= 14 ? 'clear' : ( $avg_sentence <= 22 ? 'moderate' : 'dense' );

			update_post_meta( $post->ID, '_epiktetos_word_count', (int) $words );
			update_post_meta( $post->ID, '_epiktetos_reading_time', (int) $reading_time );
			update_post_meta( $post->ID, '_epiktetos_heading_count', (int) $heading_count );
			update_post_meta( $post->ID, '_epiktetos_reading_level', $reading_level );
			update_post_meta( $post->ID, '_epiktetos_intelligence_hash', $hash );
		}

		protected static function primary_category( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return null;
			}
			$cats = get_the_category( $post->ID );
			if ( empty( $cats ) ) {
				return null;
			}
			return $cats[0];
		}

		protected static function publication_name() {
			$name = self::get( 'publication_name' );
			return $name ? $name : get_bloginfo( 'name', 'display' );
		}

		protected static function is_topics_page() {
			$post = get_post();
			return $post instanceof WP_Post && 'topics' === $post->post_name;
		}

		protected static function excerpt_from_content( $content ) {
			return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 32, '' );
		}

		protected static function breadcrumb_label() {
			if ( is_singular() ) {
				return get_the_title();
			}
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				return $term instanceof WP_Term ? $term->name : self::title();
			}
			if ( is_author() ) {
				return get_the_author_meta( 'display_name', (int) get_queried_object_id() );
			}
			if ( is_search() ) {
				return __( 'Search Results', 'epiktetos' );
			}
			if ( is_day() ) {
				return self::date_archive_title( 'day' );
			}
			if ( is_month() ) {
				return self::date_archive_title( 'month' );
			}
			if ( is_year() ) {
				return self::date_archive_title( 'year' );
			}
			return self::title();
		}

		protected static function date_archive_title( $type ) {
			$year  = (int) get_query_var( 'year' );
			$month = (int) get_query_var( 'monthnum' );
			$day   = (int) get_query_var( 'day' );
			$m     = preg_replace( '/[^0-9]/', '', (string) get_query_var( 'm' ) );
			if ( $m && ! $year && strlen( $m ) >= 4 ) {
				$year = (int) substr( $m, 0, 4 );
				if ( strlen( $m ) >= 6 ) {
					$month = (int) substr( $m, 4, 2 );
				}
				if ( strlen( $m ) >= 8 ) {
					$day = (int) substr( $m, 6, 2 );
				}
			}
			if ( 'year' === $type && $year ) {
				return (string) $year;
			}
			if ( 'month' === $type && $year && $month ) {
				return date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
			}
			if ( 'day' === $type && $year && $month && $day ) {
				return date_i18n( get_option( 'date_format' ), mktime( 0, 0, 0, $month, $day, $year ) );
			}
			return __( 'Archive', 'epiktetos' );
		}

		protected static function trim_description( $text ) {
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) <= 160 ) {
				return $text;
			}
			if ( function_exists( 'mb_substr' ) ) {
				return rtrim( mb_substr( $text, 0, 157 ), " \t\n\r\0\x0B.,;:" ) . '...';
			}
			if ( strlen( $text ) <= 160 ) {
				return $text;
			}
			return rtrim( substr( $text, 0, 157 ), " \t\n\r\0\x0B.,;:" ) . '...';
		}
	}

	Epiktetos_SEO::init();
}
