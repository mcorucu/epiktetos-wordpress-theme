<?php
/**
 * Epiktetos — admin suite (System, Tools, Sample Content, Theme health).
 *
 * Adds the management pages that make the theme feel like a finished product:
 * a system-health panel, maintenance tools, an idempotent sample importer, and
 * a theme validator. All destructive actions are nonce-protected, capability-
 * gated, and use the POST→redirect→GET pattern with admin notices.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Admin' ) ) {

	class Epiktetos_Admin {

		const CAP               = 'edit_theme_options';
		const DEMO_META         = '_epiktetos_demo';
		const SAMPLE_META       = '_epiktetos_sample_content';
		const SAMPLE_MEDIA_META = '_epiktetos_sample_media_key';
		const NONCE             = 'epiktetos_admin';
		const SLUG              = 'epiktetos';

		/** Theme option groups eligible for export/import. */
		public static function option_keys() {
			return array(
				'epiktetos_options',
				'epiktetos_branding_options',
				'epiktetos_footer_options',
				'epiktetos_single_options',
				'epiktetos_discussion_options',
				'epiktetos_taxonomy_options',
				'epiktetos_reader_options',
				'epiktetos_seo_options',
				'epiktetos_about_options',
				'epiktetos_category_order',
			);
		}

		/** Known theme transients (caches). */
		public static function cache_keys() {
			return array(
				'epiktetos_cat_showcase'      => __( 'Showcase cache', 'epiktetos' ),
				'epiktetos_editor_picks'      => __( 'Editorial cache', 'epiktetos' ),
				'epiktetos_publication_stats' => __( 'Publication stats', 'epiktetos' ),
			);
		}

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_pages' ), 20 );
			add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
			add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_appearance_pages' ), 1 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		}

		/* ============================================================
		   Menu
		   ============================================================ */

		public static function register_pages() {
			// One native entry: Appearance → Epiktetos. The unified, tabbed
			// settings screen is the single hub for the whole theme.
			$hook = add_theme_page(
				__( 'Epiktetos', 'epiktetos' ),
				__( 'Epiktetos', 'epiktetos' ),
				self::CAP,
				'epiktetos-settings',
				array( 'Epiktetos_Settings', 'render_page' )
			);
			if ( $hook ) {
				$GLOBALS['epiktetos_hook_epiktetos-settings'] = $hook;
			}

			// Auxiliary experiences are folded into the Settings tabs, but stay
			// reachable by URL (and for the setup wizard). They are registered
			// without a visible menu item so the admin stays native and tidy.
			$hidden = array(
				'epiktetos-wizard'    => array( __( 'Epiktetos Setup', 'epiktetos' ), array( 'Epiktetos_Wizard', 'render' ) ),
				'epiktetos-demo'      => array( __( 'Sample Content', 'epiktetos' ), array( __CLASS__, 'render_demo' ) ),
				'epiktetos-system'    => array( __( 'System', 'epiktetos' ), array( __CLASS__, 'render_system' ) ),
				'epiktetos-tools'     => array( __( 'Tools', 'epiktetos' ), array( __CLASS__, 'render_tools' ) ),
				'epiktetos-validator' => array( __( 'Theme health', 'epiktetos' ), array( __CLASS__, 'render_validator' ) ),
			);
			foreach ( $hidden as $slug => $cfg ) {
				$h = add_submenu_page( null, $cfg[0], $cfg[0], self::CAP, $slug, $cfg[1] );
				if ( $h ) {
					$GLOBALS[ 'epiktetos_hook_' . $slug ] = $h;
				}
			}
		}

		/**
		 * Kept for backwards compatibility with bookmarked admin.php URLs.
		 * The admin now lives under Appearance, so no redirect is performed.
		 */
		public static function redirect_legacy_appearance_pages() {}

		/* ============================================================
		   Assets
		   ============================================================ */

		public static function assets( $hook ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( ! in_array( $page, self::admin_page_slugs(), true ) && false === strpos( (string) $hook, 'epiktetos' ) ) {
				return;
			}
			$ver         = function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/css/admin.css' ) : null;
			$needs_media = self::admin_page_needs_media( $page );
			if ( $needs_media ) {
				wp_enqueue_media();
			}
			wp_enqueue_style( 'epiktetos-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), $ver );
			wp_enqueue_script(
				'epiktetos-admin',
				get_template_directory_uri() . '/assets/js/admin.js',
				$needs_media ? array( 'media-editor' ) : array(),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/js/admin.js' ) : null,
				true
			);
		}

		protected static function admin_page_slugs() {
			return array(
				self::SLUG,
				'epiktetos-settings',
				'epiktetos-wizard',
				'epiktetos-demo',
				'epiktetos-system',
				'epiktetos-tools',
				'epiktetos-validator',
			);
		}

		protected static function admin_page_needs_media( $page ) {
			if ( 'epiktetos-settings' === $page ) {
				return true;
			}
			if ( 'epiktetos-wizard' !== $page ) {
				return false;
			}
			$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'welcome';
			return in_array( $step, array( 'branding', 'logo', 'favicon' ), true );
		}

		/* ============================================================
		   Action router (POST → redirect → GET)
		   ============================================================ */

		public static function handle_actions() {
			if ( empty( $_POST['epiktetos_action'] ) || ! current_user_can( self::CAP ) ) {
				return;
			}
			check_admin_referer( self::NONCE );
			$action = sanitize_key( wp_unslash( $_POST['epiktetos_action'] ) );
			$dry    = ! empty( $_POST['dry_run'] );
			$notice = '';

			switch ( $action ) {
				case 'demo_import':
					$r = self::demo_import( $dry );
					if ( ! empty( $r['full'] ) ) {
						$notice = $dry ? 'dryfull' : 'full';
					} else {
						$notice = ( $dry ? 'dryimport' : 'import' ) . ':' . $r['created'] . '-' . $r['updated'];
					}
					break;
				case 'demo_reset':
					$r = self::demo_reset( $dry );
					$notice = ( $dry ? 'dryreset' : 'reset' ) . ':' . $r['deleted'];
					break;
				case 'demo_rebuild':
					self::flush_all_caches();
					$notice = 'rebuilt';
					break;
				case 'clear_cache_all':
					self::flush_all_caches();
					$notice = 'cache';
					break;
				case 'clear_cache_theme':
					delete_transient( 'epiktetos_cat_showcase' );
					$notice = 'cache';
					break;
				case 'clear_cache_editorial':
					if ( class_exists( 'Epiktetos_Reader' ) ) {
						Epiktetos_Reader::clear_editor_picks_cache();
					}
					$notice = 'cache';
					break;
				case 'recalc_reading_time':
					// Reading time is computed on render; clearing derived caches
					// is the effective "recalculate".
					self::flush_all_caches();
					$notice = 'recalc';
					break;
				case 'regen_thumbs':
					$n = self::regenerate_thumbnails();
					$notice = 'thumbs:' . $n;
					break;
				case 'export_settings':
					self::export_settings(); // exits.
					break;
				case 'import_settings':
					$ok = self::import_settings();
					$notice = $ok ? 'imported' : 'importfail';
					break;
			}

			$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=epiktetos-tools' );
			wp_safe_redirect( add_query_arg( 'epi_notice', rawurlencode( $notice ), $redirect ) );
			exit;
		}

		public static function notice() {
			if ( empty( $_GET['epi_notice'] ) ) {
				return '';
			}
			$raw = sanitize_text_field( wp_unslash( $_GET['epi_notice'] ) );
			list( $key, $detail ) = array_pad( explode( ':', $raw, 2 ), 2, '' );
			$map = array(
				'import'    => __( 'Sample content created.', 'epiktetos' ),
				'dryimport' => __( 'Preview — sample content changes.', 'epiktetos' ),
				'full'      => __( 'A full set of articles is already present, so no sample posts were added.', 'epiktetos' ),
				'dryfull'   => __( 'Preview — a full set of articles is already present; nothing would be added.', 'epiktetos' ),
				'reset'     => __( 'Sample content removed.', 'epiktetos' ),
				'dryreset'  => __( 'Preview — sample content removal.', 'epiktetos' ),
				'rebuilt'   => __( 'Caches rebuilt.', 'epiktetos' ),
				'cache'     => __( 'Cache cleared.', 'epiktetos' ),
				'recalc'    => __( 'Reading time recalculated (derived caches cleared).', 'epiktetos' ),
				'thumbs'    => __( 'Thumbnails regenerated.', 'epiktetos' ),
				'imported'  => __( 'Settings imported.', 'epiktetos' ),
				'importfail'=> __( 'Settings import failed — invalid file.', 'epiktetos' ),
			);
			$msg = isset( $map[ $key ] ) ? $map[ $key ] : '';
			if ( ! $msg ) {
				return '';
			}
			if ( $detail && in_array( $key, array( 'import', 'dryimport' ), true ) ) {
				$parts = explode( '-', $detail );
				$msg  .= ' ' . sprintf( __( '%1$s created, %2$s updated.', 'epiktetos' ), (int) $parts[0], isset( $parts[1] ) ? (int) $parts[1] : 0 );
			} elseif ( $detail && in_array( $key, array( 'reset', 'dryreset' ), true ) ) {
				$msg .= ' ' . sprintf( __( '%s items.', 'epiktetos' ), (int) $detail );
			} elseif ( $detail && 'thumbs' === $key ) {
				$msg .= ' ' . sprintf( __( '%s attachments.', 'epiktetos' ), (int) $detail );
			}
			$class = 'importfail' === $key ? 'notice-error' : 'notice-success';
			return '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		/* ============================================================
		   Caches
		   ============================================================ */

		public static function flush_all_caches() {
			foreach ( array_keys( self::cache_keys() ) as $k ) {
				delete_transient( $k );
			}
			if ( class_exists( 'Epiktetos_Categories' ) ) {
				Epiktetos_Categories::flush_cache();
			}
			if ( class_exists( 'Epiktetos_Reader' ) ) {
				Epiktetos_Reader::clear_editor_picks_cache();
			}
		}

		/* ============================================================
		   Sample content importer (idempotent)
		   ============================================================ */

		protected static function sample_content_dir() {
			return trailingslashit( get_template_directory() ) . 'inc/sample-content';
		}

		protected static function sample_json( $file, $fallback = array() ) {
			$path = self::sample_content_dir() . '/' . ltrim( $file, '/' );
			if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
				return $fallback;
			}
			$raw = file_get_contents( $path );
			if ( false === $raw ) {
				return $fallback;
			}
			$data = json_decode( $raw, true );
			return is_array( $data ) ? $data : $fallback;
		}

		protected static function sample_bundle() {
			return array(
				'manifest'      => self::sample_json( 'manifest.json' ),
				'posts'         => self::sample_json( 'posts.json' ),
				'pages'         => self::sample_json( 'pages.json' ),
				'taxonomies'    => self::sample_json( 'taxonomies.json', array( 'categories' => array(), 'tags' => array() ) ),
				'menus'         => self::sample_json( 'menus.json', array( 'locations' => array(), 'menus' => array() ) ),
				'theme_options' => self::sample_json( 'theme-options.json', array( 'options' => array(), 'refs' => array(), 'reading' => array() ) ),
				'comments'      => self::sample_json( 'comments.json' ),
				'media'         => self::sample_json( 'media.json' ),
			);
		}

		protected static function sample_manifest_counts() {
			$manifest = self::sample_json( 'manifest.json' );
			return isset( $manifest['counts'] ) && is_array( $manifest['counts'] ) ? $manifest['counts'] : array();
		}

		public static function full_demo_present() {
			$posts = self::sample_json( 'posts.json' );
			if ( empty( $posts ) ) {
				return false;
			}
			foreach ( $posts as $post ) {
				$slug = isset( $post['slug'] ) ? sanitize_title( $post['slug'] ) : '';
				if ( ! $slug || ! get_page_by_path( $slug, OBJECT, 'post' ) ) {
					return false;
				}
			}
			return true;
		}

		public static function demo_import( $dry = false ) {
			$bundle  = self::sample_bundle();
			$preview = self::sample_preview_counts( $bundle );
			if ( $dry ) {
				return array( 'created' => $preview['created'], 'updated' => $preview['updated'], 'full' => false );
			}

			$media_ids = self::import_sample_media( $bundle['media'] );
			$term_ids  = self::import_sample_terms( $bundle['taxonomies'] );
			$post_ids  = array();
			$page_ids  = array();
			$created   = 0;
			$updated   = 0;

			foreach ( $bundle['pages'] as $page ) {
				$result = self::upsert_sample_post( $page, 'page', $media_ids, $term_ids );
				if ( $result['id'] ) {
					$page_ids[ $result['slug'] ] = $result['id'];
				}
				$created += $result['created'];
				$updated += $result['updated'];
			}

			foreach ( $bundle['posts'] as $post ) {
				$result = self::upsert_sample_post( $post, 'post', $media_ids, $term_ids );
				if ( $result['id'] ) {
					$post_ids[ $result['slug'] ] = $result['id'];
				}
				$created += $result['created'];
				$updated += $result['updated'];
			}

			self::import_sample_comments( $bundle['comments'], $post_ids );
			self::import_sample_menus( $bundle['menus'], $post_ids, $page_ids, $term_ids );
			self::apply_sample_options( $bundle['theme_options'], $post_ids, $page_ids, $media_ids, $term_ids );
			self::flush_all_caches();
			flush_rewrite_rules();

			return array( 'created' => $created, 'updated' => $updated, 'full' => false );
		}

		protected static function sample_preview_counts( $bundle ) {
			$created = 0;
			$updated = 0;
			foreach ( array( 'posts' => 'post', 'pages' => 'page' ) as $key => $type ) {
				foreach ( $bundle[ $key ] as $item ) {
					$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
					if ( ! $slug ) {
						continue;
					}
					if ( get_page_by_path( $slug, OBJECT, $type ) ) {
						$updated++;
					} else {
						$created++;
					}
				}
			}
			foreach ( $bundle['media'] as $media ) {
				$key = isset( $media['key'] ) ? sanitize_file_name( $media['key'] ) : '';
				if ( $key && ! self::find_sample_attachment( $key ) ) {
					$created++;
				}
			}
			return array( 'created' => $created, 'updated' => $updated );
		}

		protected static function import_sample_media( $media_items ) {
			$ids = array();
			if ( empty( $media_items ) ) {
				return $ids;
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			foreach ( $media_items as $media ) {
				$key  = isset( $media['key'] ) ? sanitize_file_name( $media['key'] ) : '';
				$file = isset( $media['file'] ) ? sanitize_file_name( $media['file'] ) : $key;
				if ( ! $key || ! $file ) {
					continue;
				}
				$existing = self::find_sample_attachment( $key );
				if ( $existing ) {
					$ids[ $key ] = $existing;
					continue;
				}
				$source = self::sample_content_dir() . '/media/' . $file;
				if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
					continue;
				}
				$bits = wp_upload_bits( $file, null, file_get_contents( $source ) );
				if ( ! empty( $bits['error'] ) || empty( $bits['file'] ) ) {
					continue;
				}
				$type = wp_check_filetype( $bits['file'] );
				$id   = wp_insert_attachment(
					array(
						'post_title'     => isset( $media['title'] ) ? sanitize_text_field( $media['title'] ) : preg_replace( '/\.[^.]+$/', '', $file ),
						'post_excerpt'   => isset( $media['caption'] ) ? wp_kses_post( $media['caption'] ) : '',
						'post_content'   => isset( $media['description'] ) ? wp_kses_post( $media['description'] ) : '',
						'post_mime_type' => $type['type'],
						'post_status'    => 'inherit',
					),
					$bits['file']
				);
				if ( $id && ! is_wp_error( $id ) ) {
					wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
					update_post_meta( $id, self::SAMPLE_META, 1 );
					update_post_meta( $id, self::SAMPLE_MEDIA_META, $key );
					if ( isset( $media['alt'] ) ) {
						update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $media['alt'] ) );
					}
					$ids[ $key ] = $id;
				}
			}
			return $ids;
		}

		protected static function find_sample_attachment( $key ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::SAMPLE_MEDIA_META,
					'meta_value'     => $key,
				)
			);
			return $ids ? (int) $ids[0] : 0;
		}

		protected static function import_sample_terms( $taxonomies ) {
			$ids = array( 'category' => array(), 'post_tag' => array() );
			$map = array( 'categories' => 'category', 'tags' => 'post_tag' );
			foreach ( $map as $key => $taxonomy ) {
				foreach ( isset( $taxonomies[ $key ] ) ? (array) $taxonomies[ $key ] : array() as $term ) {
					$slug = isset( $term['slug'] ) ? sanitize_title( $term['slug'] ) : '';
					$name = isset( $term['name'] ) ? sanitize_text_field( $term['name'] ) : $slug;
					if ( ! $slug || ! $name ) {
						continue;
					}
					$existing = get_term_by( 'slug', $slug, $taxonomy );
					if ( $existing ) {
						$term_id = (int) $existing->term_id;
						wp_update_term( $term_id, $taxonomy, array( 'name' => $name, 'description' => isset( $term['description'] ) ? wp_kses_post( $term['description'] ) : '' ) );
					} else {
						$result  = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'description' => isset( $term['description'] ) ? wp_kses_post( $term['description'] ) : '' ) );
						$term_id = is_wp_error( $result ) ? 0 : (int) $result['term_id'];
					}
					if ( $term_id ) {
						update_term_meta( $term_id, self::SAMPLE_META, 1 );
						$ids[ $taxonomy ][ $slug ] = $term_id;
					}
				}
			}
			return $ids;
		}

		protected static function upsert_sample_post( $item, $type, $media_ids, $term_ids ) {
			$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
			if ( ! $slug ) {
				return array( 'id' => 0, 'slug' => '', 'created' => 0, 'updated' => 0 );
			}
			$existing = get_page_by_path( $slug, OBJECT, $type );
			$args     = array(
				'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : $slug,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => $type,
				'post_excerpt' => isset( $item['excerpt'] ) ? wp_kses_post( $item['excerpt'] ) : '',
				'post_content' => isset( $item['content'] ) ? (string) $item['content'] : '',
				'post_author'  => self::sample_author_id(),
				'menu_order'   => isset( $item['menu_order'] ) ? (int) $item['menu_order'] : 0,
			);
			if ( ! empty( $item['date'] ) ) {
				$args['post_date']     = sanitize_text_field( $item['date'] );
				$args['post_date_gmt'] = get_gmt_from_date( $args['post_date'] );
			}
			if ( $existing ) {
				$args['ID'] = $existing->ID;
				$id         = wp_update_post( $args );
				$created    = 0;
				$updated    = 1;
			} else {
				$id      = wp_insert_post( $args );
				$created = 1;
				$updated = 0;
			}
			if ( ! $id || is_wp_error( $id ) ) {
				return array( 'id' => 0, 'slug' => $slug, 'created' => 0, 'updated' => 0 );
			}
			update_post_meta( $id, self::SAMPLE_META, 1 );
			update_post_meta( $id, self::DEMO_META, 1 );
			if ( ! empty( $item['featured_media'] ) && isset( $media_ids[ $item['featured_media'] ] ) ) {
				set_post_thumbnail( $id, (int) $media_ids[ $item['featured_media'] ] );
			}
			if ( 'page' === $type && isset( $item['template'] ) && $item['template'] ) {
				update_post_meta( $id, '_wp_page_template', sanitize_text_field( $item['template'] ) );
			}
			if ( 'post' === $type ) {
				$cats = array();
				foreach ( isset( $item['categories'] ) ? (array) $item['categories'] : array() as $slug ) {
					$slug = sanitize_title( $slug );
					if ( isset( $term_ids['category'][ $slug ] ) ) {
						$cats[] = (int) $term_ids['category'][ $slug ];
					}
				}
				if ( $cats ) {
					wp_set_post_categories( $id, $cats );
				}
				$tags = array();
				foreach ( isset( $item['tags'] ) ? (array) $item['tags'] : array() as $slug ) {
					$slug = sanitize_title( $slug );
					if ( isset( $term_ids['post_tag'][ $slug ] ) ) {
						$tags[] = (int) $term_ids['post_tag'][ $slug ];
					}
				}
				wp_set_post_terms( $id, $tags, 'post_tag' );
			}
			return array( 'id' => (int) $id, 'slug' => $slug, 'created' => $created, 'updated' => $updated );
		}

		protected static function sample_author_id() {
			$id = get_current_user_id();
			if ( $id ) {
				return $id;
			}
			$users = get_users( array( 'role__in' => array( 'administrator', 'editor' ), 'number' => 1, 'fields' => 'ID' ) );
			return $users ? (int) $users[0] : 1;
		}

		protected static function import_sample_comments( $comments, $post_ids ) {
			foreach ( (array) $comments as $comment ) {
				$slug = isset( $comment['post_slug'] ) ? sanitize_title( $comment['post_slug'] ) : '';
				$post_id = isset( $post_ids[ $slug ] ) ? (int) $post_ids[ $slug ] : self::sample_post_id_by_slug( $slug, 'post' );
				if ( ! $slug || ! $post_id ) {
					continue;
				}
				$content = isset( $comment['content'] ) ? wp_kses_post( $comment['content'] ) : '';
				$author  = isset( $comment['author'] ) ? sanitize_text_field( $comment['author'] ) : '';
				if ( ! $content || ! $author ) {
					continue;
				}
				$existing = get_comments( array( 'post_id' => $post_id, 'author_email' => isset( $comment['author_email'] ) ? sanitize_email( $comment['author_email'] ) : '', 'meta_key' => self::SAMPLE_META, 'meta_value' => 1, 'number' => 1 ) );
				if ( $existing ) {
					continue;
				}
				$id = wp_insert_comment(
					array(
						'comment_post_ID'      => $post_id,
						'comment_author'       => $author,
						'comment_author_email' => isset( $comment['author_email'] ) ? sanitize_email( $comment['author_email'] ) : '',
						'comment_author_url'   => isset( $comment['author_url'] ) ? esc_url_raw( $comment['author_url'] ) : '',
						'comment_content'      => $content,
						'comment_date'         => isset( $comment['date'] ) ? sanitize_text_field( $comment['date'] ) : current_time( 'mysql' ),
						'comment_approved'     => 1,
					)
				);
				if ( $id ) {
					add_comment_meta( $id, self::SAMPLE_META, 1, true );
				}
			}
		}

		protected static function sample_post_id_by_slug( $slug, $type ) {
			$post = $slug ? get_page_by_path( sanitize_title( $slug ), OBJECT, $type ) : null;
			return $post ? (int) $post->ID : 0;
		}

		protected static function import_sample_menus( $menus, $post_ids, $page_ids, $term_ids ) {
			$menu_ids = array();
			foreach ( isset( $menus['menus'] ) ? (array) $menus['menus'] : array() as $menu ) {
				$slug = isset( $menu['slug'] ) ? sanitize_title( $menu['slug'] ) : '';
				$name = isset( $menu['name'] ) ? sanitize_text_field( $menu['name'] ) : $slug;
				if ( ! $slug || ! $name ) {
					continue;
				}
				$term = wp_get_nav_menu_object( $slug );
				if ( ! $term ) {
					$menu_id = wp_create_nav_menu( $name );
					if ( is_wp_error( $menu_id ) ) {
						continue;
					}
				} else {
					$menu_id = (int) $term->term_id;
					foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $item ) {
						wp_delete_post( $item->ID, true );
					}
				}
				update_term_meta( $menu_id, self::SAMPLE_META, 1 );
				$menu_ids[ $slug ] = $menu_id;
				foreach ( isset( $menu['items'] ) ? (array) $menu['items'] : array() as $item ) {
					$args = array( 'menu-item-title' => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '', 'menu-item-status' => 'publish' );
					if ( 'post_type' === $item['type'] && 'page' === $item['object'] && ! empty( $page_ids[ $item['object_slug'] ] ) ) {
						$args['menu-item-type']      = 'post_type';
						$args['menu-item-object']    = 'page';
						$args['menu-item-object-id'] = (int) $page_ids[ $item['object_slug'] ];
					} elseif ( 'taxonomy' === $item['type'] && isset( $term_ids[ $item['object'] ][ $item['object_slug'] ] ) ) {
						$args['menu-item-type']      = 'taxonomy';
						$args['menu-item-object']    = sanitize_key( $item['object'] );
						$args['menu-item-object-id'] = (int) $term_ids[ $item['object'] ][ $item['object_slug'] ];
					} else {
						$args['menu-item-type'] = 'custom';
						$args['menu-item-url']  = self::sample_menu_url( isset( $item['url'] ) ? $item['url'] : '' );
					}
					$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
					if ( $item_id && ! is_wp_error( $item_id ) ) {
						update_post_meta( $item_id, self::SAMPLE_META, 1 );
					}
				}
			}
			$locations = get_theme_mod( 'nav_menu_locations', array() );
			foreach ( isset( $menus['locations'] ) ? (array) $menus['locations'] : array() as $location => $slug ) {
				$location = sanitize_key( $location );
				$slug     = sanitize_title( $slug );
				if ( isset( $menu_ids[ $slug ] ) ) {
					$locations[ $location ] = $menu_ids[ $slug ];
				}
			}
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		protected static function sample_menu_url( $url ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			return '/' === $path || '' === $path ? home_url( '/' ) : home_url( trailingslashit( ltrim( (string) $path, '/' ) ) );
		}

		protected static function apply_sample_options( $data, $post_ids, $page_ids, $media_ids, $term_ids ) {
			$options = isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : array();
			$refs    = isset( $data['refs'] ) && is_array( $data['refs'] ) ? $data['refs'] : array();
			foreach ( $options as $key => $value ) {
				if ( ! in_array( $key, self::option_keys(), true ) ) {
					continue;
				}
				if ( 'epiktetos_reader_options' === $key && is_array( $value ) && ! empty( $refs['reader_editor_picks'] ) ) {
					$value['editor_picks'] = array();
					foreach ( (array) $refs['reader_editor_picks'] as $slug ) {
						$post_id = isset( $post_ids[ $slug ] ) ? (int) $post_ids[ $slug ] : self::sample_post_id_by_slug( $slug, 'post' );
						if ( $post_id ) {
							$value['editor_picks'][] = $post_id;
						}
					}
				}
				if ( 'epiktetos_seo_options' === $key && is_array( $value ) && ! empty( $refs['seo_default_og_image'] ) && isset( $media_ids[ $refs['seo_default_og_image'] ] ) ) {
					$value['default_og_image'] = wp_get_attachment_url( (int) $media_ids[ $refs['seo_default_og_image'] ] );
				}
				if ( 'epiktetos_about_options' === $key && is_array( $value ) && ! empty( $refs['about_page_slug'] ) && isset( $page_ids[ $refs['about_page_slug'] ] ) ) {
					$value['about_page_id'] = (int) $page_ids[ $refs['about_page_slug'] ];
				}
				if ( 'epiktetos_category_order' === $key && ! empty( $refs['category_order'] ) ) {
					$value = array();
					foreach ( (array) $refs['category_order'] as $slug ) {
						if ( isset( $term_ids['category'][ $slug ] ) ) {
							$value[] = (int) $term_ids['category'][ $slug ];
						}
					}
				}
				update_option( $key, self::sanitize_imported_option( $key, $value ) );
			}
			$reading = isset( $data['reading'] ) && is_array( $data['reading'] ) ? $data['reading'] : array();
			if ( ! empty( $reading['show_on_front'] ) ) {
				update_option( 'show_on_front', sanitize_key( $reading['show_on_front'] ) );
			}
			if ( ! empty( $reading['page_on_front_slug'] ) && isset( $page_ids[ $reading['page_on_front_slug'] ] ) ) {
				update_option( 'page_on_front', (int) $page_ids[ $reading['page_on_front_slug'] ] );
			}
			if ( ! empty( $reading['page_for_posts_slug'] ) && isset( $page_ids[ $reading['page_for_posts_slug'] ] ) ) {
				update_option( 'page_for_posts', (int) $page_ids[ $reading['page_for_posts_slug'] ] );
			}
			if ( isset( $reading['permalink_structure'] ) ) {
				update_option( 'permalink_structure', sanitize_text_field( $reading['permalink_structure'] ) );
			}
		}

		public static function demo_reset( $dry = false ) {
			$post_ids = get_posts(
				array(
					'post_type'      => array( 'post', 'page', 'attachment', 'nav_menu_item' ),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'OR',
						array( 'key' => self::SAMPLE_META, 'value' => 1 ),
						array( 'key' => self::DEMO_META, 'value' => 1 ),
					),
				)
			);
			$comments = get_comments( array( 'meta_key' => self::SAMPLE_META, 'meta_value' => 1, 'fields' => 'ids' ) );
			$terms    = self::sample_terms_for_removal();
			$total    = count( $post_ids ) + count( $comments ) + count( $terms );
			if ( ! $dry ) {
				foreach ( $comments as $id ) {
					wp_delete_comment( $id, true );
				}
				foreach ( $post_ids as $id ) {
					wp_delete_post( $id, true );
				}
				$terms = self::sample_terms_for_removal();
				foreach ( $terms as $term ) {
					wp_delete_term( $term['id'], $term['taxonomy'] );
				}
				$total = count( $post_ids ) + count( $comments ) + count( $terms );
				self::flush_all_caches();
			}
			return array( 'deleted' => $total );
		}

		protected static function sample_terms_for_removal() {
			$out = array();
			foreach ( array( 'category', 'post_tag', 'nav_menu' ) as $taxonomy ) {
				$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'meta_key' => self::SAMPLE_META, 'meta_value' => 1 ) );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( 'nav_menu' === $taxonomy || 0 === (int) $term->count ) {
						$out[] = array( 'id' => (int) $term->term_id, 'taxonomy' => $taxonomy );
					}
				}
			}
			return $out;
		}

		protected static function demo_count() {
			return (int) count(
				get_posts(
					array(
						'post_type'      => array( 'post', 'page', 'attachment' ),
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => self::SAMPLE_META,
						'meta_value'     => 1,
					)
				)
			);
		}

		/* ============================================================
		   Tools — settings export/import, thumbnails
		   ============================================================ */

		protected static function export_settings() {
			$data = array( '_meta' => array( 'theme' => 'epiktetos', 'version' => defined( 'EPIKTETOS_VERSION' ) ? EPIKTETOS_VERSION : '', 'exported' => gmdate( 'c' ) ) );
			foreach ( self::option_keys() as $key ) {
				$data[ $key ] = get_option( $key, null );
			}
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=epiktetos-settings-' . gmdate( 'Ymd' ) . '.json' );
			echo wp_json_encode( $data, JSON_PRETTY_PRINT );
			exit;
		}

		protected static function import_settings() {
			if ( empty( $_FILES['epiktetos_settings_file'] ) || ! is_array( $_FILES['epiktetos_settings_file'] ) ) {
				return false;
			}
			$file = $_FILES['epiktetos_settings_file'];
			$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
			$name  = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
			$tmp   = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
			if ( UPLOAD_ERR_OK !== $error || ! $tmp || ! is_uploaded_file( $tmp ) ) {
				return false;
			}
			if ( $size <= 0 || $size > 1048576 || 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				return false;
			}
			$raw  = file_get_contents( $tmp );
			if ( false === $raw || strlen( $raw ) > 1048576 ) {
				return false;
			}
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				return false;
			}
			if ( isset( $data['_meta']['theme'] ) && 'epiktetos' !== sanitize_key( $data['_meta']['theme'] ) ) {
				return false;
			}
			$allowed = self::option_keys();
			foreach ( $data as $key => $value ) {
				if ( in_array( $key, $allowed, true ) ) {
					update_option( $key, self::sanitize_imported_option( $key, $value ) );
				}
			}
			self::flush_all_caches();
			return true;
		}

		public static function sanitize_imported_option( $key, $value ) {
			$map = array(
				'epiktetos_options'            => array( 'Epiktetos_Header', 'sanitize' ),
				'epiktetos_branding_options'   => array( 'Epiktetos_Branding', 'sanitize' ),
				'epiktetos_footer_options'     => array( 'Epiktetos_Footer', 'sanitize' ),
				'epiktetos_single_options'     => array( 'Epiktetos_Single', 'sanitize' ),
				'epiktetos_discussion_options' => array( 'Epiktetos_Comments', 'sanitize' ),
				'epiktetos_taxonomy_options'   => array( 'Epiktetos_Taxonomies', 'sanitize' ),
				'epiktetos_reader_options'     => array( 'Epiktetos_Reader', 'sanitize' ),
				'epiktetos_seo_options'        => array( 'Epiktetos_SEO', 'sanitize' ),
				'epiktetos_about_options'      => array( 'Epiktetos_Pages', 'sanitize_about' ),
				'epiktetos_category_order'     => array( 'Epiktetos_Categories', 'sanitize_order' ),
			);
			if ( isset( $map[ $key ] ) && is_callable( $map[ $key ] ) ) {
				return call_user_func( $map[ $key ], $value );
			}
			return self::sanitize_scalar_tree( $value );
		}

		protected static function sanitize_scalar_tree( $value ) {
			if ( is_array( $value ) ) {
				$clean = array();
				foreach ( $value as $k => $v ) {
					$clean[ sanitize_key( $k ) ] = self::sanitize_scalar_tree( $v );
				}
				return $clean;
			}
			return sanitize_text_field( wp_unslash( $value ) );
		}

		protected static function regenerate_thumbnails() {
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$ids = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 50, 'post_status' => 'inherit', 'fields' => 'ids' ) );
			$n = 0;
			foreach ( $ids as $id ) {
				$file = get_attached_file( $id );
				if ( $file && file_exists( $file ) ) {
					$meta = wp_generate_attachment_metadata( $id, $file );
					if ( $meta ) {
						wp_update_attachment_metadata( $id, $meta );
						$n++;
					}
				}
			}
			return $n;
		}

		/* ============================================================
		   System health
		   ============================================================ */

		protected static function status_dot( $level ) {
			$labels = array( 'ok' => __( 'Good', 'epiktetos' ), 'warn' => __( 'Check', 'epiktetos' ), 'bad' => __( 'Action needed', 'epiktetos' ), 'skip' => __( 'Skipped', 'epiktetos' ) );
			$title  = isset( $labels[ $level ] ) ? $labels[ $level ] : $labels['warn'];
			return '<span class="epi-dot epi-dot--' . esc_attr( $level ) . '" title="' . esc_attr( $title ) . '"></span>';
		}

		protected static function status_label( $level ) {
			$labels = array(
				'ok'   => __( 'PASS', 'epiktetos' ),
				'warn' => __( 'WARNING', 'epiktetos' ),
				'bad'  => __( 'WARNING', 'epiktetos' ),
				'skip' => __( 'SKIPPED', 'epiktetos' ),
			);
			return isset( $labels[ $level ] ) ? $labels[ $level ] : $labels['warn'];
		}

		/**
		 * Whether the site is running in a local/Docker development environment.
		 * Used to downgrade HTTP self-check failures (which are expected on
		 * loopback setups) to "Skipped" instead of a misleading WARNING.
		 */
		protected static function is_local_environment() {
			if ( file_exists( '/.dockerenv' ) ) {
				return true;
			}
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return true;
			}
			foreach ( array( '.local', '.test', '.localhost', '.ddev.site' ) as $suffix ) {
				if ( '' !== $host && substr( $host, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			}
			if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
				return true;
			}
			return false;
		}

		protected static function row( $label, $value, $level = 'ok' ) {
			return '<tr><th>' . self::status_dot( $level ) . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>';
		}

		protected static function bytes( $val ) {
			$val = trim( (string) $val );
			$unit = strtolower( substr( $val, -1 ) );
			$num  = (float) $val;
			$mult = array( 'g' => 1073741824, 'm' => 1048576, 'k' => 1024 );
			return isset( $mult[ $unit ] ) ? $num * $mult[ $unit ] : $num;
		}

		public static function render_system() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			global $wp_version, $wpdb;

			$php_ok   = version_compare( PHP_VERSION, '8.0', '>=' );
			$mem      = self::bytes( ini_get( 'memory_limit' ) );
			$upload   = wp_max_upload_size();
			$imagick  = extension_loaded( 'imagick' );
			$gd       = extension_loaded( 'gd' );
			$permalink = (bool) get_option( 'permalink_structure' );
			$cron     = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
			$objcache = wp_using_ext_object_cache();
			$db_size  = (float) $wpdb->get_var( "SELECT SUM(data_length+index_length) FROM information_schema.tables WHERE table_schema='" . DB_NAME . "'" );

			$rows  = '';
			$rows .= self::row( __( 'PHP version', 'epiktetos' ), PHP_VERSION, $php_ok ? 'ok' : 'bad' );
			$rows .= self::row( __( 'WordPress version', 'epiktetos' ), esc_html( $wp_version ), 'ok' );
			$rows .= self::row( __( 'Theme version', 'epiktetos' ), esc_html( defined( 'EPIKTETOS_VERSION' ) ? EPIKTETOS_VERSION : '—' ), 'ok' );
			$rows .= self::row( __( 'Memory limit', 'epiktetos' ), esc_html( ini_get( 'memory_limit' ) ), $mem >= 134217728 ? 'ok' : 'warn' );
			$rows .= self::row( __( 'Max upload size', 'epiktetos' ), esc_html( size_format( $upload ) ), $upload >= 8388608 ? 'ok' : 'warn' );
			$rows .= self::row( __( 'Image library', 'epiktetos' ), $imagick ? 'Imagick' : ( $gd ? 'GD' : __( 'None', 'epiktetos' ) ), ( $imagick || $gd ) ? 'ok' : 'bad' );
			$rows .= self::row( __( 'Permalinks', 'epiktetos' ), $permalink ? __( 'Pretty', 'epiktetos' ) : __( 'Plain', 'epiktetos' ), $permalink ? 'ok' : 'warn' );
			$rows .= self::row( __( 'WP-Cron', 'epiktetos' ), $cron ? __( 'Enabled', 'epiktetos' ) : __( 'Disabled', 'epiktetos' ), $cron ? 'ok' : 'warn' );
			$rows .= self::row( __( 'REST API', 'epiktetos' ), self::rest_ok() ? __( 'Reachable', 'epiktetos' ) : __( 'Unreachable', 'epiktetos' ), self::rest_ok() ? 'ok' : 'bad' );
			$rows .= self::row( __( 'Object cache', 'epiktetos' ), $objcache ? __( 'External', 'epiktetos' ) : __( 'Default (DB transients)', 'epiktetos' ), 'ok' );
			$rows .= self::row( __( 'Database size', 'epiktetos' ), esc_html( size_format( $db_size ) ), 'ok' );

			// Content counts.
			$counts  = '';
			$counts .= self::row( __( 'Published posts', 'epiktetos' ), (int) wp_count_posts()->publish, 'ok' );
			$counts .= self::row( __( 'Media items', 'epiktetos' ), (int) array_sum( (array) wp_count_attachments() ), 'ok' );
			$counts .= self::row( __( 'Categories', 'epiktetos' ), (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ), 'ok' );
			$counts .= self::row( __( 'Tags', 'epiktetos' ), (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ), 'ok' );
			$counts .= self::row( __( 'Comments', 'epiktetos' ), (int) wp_count_comments()->approved, 'ok' );
			$counts .= self::row( __( 'Sample posts', 'epiktetos' ), self::demo_count(), 'ok' );

			// Cache statuses.
			$caches = '';
			foreach ( self::cache_keys() as $key => $label ) {
				$set = false !== get_transient( $key );
				$caches .= self::row( $label, $set ? __( 'Warm', 'epiktetos' ) : __( 'Empty', 'epiktetos' ), 'ok' );
			}

			self::open( __( 'System', 'epiktetos' ), __( 'Environment, content, and cache status for this installation.', 'epiktetos' ) );
			echo '<div class="epi-grid epi-grid--2">';
			echo '<div class="epi-card"><h2>' . esc_html__( 'Environment', 'epiktetos' ) . '</h2><table class="epi-health">' . $rows . '</table></div>';
			echo '<div class="epi-card"><h2>' . esc_html__( 'Content', 'epiktetos' ) . '</h2><table class="epi-health">' . $counts . '</table>';
			echo '<h2 style="margin-top:1.5em">' . esc_html__( 'Caches', 'epiktetos' ) . '</h2><table class="epi-health">' . $caches . '</table></div>';
			echo '</div>';
			self::close();
		}

		public static function render_dashboard() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			global $wp_version;

			$version = defined( 'EPIKTETOS_VERSION' ) ? EPIKTETOS_VERSION : wp_get_theme()->get( 'Version' );
			$pages   = array(
				__( 'Active homepage', 'epiktetos' ) => self::active_homepage_label(),
				__( 'Active About page', 'epiktetos' ) => self::page_status_label( 'about' ),
				__( 'Active Topics page', 'epiktetos' ) => self::page_status_label( 'topics' ),
				__( 'Active Contact page', 'epiktetos' ) => self::page_status_label( 'contact' ),
			);
			$stats   = array(
				__( 'Articles', 'epiktetos' ) => (int) wp_count_posts( 'post' )->publish,
				__( 'Categories', 'epiktetos' ) => self::term_count( 'category' ),
				__( 'Tags', 'epiktetos' ) => self::term_count( 'post_tag' ),
				__( 'Images', 'epiktetos' ) => self::image_count(),
				__( 'Comments', 'epiktetos' ) => (int) wp_count_comments()->approved,
				__( 'Average reading time', 'epiktetos' ) => self::average_reading_time_label(),
			);

			self::open( __( 'Dashboard', 'epiktetos' ), __( 'A concise overview of the local publication and theme setup.', 'epiktetos' ) );
			echo '<div class="epi-grid epi-grid--2">';
			echo '<div class="epi-card"><h2>' . esc_html__( 'Site setup', 'epiktetos' ) . '</h2><dl class="epi-meta-grid">';
			echo '<div><dt>' . esc_html__( 'Theme version', 'epiktetos' ) . '</dt><dd>' . esc_html( $version ) . '</dd></div>';
			echo '<div><dt>' . esc_html__( 'WordPress version', 'epiktetos' ) . '</dt><dd>' . esc_html( $wp_version ) . '</dd></div>';
			echo '<div><dt>' . esc_html__( 'PHP version', 'epiktetos' ) . '</dt><dd>' . esc_html( PHP_VERSION ) . '</dd></div>';
			foreach ( $pages as $label => $value ) {
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . wp_kses_post( $value ) . '</dd></div>';
			}
			echo '</dl></div>';

			echo '<div class="epi-card"><h2>' . esc_html__( 'Editorial statistics', 'epiktetos' ) . '</h2><dl class="epi-meta-grid">';
			foreach ( $stats as $label => $value ) {
				$display = is_int( $value ) ? number_format_i18n( $value ) : $value;
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $display ) . '</dd></div>';
			}
			echo '</dl></div>';

			echo '<div class="epi-card epi-section--wide"><h2>' . esc_html__( 'Quick links', 'epiktetos' ) . '</h2>';
			echo '<div class="epi-quick-links">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=epiktetos-settings' ) ) . '">' . esc_html__( 'Settings', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=epiktetos-wizard' ) ) . '">' . esc_html__( 'Setup Wizard', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=epiktetos-validator' ) ) . '">' . esc_html__( 'Validator', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=epiktetos-system' ) ) . '">' . esc_html__( 'System', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=epiktetos-tools' ) ) . '">' . esc_html__( 'Tools', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( admin_url( 'post-new.php' ) ) . '">' . esc_html__( 'Create New Post', 'epiktetos' ) . '</a>';
			echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Visit Homepage', 'epiktetos' ) . '</a>';
			echo '</div></div>';
			echo '</div>';
			self::close();
		}

		protected static function active_homepage_label() {
			if ( 'page' !== get_option( 'show_on_front' ) ) {
				return esc_html__( 'Latest posts', 'epiktetos' );
			}
			$page_id = (int) get_option( 'page_on_front' );
			return $page_id ? esc_html( get_the_title( $page_id ) ) : esc_html__( 'Static page not selected', 'epiktetos' );
		}

		protected static function page_status_label( $slug ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				return '<span class="epi-muted">' . esc_html__( 'Not found', 'epiktetos' ) . '</span>';
			}
			$status = get_post_status( $page );
			$title  = get_the_title( $page );
			if ( 'publish' !== $status ) {
				return esc_html( $title ) . ' <span class="epi-muted">(' . esc_html( $status ) . ')</span>';
			}
			return esc_html( $title );
		}

		protected static function image_count() {
			$counts = wp_count_attachments( 'image' );
			return (int) array_sum( (array) $counts );
		}

		protected static function average_reading_time_label() {
			$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids' ) );
			if ( empty( $posts ) ) {
				return __( '0 min', 'epiktetos' );
			}
			$total = 0;
			foreach ( $posts as $post_id ) {
				$post = get_post( $post_id );
				$total += $post && class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : 1;
			}
			$avg = max( 1, (int) round( $total / count( $posts ) ) );
			return sprintf( _n( '%d min', '%d min', $avg, 'epiktetos' ), $avg );
		}

		protected static function term_count( $taxonomy ) {
			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			return is_wp_error( $count ) ? 0 : (int) $count;
		}

		protected static function rest_ok() {
			$res = wp_remote_get( rest_url(), array( 'timeout' => 4, 'sslverify' => false ) );
			return ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) < 500;
		}

		/* ============================================================
		   Tools
		   ============================================================ */

		public static function render_tools() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			self::open( __( 'Tools', 'epiktetos' ), __( 'Maintenance utilities. Destructive actions ask for confirmation.', 'epiktetos' ) );

			echo '<div class="epi-grid epi-grid--2">';

			// Caches.
			echo '<div class="epi-card"><h2>' . esc_html__( 'Caches', 'epiktetos' ) . '</h2><p class="epi-muted">' . esc_html__( 'Reading time and reader state are computed live / stored client-side; the theme caches the showcase, editorial picks, and stats.', 'epiktetos' ) . '</p>';
			echo '<div class="epi-actions">';
			self::action_button( 'clear_cache_all', __( 'Clear all caches', 'epiktetos' ), 'primary' );
			self::action_button( 'clear_cache_theme', __( 'Clear showcase cache', 'epiktetos' ) );
			self::action_button( 'clear_cache_editorial', __( 'Clear editorial cache', 'epiktetos' ) );
			self::action_button( 'recalc_reading_time', __( 'Recalculate reading time', 'epiktetos' ) );
			echo '</div></div>';

			// Media.
			echo '<div class="epi-card"><h2>' . esc_html__( 'Media', 'epiktetos' ) . '</h2><p class="epi-muted">' . esc_html__( 'Rebuild image sizes (requires an image library; processes up to 50 images).', 'epiktetos' ) . '</p>';
			echo '<div class="epi-actions">';
			self::action_button( 'regen_thumbs', __( 'Regenerate thumbnails', 'epiktetos' ), '', __( 'Regenerate image sizes now?', 'epiktetos' ) );
			echo '</div></div>';

			// Settings export/import.
			echo '<div class="epi-card"><h2>' . esc_html__( 'Settings', 'epiktetos' ) . '</h2><p class="epi-muted">' . esc_html__( 'Back up or move all Epiktetos options between installs.', 'epiktetos' ) . '</p>';
			echo '<div class="epi-actions">';
			self::action_button( 'export_settings', __( 'Export settings', 'epiktetos' ) );
			echo '</div>';
			echo '<form method="post" enctype="multipart/form-data" class="epi-import">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="epiktetos_action" value="import_settings" />';
			echo '<input type="file" name="epiktetos_settings_file" accept="application/json" required /> ';
			echo '<button type="submit" class="button" data-epi-confirm="' . esc_attr__( 'Import settings and overwrite current options?', 'epiktetos' ) . '">' . esc_html__( 'Import settings', 'epiktetos' ) . '</button>';
			echo '</form></div>';

			// Validate link.
			echo '<div class="epi-card"><h2>' . esc_html__( 'Validation', 'epiktetos' ) . '</h2><p class="epi-muted">' . esc_html__( 'Run automated content and configuration checks.', 'epiktetos' ) . '</p>';
			echo '<div class="epi-actions"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=epiktetos-validator' ) ) . '">' . esc_html__( 'Open validator', 'epiktetos' ) . '</a></div></div>';

			echo '</div>';
			self::close();
		}

		/* ============================================================
		   Sample Content
		   ============================================================ */

		public static function render_demo() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
				self::open( __( 'Sample Content', 'epiktetos' ), __( 'Create the bundled local example publication, then remove it whenever you like.', 'epiktetos' ) );
			self::render_sample_content_panel();
			self::close();
		}

		/**
		 * Inner Sample Content panel, used by the Settings "Sample Content" tab.
		 *
			 * Creates only locally bundled example posts, pages, menus, and media on explicit confirmation —
			 * it never downloads anything, never contacts an external server, never
			 * imports XML, and never overwrites the user's own content.
			 */
			public static function render_sample_content_panel() {
				if ( ! current_user_can( self::CAP ) ) {
					return;
				}
				$count     = self::demo_count();
				$available = self::sample_manifest_counts();

				echo '<div class="epi-card"><p class="epi-muted">' . esc_html__( 'Sample Content is bundled inside the theme. Creating it adds local example posts, pages, menus, taxonomies and images. It does not download anything, does not install plugins, and only updates the explicit sample slugs.', 'epiktetos' ) . '</p></div>';

				echo '<div class="epi-card"><p>';
				echo esc_html__( 'Bundled package:', 'epiktetos' ) . ' ';
				printf(
					/* translators: 1: posts, 2: pages, 3: media. */
					esc_html__( '%1$d posts, %2$d pages, %3$d images.', 'epiktetos' ),
					(int) ( isset( $available['posts'] ) ? $available['posts'] : 0 ),
					(int) ( isset( $available['pages'] ) ? $available['pages'] : 0 ),
					(int) ( isset( $available['media'] ) ? $available['media'] : 0 )
				);
				echo ' ';
				/* translators: %d: number of sample items currently created. */
				echo esc_html( sprintf( _n( '%d theme-created item is currently present.', '%d theme-created items are currently present.', $count, 'epiktetos' ), $count ) );
				echo '</p>';
				echo '<form method="post"><label class="epi-dry"><input type="checkbox" name="dry_run" value="1" /> ' . esc_html__( 'Preview only (show what would change, make no changes)', 'epiktetos' ) . '</label>';
				echo '<div class="epi-actions" style="margin-top:1em">';
				wp_nonce_field( self::NONCE );
				echo '<button class="button button-primary" name="epiktetos_action" value="demo_import">' . esc_html__( 'Create Sample Content', 'epiktetos' ) . '</button>';
				echo '<button class="button epi-danger" name="epiktetos_action" value="demo_reset" data-epi-confirm="' . esc_attr__( 'Remove only the sample content created by this theme? Your own content is never touched.', 'epiktetos' ) . '">' . esc_html__( 'Remove Sample Content', 'epiktetos' ) . '</button>';
				echo '</div></form></div>';
			}

		/* ============================================================
		   Validator
		   ============================================================ */

		public static function render_validator() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			self::open( __( 'Theme health', 'epiktetos' ), __( 'Plain-language checks for content, accessibility, and repository readiness.', 'epiktetos' ) );
			self::render_validator_panel();
			self::close();
		}

		/**
		 * Inner theme-health content, used by the Settings "Theme health" tab.
		 * Leads with a plain-language summary; the technical breakdown is behind
		 * a native disclosure so users are not confronted with jargon up front.
		 */
		public static function render_validator_panel() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			$checks = self::run_checks();
			$tally  = array( 'ok' => 0, 'warn' => 0, 'bad' => 0, 'skip' => 0 );
			foreach ( $checks as $c ) {
				$lvl = isset( $c['level'] ) ? $c['level'] : 'warn';
				$tally[ $lvl ] = ( isset( $tally[ $lvl ] ) ? $tally[ $lvl ] : 0 ) + 1;
			}
			$needs = $tally['warn'] + $tally['bad'];

			echo '<div class="epi-card">';
			if ( 0 === $needs ) {
				echo '<p><strong>' . esc_html__( 'Everything looks good.', 'epiktetos' ) . '</strong> ';
			} else {
				/* translators: %d: number of checks needing attention. */
				echo '<p><strong>' . esc_html( sprintf( _n( '%d item needs a look.', '%d items need a look.', $needs, 'epiktetos' ), $needs ) ) . '</strong> ';
			}
			/* translators: %d: number of checks that passed. */
			echo esc_html( sprintf( _n( '%d check passed.', '%d checks passed.', $tally['ok'], 'epiktetos' ), $tally['ok'] ) );
			if ( $tally['skip'] > 0 ) {
				echo ' ';
				/* translators: %d: number of checks skipped in this environment. */
				echo esc_html( sprintf( _n( '%d check was skipped for this environment.', '%d checks were skipped for this environment.', $tally['skip'], 'epiktetos' ), $tally['skip'] ) );
			}
			echo '</p></div>';

			$groups = array(
				'accessibility' => __( 'Accessibility', 'epiktetos' ),
				'performance'   => __( 'Performance', 'epiktetos' ),
				'seo'           => __( 'SEO', 'epiktetos' ),
				'content'       => __( 'Content', 'epiktetos' ),
				'branding'      => __( 'Branding', 'epiktetos' ),
				'reader'        => __( 'Reader', 'epiktetos' ),
				'security'      => __( 'Security', 'epiktetos' ),
				'theme'         => __( 'Theme', 'epiktetos' ),
				'system'        => __( 'System', 'epiktetos' ),
				'wordpressorg'  => __( 'WordPress.org Readiness', 'epiktetos' ),
			);
			echo '<details class="epi-validator-details"' . ( $needs ? ' open' : '' ) . '>';
			echo '<summary>' . esc_html__( 'Show detailed checks', 'epiktetos' ) . '</summary>';
			foreach ( $groups as $gkey => $gtitle ) {
				$rows = array();
				foreach ( $checks as $c ) {
					if ( ( isset( $c['group'] ) ? $c['group'] : 'environment' ) === $gkey ) {
						$rows[] = $c;
					}
				}
				if ( empty( $rows ) ) {
					continue;
				}
				echo '<h2 class="epi-validator__group">' . esc_html( $gtitle ) . '</h2>';
				echo '<table class="epi-health epi-validator">';
				foreach ( $rows as $c ) {
					$c = self::normalize_check( $c );
					echo '<tr><th>' . self::status_dot( $c['level'] ) . esc_html( $c['label'] ) . '</th><td>';
					echo '<strong class="epi-validator__status epi-validator__status--' . esc_attr( $c['level'] ) . '">' . esc_html( self::status_label( $c['level'] ) ) . '</strong>';
					echo '<span class="epi-check__what">' . esc_html( $c['detail'] ) . '</span>';
					if ( in_array( $c['level'], array( 'warn', 'bad' ), true ) ) {
						echo '<span class="epi-check__why"><strong>' . esc_html__( 'Why:', 'epiktetos' ) . '</strong> ' . esc_html( $c['why'] ) . '</span>';
						echo '<span class="epi-check__fix"><strong>' . esc_html__( 'Fix:', 'epiktetos' ) . '</strong> ' . esc_html( $c['fix'] ) . '</span>';
						if ( ! empty( $c['action'] ) && ! empty( $c['action_label'] ) ) {
							echo '<span class="epi-check__action">';
							self::action_button( $c['action'], $c['action_label'], '', isset( $c['confirm'] ) ? $c['confirm'] : '' );
							echo '</span>';
						}
					}
					echo '</td></tr>';
				}
				echo '</table>';
			}
			echo '</details>';
		}

		protected static function normalize_check( $check ) {
			$check = wp_parse_args( $check, array(
				'group'        => 'system',
				'label'        => '',
				'level'        => 'warn',
				'detail'       => '',
				'why'          => '',
				'fix'          => '',
				'action'       => '',
				'action_label' => '',
				'confirm'      => '',
			) );
			if ( in_array( $check['level'], array( 'warn', 'bad' ), true ) ) {
				if ( '' === $check['why'] ) {
					$check['why'] = __( 'This can affect review readiness, accessibility, or the first-run experience for site owners.', 'epiktetos' );
				}
				if ( '' === $check['fix'] ) {
					$check['fix'] = __( 'Review the item above and update the related WordPress content, setting, or theme file before submission.', 'epiktetos' );
				}
			}
			return $check;
		}

		protected static function run_checks() {
			$checks = array();
			$published = get_posts( array( 'numberposts' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) );

			// Featured images.
			$no_thumb = 0;
			$no_excerpt = 0;
			foreach ( $published as $id ) {
				if ( ! has_post_thumbnail( $id ) ) { $no_thumb++; }
				$p = get_post( $id );
				if ( ! has_excerpt( $p ) && '' === trim( $p->post_excerpt ) ) { $no_excerpt++; }
			}
			$checks[] = array( 'group' => 'content', 'label' => __( 'Featured images', 'epiktetos' ), 'level' => $no_thumb ? 'warn' : 'ok', 'detail' => $no_thumb ? sprintf( __( '%d posts missing a featured image.', 'epiktetos' ), $no_thumb ) : __( 'All posts have featured images.', 'epiktetos' ) );
			$checks[] = array( 'group' => 'content', 'label' => __( 'Excerpts', 'epiktetos' ), 'level' => $no_excerpt ? 'warn' : 'ok', 'detail' => $no_excerpt ? sprintf( __( '%d posts missing an excerpt.', 'epiktetos' ), $no_excerpt ) : __( 'All posts have excerpts.', 'epiktetos' ) );

			// Featured images present (count posts that have one).
			$with_thumb = count( $published ) - $no_thumb;
			$checks[] = array( 'group' => 'content', 'label' => __( 'Featured images present', 'epiktetos' ), 'level' => $with_thumb >= 20 ? 'ok' : 'warn', 'detail' => sprintf( __( '%d published posts have a featured image.', 'epiktetos' ), $with_thumb ), 'why' => __( 'Featured images help archive, search, and social-preview layouts remain complete.', 'epiktetos' ), 'fix' => __( 'Add a featured image to each published article that should appear in editorial indexes.', 'epiktetos' ) );

			// Featured image alt text (alt on each post's thumbnail).
			$feat_no_alt = 0;
			foreach ( $published as $id ) {
				$tid = get_post_thumbnail_id( $id );
				if ( $tid && '' === trim( (string) get_post_meta( $tid, '_wp_attachment_image_alt', true ) ) ) { $feat_no_alt++; }
			}
			$checks[] = array( 'group' => 'accessibility', 'label' => __( 'Featured image alt text', 'epiktetos' ), 'level' => $feat_no_alt ? 'warn' : 'ok', 'detail' => $feat_no_alt ? sprintf( __( '%d featured images have no alt text.', 'epiktetos' ), $feat_no_alt ) : __( 'Every featured image has alt text.', 'epiktetos' ), 'why' => __( 'Screen-reader users need meaningful image alternatives when images carry editorial context.', 'epiktetos' ), 'fix' => __( 'Edit the related media attachments and add concise alt text, or leave decorative images intentionally empty.', 'epiktetos' ) );

			// OG image.
			$og = class_exists( 'Epiktetos_Branding' ) ? (int) Epiktetos_Branding::get( 'default_og_image_id' ) : 0;
			$checks[] = array( 'group' => 'seo', 'label' => __( 'Default OG image', 'epiktetos' ), 'level' => $og ? 'ok' : 'warn', 'detail' => $og ? __( 'Configured.', 'epiktetos' ) : __( 'Not set — social shares fall back to the featured image only.', 'epiktetos' ) );

			// Permalinks.
			$pl = (bool) get_option( 'permalink_structure' );
			$checks[] = array( 'group' => 'seo', 'label' => __( 'Permalinks', 'epiktetos' ), 'level' => $pl ? 'ok' : 'warn', 'detail' => $pl ? __( 'Pretty permalinks enabled.', 'epiktetos' ) : __( 'Plain permalinks — set a pretty structure for SEO-friendly URLs.', 'epiktetos' ) );

			// Category integrity.
			$default   = (int) get_option( 'default_category' );
			$empty_cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids', 'exclude' => array( $default ) ) );
			$empty_count = 0;
			foreach ( $empty_cats as $tid ) { if ( 0 === (int) get_term( $tid )->count ) { $empty_count++; } }

			// Uncategorized / orphaned posts. Resolve the bucket by the canonical
			// "uncategorized" slug rather than default_category — the default can be
			// repointed to a real category (e.g. when the classic Uncategorized term
			// was removed), and its well-categorized posts must not be miscounted.
			$uncat_term = get_term_by( 'slug', 'uncategorized', 'category' );
			$orphans = 0;
			foreach ( $published as $pid ) {
				if ( 'post' !== get_post_type( $pid ) ) { continue; }
				$cats = wp_get_post_categories( $pid );
				if ( empty( $cats ) ) {
					$orphans++;
				} elseif ( $uncat_term && 1 === count( $cats ) && (int) $cats[0] === (int) $uncat_term->term_id ) {
					$orphans++;
				}
			}
			$checks[] = array( 'group' => 'content', 'label' => __( 'Uncategorized posts', 'epiktetos' ), 'level' => $orphans ? 'warn' : 'ok', 'detail' => $orphans ? sprintf( __( '%d posts have no real category.', 'epiktetos' ), $orphans ) : __( 'No orphaned posts — every post has a real category.', 'epiktetos' ) );
			$checks[] = array( 'group' => 'content', 'label' => __( 'Empty categories', 'epiktetos' ), 'level' => $empty_count ? 'warn' : 'ok', 'detail' => $empty_count ? sprintf( __( '%d categories have no posts.', 'epiktetos' ), $empty_count ) : __( 'All categories have posts.', 'epiktetos' ) );

			// Editorial structure (demo fidelity): 4 main categories, 5 posts each.
			$main_terms = array();
			foreach ( array_keys( self::demo_categories() ) as $cslug ) {
				$ct = get_category_by_slug( $cslug );
				if ( $ct ) { $main_terms[ $cslug ] = $ct; }
			}
			$main_count = count( $main_terms );
			$checks[] = array( 'group' => 'content', 'label' => __( 'Main categories', 'epiktetos' ), 'level' => 4 === $main_count ? 'ok' : 'warn', 'detail' => sprintf( __( '%d of the 4 expected main categories present.', 'epiktetos' ), $main_count ) );
			$off = array();
			foreach ( $main_terms as $ct ) {
				$n = (int) count( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'category' => $ct->term_id ) ) );
				if ( 5 !== $n ) { $off[] = $ct->name . " ($n)"; }
			}
			$checks[] = array( 'group' => 'content', 'label' => __( 'Posts per main category', 'epiktetos' ), 'level' => ( $main_count && empty( $off ) ) ? 'ok' : 'warn', 'detail' => empty( $off ) ? __( 'Each main category has 5 published posts.', 'epiktetos' ) : sprintf( __( 'Not at 5 posts: %s', 'epiktetos' ), implode( ', ', $off ) ) );

			// Key pages exist and are published.
			$want_pages = array( 'about' => __( 'About', 'epiktetos' ), 'topics' => __( 'Topics', 'epiktetos' ), 'contact' => __( 'Contact', 'epiktetos' ), 'saved' => __( 'Saved', 'epiktetos' ) );
			$missing_pages = array();
			foreach ( $want_pages as $pslug => $pname ) {
				$pg = get_page_by_path( $pslug );
				if ( ! $pg || 'publish' !== get_post_status( $pg ) ) { $missing_pages[] = $pname; }
			}
			$checks[] = array( 'group' => 'content', 'label' => __( 'Key pages', 'epiktetos' ), 'level' => empty( $missing_pages ) ? 'ok' : 'warn', 'detail' => empty( $missing_pages ) ? __( 'About, Topics, Contact and Saved pages all exist.', 'epiktetos' ) : sprintf( __( 'Missing or unpublished: %s', 'epiktetos' ), implode( ', ', $missing_pages ) ) );

			// Missing image alt text (all attachments).
			$imgs = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => -1, 'post_status' => 'inherit', 'fields' => 'ids' ) );
			$no_alt = 0;
			foreach ( $imgs as $aid ) {
				if ( '' === trim( (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) ) ) { $no_alt++; }
			}
			$checks[] = array( 'group' => 'accessibility', 'label' => __( 'Image alt text', 'epiktetos' ), 'level' => $no_alt ? 'warn' : 'ok', 'detail' => $no_alt ? sprintf( __( '%d images have no alt text.', 'epiktetos' ), $no_alt ) : __( 'All images have alt text.', 'epiktetos' ), 'why' => __( 'The media library may be reused across posts, archives, and pages where missing alt text becomes an accessibility gap.', 'epiktetos' ), 'fix' => __( 'Review Media Library attachments and add alt text where the image communicates content.', 'epiktetos' ) );

			// --- Skip link (static — does not depend on HTTP self-fetch) ---
			$skip_ok = function_exists( 'epiktetos_skip_link' ) && false !== has_action( 'wp_body_open', 'epiktetos_skip_link' );
			$checks[] = array( 'group' => 'accessibility', 'label' => __( 'Skip link', 'epiktetos' ), 'level' => $skip_ok ? 'ok' : 'bad', 'detail' => $skip_ok ? __( 'Skip-to-content link is registered on wp_body_open.', 'epiktetos' ) : __( 'Skip link not registered.', 'epiktetos' ) );

			// --- Main landmark integrity (static — scan template files) ---
			$tpl_dir = trailingslashit( get_template_directory() ) . 'templates';
			$bad_tpl = array();
			foreach ( (array) glob( $tpl_dir . '/*.html' ) as $tpl ) {
				$n = substr_count( (string) file_get_contents( $tpl ), 'id="main-content"' );
				if ( 1 !== $n ) { $bad_tpl[] = basename( $tpl ) . " ($n)"; }
			}
				$checks[] = array( 'group' => 'accessibility', 'label' => __( 'Main landmark / skip target', 'epiktetos' ), 'level' => empty( $bad_tpl ) ? 'ok' : 'bad', 'detail' => empty( $bad_tpl ) ? __( 'Every template has exactly one #main-content target.', 'epiktetos' ) : sprintf( __( 'Templates without a single target: %s', 'epiktetos' ), implode( ', ', $bad_tpl ) ) );

				// Performance readiness.
				$frontend_css = self::theme_file_size( 'assets/css/frontend.css' );
				$checks[] = array(
					'group'  => 'performance',
					'label'  => __( 'Frontend CSS size', 'epiktetos' ),
					'level'  => $frontend_css > 153600 ? 'warn' : 'ok',
					'detail' => sprintf( __( 'frontend.css is %s.', 'epiktetos' ), size_format( $frontend_css ) ),
				);

				$frontend_js = 0;
				foreach ( (array) glob( get_template_directory() . '/assets/js/*.js' ) as $js_file ) {
					if ( false === strpos( basename( $js_file ), 'admin' ) ) {
						$frontend_js += (int) filesize( $js_file );
					}
				}
				$checks[] = array(
					'group'  => 'performance',
					'label'  => __( 'Frontend JS size', 'epiktetos' ),
					'level'  => $frontend_js > 122880 ? 'warn' : 'ok',
					'detail' => sprintf( __( 'Frontend scripts total %s before compression.', 'epiktetos' ), size_format( $frontend_js ) ),
				);

				// Detect external font references in theme code. The needles are
				// split so this validator file never matches its own source.
				$font_needles  = array( preg_quote( 'fonts.googleapis' . '.com', '~' ), preg_quote( 'fonts.gstatic' . '.com', '~' ) );
				$external_fonts = self::theme_contains_patterns( $font_needles, array( 'php', 'css', 'js' ) );
				$checks[] = array(
					'group'  => 'performance',
					'label'  => __( 'External font requests', 'epiktetos' ),
					'level'  => empty( $external_fonts ) ? 'ok' : 'warn',
					'detail' => empty( $external_fonts ) ? __( 'Fonts are self-hosted; no external font requests are made.', 'epiktetos' ) : sprintf( __( 'External font references found in: %s', 'epiktetos' ), implode( ', ', array_slice( $external_fonts, 0, 6 ) ) ),
					'why'    => __( 'External font requests add a third-party dependency and can expose visitor IP addresses.', 'epiktetos' ),
					'fix'    => __( 'Bundle the required fonts locally as WOFF2 and enqueue them from the theme.', 'epiktetos' ),
				);

				$local_fonts = glob( get_template_directory() . '/assets/fonts/*.{woff2,woff}', GLOB_BRACE );
				$checks[] = array(
					'group'  => 'performance',
					'label'  => __( 'Bundled webfonts', 'epiktetos' ),
					'level'  => empty( $local_fonts ) ? 'warn' : 'ok',
					'detail' => empty( $local_fonts ) ? __( 'No bundled WOFF2 fonts found; only system fallbacks are available.', 'epiktetos' ) : sprintf( __( '%d bundled font file(s) present and self-hosted.', 'epiktetos' ), count( $local_fonts ) ),
					'why'    => __( 'Self-hosted fonts preserve the intended typography without external requests.', 'epiktetos' ),
					'fix'    => __( 'Add WOFF2 files under assets/fonts/ and reference them with @font-face.', 'epiktetos' ),
				);

				$empty_caches = array();
				foreach ( self::cache_keys() as $key => $label ) {
					if ( false === get_transient( $key ) ) {
						$empty_caches[] = $label;
					}
				}
				$checks[] = array(
					'group'  => 'performance',
					'label'  => __( 'Theme cache warmth', 'epiktetos' ),
					'level'  => empty( $empty_caches ) ? 'ok' : 'warn',
					'detail' => empty( $empty_caches ) ? __( 'Known Epiktetos transients are warm.', 'epiktetos' ) : sprintf( __( 'Cold or expired: %s.', 'epiktetos' ), implode( ', ', $empty_caches ) ),
					'why'    => __( 'Cold caches are normal locally, but warmed caches give more representative performance checks.', 'epiktetos' ),
					'fix'    => __( 'Clear the known theme caches and browse the affected pages once to warm them again.', 'epiktetos' ),
					'action' => empty( $empty_caches ) ? '' : 'clear_cache_all',
					'action_label' => __( 'Clear caches', 'epiktetos' ),
				);

				$full_demo = self::full_demo_present();
				$checks[] = array(
					'group'  => 'content',
					'label'  => __( 'Sample content state', 'epiktetos' ),
					'level'  => $full_demo ? 'ok' : 'warn',
					'detail' => $full_demo ? __( 'A full set of articles is present; sample content stays paused.', 'epiktetos' ) : __( 'A full set of articles was not detected; sample content may still be useful.', 'epiktetos' ),
					'why'    => __( 'Sample-content checks are local previews only and should not create duplicate placeholder content.', 'epiktetos' ),
					'fix'    => __( 'Use Sample Content only on local installs, or skip this note before repository packaging.', 'epiktetos' ),
				);

				// 404 template.
				$has404 = file_exists( get_template_directory() . '/templates/404.html' ) || file_exists( get_template_directory() . '/templates/index.html' );
			$checks[] = array( 'group' => 'theme', 'label' => __( '404 template', 'epiktetos' ), 'level' => $has404 ? 'ok' : 'warn', 'detail' => $has404 ? __( 'Present (or index fallback).', 'epiktetos' ) : __( 'No 404 template found.', 'epiktetos' ), 'why' => __( 'Users need a predictable template for missing URLs.', 'epiktetos' ), 'fix' => __( 'Add templates/404.html or ensure templates/index.html provides an acceptable fallback.', 'epiktetos' ) );

			// --- Header behavior settings (Sticky + Transparent) ---
			$hdr_defaults = class_exists( 'Epiktetos_Header' ) ? Epiktetos_Header::defaults() : array();
			$hdr_registered = isset( $hdr_defaults['sticky_header'] ) && isset( $hdr_defaults['transparent_header'] );
			$checks[] = array(
				'group'  => 'theme',
				'label'  => __( 'Header behavior settings', 'epiktetos' ),
				'level'  => $hdr_registered ? 'ok' : 'warn',
				'detail' => $hdr_registered ? __( 'Sticky and Transparent header settings are registered.', 'epiktetos' ) : __( 'A header behavior setting is not registered.', 'epiktetos' ),
				'why'    => __( 'Header settings must exist as real options to control frontend behavior.', 'epiktetos' ),
				'fix'    => __( 'Register sticky_header and transparent_header in Epiktetos_Header::defaults().', 'epiktetos' ),
			);

			$css_src = (string) @file_get_contents( get_template_directory() . '/assets/css/frontend.css' );
			$js_src  = (string) @file_get_contents( get_template_directory() . '/assets/js/header.js' );
			// Require the real runtime, not just the class names: sticky must use
			// fixed positioning (CSS sticky cannot follow inside the template-part
			// wrapper), solid sticky must reserve header height, and static +
			// transparent states must exist. Avoids a false PASS if CSS is missing.
			$sticky_fixed   = (bool) preg_match( '/\.epiktetos-header-sticky\s+\.ts-header\s*\{[^}]*position:\s*fixed/i', $css_src );
			$offset_ok      = (bool) preg_match( '/epiktetos-header-sticky\.epiktetos-header-solid\s*\{[^}]*padding-top/i', $css_src );
			$static_ok      = false !== strpos( $css_src, '.epiktetos-header-static' );
			$transparent_ok = false !== strpos( $css_src, '.epiktetos-header-transparent' );
			$scrolled_ok    = false !== strpos( $css_src, '.ts-header.is-scrolled' );
			// A sticky header must stay visible: no hidden/translated scroll state.
			$no_hide        = false === strpos( $css_src, '.ts-header.is-hidden' ) && false === strpos( $js_src, 'is-hidden' );
			// Sticky sidebar offset must account for the fixed header height.
			$sidebar_ok     = (bool) preg_match( '/\.ts-home__aside\s*\{[^}]*--ts-header-h/i', $css_src );
			$js_ok          = false !== strpos( $js_src, 'epiktetos-header-sticky' );
			$state_wired    = $sticky_fixed && $offset_ok && $static_ok && $transparent_ok && $scrolled_ok && $no_hide && $sidebar_ok && $js_ok;
			$missing        = array();
			if ( ! $sticky_fixed )   { $missing[] = 'sticky=fixed'; }
			if ( ! $offset_ok )      { $missing[] = 'solid offset'; }
			if ( ! $static_ok )      { $missing[] = 'static'; }
			if ( ! $transparent_ok ) { $missing[] = 'transparent'; }
			if ( ! $scrolled_ok )    { $missing[] = 'scrolled state'; }
			if ( ! $no_hide )        { $missing[] = 'hide-on-scroll present'; }
			if ( ! $sidebar_ok )     { $missing[] = 'sidebar offset'; }
			if ( ! $js_ok )          { $missing[] = 'js guard'; }
			$checks[] = array(
				'group'  => 'theme',
				'label'  => __( 'Header state classes', 'epiktetos' ),
				'level'  => $state_wired ? 'ok' : 'warn',
				'detail' => $state_wired ? __( 'Sticky uses fixed positioning with flow compensation, stays visible (no hide-on-scroll), and the scrolled/transparent states plus the sticky sidebar offset are all wired.', 'epiktetos' ) : sprintf( __( 'Header state wiring incomplete (missing: %s).', 'epiktetos' ), implode( ', ', $missing ) ),
				'why'    => __( 'Sticky must use fixed positioning to follow scroll, stay visible at all times, and reserve its height so content is not hidden.', 'epiktetos' ),
				'fix'    => __( 'Drive header positioning from the body state classes: fixed sticky, header-height padding on solid sticky, and static/transparent variants.', 'epiktetos' ),
			);

			$hdr_src   = (string) @file_get_contents( get_template_directory() . '/inc/header/class-epiktetos-header.php' );
			$safe_scope = method_exists( 'Epiktetos_Header', 'transparent_active' ) && false !== strpos( $hdr_src, 'is_front_page' );
			$checks[] = array(
				'group'  => 'theme',
				'label'  => __( 'Transparent header scope', 'epiktetos' ),
				'level'  => $safe_scope ? 'ok' : 'warn',
				'detail' => $safe_scope ? __( 'Transparent header is limited to the front page for readability.', 'epiktetos' ) : __( 'Transparent header is not scoped to a safe route.', 'epiktetos' ),
				'why'    => __( 'Overlaying content routes risks unreadable text and review issues.', 'epiktetos' ),
				'fix'    => __( 'Gate the transparent header on is_front_page() so other routes stay solid.', 'epiktetos' ),
			);

			// --- HTTP-dependent checks (skipped cleanly if the site cannot
			//     reach its own URL, e.g. local loopback / Docker) ---
			$home     = wp_remote_get( home_url( '/' ), array( 'timeout' => 5, 'sslverify' => false ) );
			$html     = is_wp_error( $home ) ? '' : (string) wp_remote_retrieve_body( $home );
			$reachable = '' !== $html;

			if ( ! $reachable ) {
				if ( self::is_local_environment() ) {
					$checks[] = array( 'group' => 'system', 'label' => __( 'HTTP self-checks', 'epiktetos' ), 'level' => 'skip', 'detail' => __( 'Skipped (Local Docker Environment). The server cannot fetch its own loopback URL here, so REST, search, JSON-LD and Open Graph were not verified over HTTP. These run normally on a public host.', 'epiktetos' ) );
				} else {
					$checks[] = array( 'group' => 'system', 'label' => __( 'HTTP self-checks', 'epiktetos' ), 'level' => 'bad', 'detail' => __( 'The server could not fetch its own site over HTTP. REST, search, JSON-LD and Open Graph could not be verified.', 'epiktetos' ), 'why' => __( 'On a public host this usually means a real connectivity, TLS, or firewall problem.', 'epiktetos' ), 'fix' => __( 'Confirm the Site Address (URL) is reachable from the server and that loopback HTTP requests are allowed.', 'epiktetos' ) );
				}
			} else {
				$checks[] = array( 'group' => 'system', 'label' => __( 'REST API', 'epiktetos' ), 'level' => self::rest_ok() ? 'ok' : 'bad', 'detail' => self::rest_ok() ? __( 'Reachable.', 'epiktetos' ) : __( 'Not reachable.', 'epiktetos' ) );
				$search   = wp_remote_get( home_url( '/?s=test' ), array( 'timeout' => 4, 'sslverify' => false ) );
				$search_ok = ! is_wp_error( $search ) && 200 === (int) wp_remote_retrieve_response_code( $search );
				$checks[] = array( 'group' => 'system', 'label' => __( 'Search endpoint', 'epiktetos' ), 'level' => $search_ok ? 'ok' : 'warn', 'detail' => $search_ok ? __( 'Search responds 200.', 'epiktetos' ) : __( 'Search did not respond 200.', 'epiktetos' ) );
				$has_jsonld = false !== strpos( $html, 'application/ld+json' );
				$checks[] = array( 'group' => 'seo', 'label' => __( 'JSON-LD structured data', 'epiktetos' ), 'level' => $has_jsonld ? 'ok' : 'warn', 'detail' => $has_jsonld ? __( 'Present.', 'epiktetos' ) : __( 'No JSON-LD detected on the homepage.', 'epiktetos' ) );
					$has_og = false !== strpos( $html, 'property="og:' ) || false !== strpos( $html, "property='og:" );
					$checks[] = array( 'group' => 'seo', 'label' => __( 'Open Graph tags', 'epiktetos' ), 'level' => $has_og ? 'ok' : 'warn', 'detail' => $has_og ? __( 'Present.', 'epiktetos' ) : __( 'No Open Graph meta detected.', 'epiktetos' ) );
					$image_attrs = self::rendered_image_attribute_audit( $html );
					$checks[] = array(
						'group'  => 'performance',
						'label'  => __( 'Rendered image dimensions', 'epiktetos' ),
						'level'  => $image_attrs['missing_dimensions'] ? 'warn' : 'ok',
						'detail' => $image_attrs['missing_dimensions'] ? sprintf( __( '%d rendered images lack width or height attributes on the homepage.', 'epiktetos' ), $image_attrs['missing_dimensions'] ) : __( 'Homepage images include dimensions.', 'epiktetos' ),
						'why'    => __( 'Missing dimensions can cause layout shift while images load.', 'epiktetos' ),
						'fix'    => __( 'Use WordPress image functions or block image markup that outputs width and height attributes.', 'epiktetos' ),
					);
					$checks[] = array(
						'group'  => 'performance',
						'label'  => __( 'Rendered image loading hints', 'epiktetos' ),
						'level'  => ( $image_attrs['missing_loading'] || $image_attrs['missing_decoding'] ) ? 'warn' : 'ok',
						'detail' => ( $image_attrs['missing_loading'] || $image_attrs['missing_decoding'] ) ? sprintf( __( '%1$d images lack loading/fetchpriority and %2$d lack decoding.', 'epiktetos' ), $image_attrs['missing_loading'], $image_attrs['missing_decoding'] ) : __( 'Homepage images include loading/fetchpriority and decoding hints.', 'epiktetos' ),
						'why'    => __( 'Loading hints help browsers prioritize images without adding JavaScript.', 'epiktetos' ),
						'fix'    => __( 'Render images through WordPress image helpers or update custom image markup to include loading/decoding attributes.', 'epiktetos' ),
					);
				}

			// Site icon.
			$icon = has_site_icon() || ( class_exists( 'Epiktetos_Branding' ) && (int) Epiktetos_Branding::get( 'site_icon_id' ) );
			$checks[] = array( 'group' => 'branding', 'label' => __( 'Site icon', 'epiktetos' ), 'level' => $icon ? 'ok' : 'warn', 'detail' => $icon ? __( 'Configured.', 'epiktetos' ) : __( 'No favicon set — run the Setup Wizard.', 'epiktetos' ) );

			$reader_ready = class_exists( 'Epiktetos_Reader' ) && get_page_by_path( 'saved' );
			$checks[] = array( 'group' => 'reader', 'label' => __( 'Saved reader route', 'epiktetos' ), 'level' => $reader_ready ? 'ok' : 'warn', 'detail' => $reader_ready ? __( 'Reader tools and the Saved page are available.', 'epiktetos' ) : __( 'Reader class or Saved page is missing.', 'epiktetos' ) );

			$option_count = count( self::option_keys() );
			$checks[] = array( 'group' => 'theme', 'label' => __( 'Option families', 'epiktetos' ), 'level' => $option_count >= 10 ? 'ok' : 'warn', 'detail' => sprintf( __( '%d Epiktetos option groups are covered by export/import.', 'epiktetos' ), $option_count ), 'why' => __( 'Theme options should be predictable, grouped, and portable during local QA.', 'epiktetos' ), 'fix' => __( 'Add any missing Epiktetos option group to the export/import allowlist with a sanitizer.', 'epiktetos' ) );

			$import_sanitizer = is_callable( array( __CLASS__, 'sanitize_imported_option' ) );
			$checks[] = array( 'group' => 'security', 'label' => __( 'Import sanitization', 'epiktetos' ), 'level' => $import_sanitizer ? 'ok' : 'bad', 'detail' => $import_sanitizer ? __( 'Imported settings pass through registered theme sanitizers.', 'epiktetos' ) : __( 'Imported settings are not sanitized.', 'epiktetos' ), 'why' => __( 'Imported JSON is user-supplied data and must not bypass registered sanitizers.', 'epiktetos' ), 'fix' => __( 'Route every imported option through the same sanitizer used by its settings section.', 'epiktetos' ) );

			$actions_ok = false !== has_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
			$checks[] = array( 'group' => 'security', 'label' => __( 'Admin action router', 'epiktetos' ), 'level' => $actions_ok ? 'ok' : 'bad', 'detail' => $actions_ok ? __( 'Admin tools are routed through capability and nonce checks.', 'epiktetos' ) : __( 'Admin action router is not registered.', 'epiktetos' ) );

			$junks = self::package_junk_files();
			$checks[] = array( 'group' => 'security', 'label' => __( 'Package hygiene', 'epiktetos' ), 'level' => empty( $junks ) ? 'ok' : 'warn', 'detail' => empty( $junks ) ? __( 'No archives, backups, maps, logs, node_modules, vendor, or .DS_Store files found in the theme.', 'epiktetos' ) : sprintf( __( 'Potential package junk: %s', 'epiktetos' ), implode( ', ', array_slice( $junks, 0, 6 ) ) ) );

			// Theme Check plugin — run it when present; otherwise it is simply
			// not installed (reported as Skipped, never a false WARNING).
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$theme_check_available = ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'theme-check/theme-check.php' ) ) || function_exists( 'run_themechecks' ) || class_exists( 'ThemeCheckMain' );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Theme Check', 'epiktetos' ),
				'level'  => $theme_check_available ? 'ok' : 'skip',
				'detail' => $theme_check_available ? __( 'Theme Check plugin is active. Run Tools → Theme Check for the full automated report.', 'epiktetos' ) : __( 'Tool not installed — the Theme Check plugin is not active on this site.', 'epiktetos' ),
				'why'    => __( 'Theme Check reproduces many automated WordPress.org review tests.', 'epiktetos' ),
				'fix'    => __( 'Install and activate the Theme Check plugin, then run it before packaging.', 'epiktetos' ),
			);

			// WordPress Coding Standards — report whether a ruleset is bundled.
			$phpcs_ruleset = file_exists( get_template_directory() . '/phpcs.xml.dist' ) || file_exists( get_template_directory() . '/phpcs.xml' );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'WordPress Coding Standards', 'epiktetos' ),
				'level'  => $phpcs_ruleset ? 'ok' : 'skip',
				'detail' => $phpcs_ruleset ? __( 'A PHPCS ruleset (phpcs.xml.dist) is bundled. Run "phpcs" locally for the full report.', 'epiktetos' ) : __( 'Tool not installed — no PHPCS ruleset is bundled with the theme.', 'epiktetos' ),
				'why'    => __( 'WordPress Coding Standards catch escaping, sanitization, naming, and compatibility issues before review.', 'epiktetos' ),
				'fix'    => __( 'Add a phpcs.xml ruleset and run WordPressCS locally before submitting.', 'epiktetos' ),
			);

			// Screenshot: must exist, be a PNG, and be exactly 1200x900.
			$shot_path = get_template_directory() . '/screenshot.png';
			$shot_info = file_exists( $shot_path ) ? @getimagesize( $shot_path ) : false;
			$shot_ok   = is_array( $shot_info ) && IMAGETYPE_PNG === ( $shot_info[2] ?? 0 ) && 1200 === (int) $shot_info[0] && 900 === (int) $shot_info[1];
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Screenshot', 'epiktetos' ),
				'level'  => $shot_ok ? 'ok' : 'warn',
				'detail' => $shot_ok ? __( 'screenshot.png is a 1200x900 PNG.', 'epiktetos' ) : ( $shot_info ? sprintf( __( 'screenshot.png is %1$dx%2$d — WordPress.org expects a 1200x900 PNG.', 'epiktetos' ), (int) $shot_info[0], (int) $shot_info[1] ) : __( 'screenshot.png is missing.', 'epiktetos' ) ),
				'why'    => __( 'The repository shows screenshot.png on the theme page; it must be a real 1200x900 PNG of the theme.', 'epiktetos' ),
				'fix'    => __( 'Add a 1200x900 screenshot.png captured from the rendered homepage.', 'epiktetos' ),
			);
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Accessibility readiness', 'epiktetos' ),
				'level'  => ( $skip_ok && empty( $bad_tpl ) && ! $feat_no_alt ) ? 'ok' : 'warn',
				'detail' => ( $skip_ok && empty( $bad_tpl ) && ! $feat_no_alt ) ? __( 'Static skip-link, landmark, and featured-image alt checks pass.', 'epiktetos' ) : __( 'One or more static accessibility checks need review.', 'epiktetos' ),
				'why'    => __( 'Accessibility-ready claims require keyboard, focus, heading, form, and screen-reader review.', 'epiktetos' ),
				'fix'    => __( 'Resolve the accessibility warnings above and perform a manual keyboard/screen-reader pass before using the accessibility-ready tag.', 'epiktetos' ),
			);
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Translation readiness', 'epiktetos' ),
				'level'  => 'epiktetos' === wp_get_theme()->get( 'TextDomain' ) && is_dir( get_template_directory() . '/languages' ) ? 'ok' : 'warn',
				'detail' => 'epiktetos' === wp_get_theme()->get( 'TextDomain' ) && is_dir( get_template_directory() . '/languages' ) ? __( 'Text Domain is set and the languages directory exists.', 'epiktetos' ) : __( 'Text Domain or languages directory is missing.', 'epiktetos' ),
				'why'    => __( 'Repository themes must be translation-ready and use a consistent text domain.', 'epiktetos' ),
				'fix'    => __( 'Keep Text Domain: epiktetos in style.css, load the textdomain, and include a languages directory.', 'epiktetos' ),
			);
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Customizer compatibility', 'epiktetos' ),
				'level'  => file_exists( get_template_directory() . '/theme.json' ) ? 'ok' : 'warn',
				'detail' => file_exists( get_template_directory() . '/theme.json' ) ? __( 'Theme settings are exposed through block-theme configuration and admin settings, not Customizer-only controls.', 'epiktetos' ) : __( 'theme.json is missing.', 'epiktetos' ),
				'why'    => __( 'Block themes should remain usable through core WordPress editing surfaces.', 'epiktetos' ),
				'fix'    => __( 'Keep core site-editor support in theme.json and avoid Customizer-only configuration.', 'epiktetos' ),
			);
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'Block editor compatibility', 'epiktetos' ),
				'level'  => current_theme_supports( 'editor-styles' ) && current_theme_supports( 'align-wide' ) && file_exists( get_template_directory() . '/assets/css/editor.css' ) ? 'ok' : 'warn',
				'detail' => current_theme_supports( 'editor-styles' ) && current_theme_supports( 'align-wide' ) && file_exists( get_template_directory() . '/assets/css/editor.css' ) ? __( 'Editor styles and wide alignment support are registered.', 'epiktetos' ) : __( 'Editor styles or wide alignment support is incomplete.', 'epiktetos' ),
				'why'    => __( 'Authors need common core blocks to match the frontend closely in the editor.', 'epiktetos' ),
				'fix'    => __( 'Register editor styles, align-wide support, and keep editor.css aligned with core block patterns.', 'epiktetos' ),
			);
			$bundled_plugins = self::theme_contains_directories( array( 'plugins', 'mu-plugins' ) );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No bundled plugins', 'epiktetos' ),
				'level'  => empty( $bundled_plugins ) ? 'ok' : 'warn',
				'detail' => empty( $bundled_plugins ) ? __( 'No plugin directories were found inside the theme.', 'epiktetos' ) : sprintf( __( 'Plugin-like directories found: %s', 'epiktetos' ), implode( ', ', $bundled_plugins ) ),
				'why'    => __( 'Themes may recommend plugins, but should not bundle plugin functionality as separate packages.', 'epiktetos' ),
				'fix'    => __( 'Remove bundled plugins from the theme zip and document optional plugin recommendations separately.', 'epiktetos' ),
			);
			$forbidden = self::theme_forbidden_functions();
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No forbidden functions', 'epiktetos' ),
				'level'  => empty( $forbidden ) ? 'ok' : 'warn',
				'detail' => empty( $forbidden ) ? __( 'No eval/base64/command-execution functions were found in theme PHP or JS files.', 'epiktetos' ) : sprintf( __( 'Potentially forbidden calls found: %s', 'epiktetos' ), implode( ', ', array_slice( $forbidden, 0, 6 ) ) ),
				'why'    => __( 'Obfuscated or command-execution code is not appropriate for repository themes.', 'epiktetos' ),
				'fix'    => __( 'Remove the flagged calls or replace them with safe WordPress APIs.', 'epiktetos' ),
			);
			$filesystem_writes = self::theme_contains_patterns( array( 'file_put_contents\s*\(', 'fwrite\s*\(', 'unlink\s*\(', 'rmdir\s*\(', 'mkdir\s*\(' ), array( 'php' ) );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No unsafe filesystem usage', 'epiktetos' ),
				'level'  => empty( $filesystem_writes ) ? 'ok' : 'warn',
				'detail' => empty( $filesystem_writes ) ? __( 'No direct filesystem write/delete calls were found in theme PHP files.', 'epiktetos' ) : sprintf( __( 'Direct filesystem calls found: %s', 'epiktetos' ), implode( ', ', array_slice( $filesystem_writes, 0, 6 ) ) ),
				'why'    => __( 'Repository themes should use WordPress APIs and avoid direct filesystem writes.', 'epiktetos' ),
				'fix'    => __( 'Replace direct filesystem writes with WordPress APIs or WP_Filesystem where appropriate.', 'epiktetos' ),
			);
			$remote_assets = self::theme_contains_patterns( array( 'wp_enqueue_(style|script)[\s\S]{0,220}https?://', '@import\s+url\(["\']?https?://', '\bfetch\s*\(\s*["\']https?://', '\bsendBeacon\s*\(' ), array( 'php', 'css', 'js' ) );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No remote tracking', 'epiktetos' ),
				'level'  => empty( $remote_assets ) ? 'ok' : 'warn',
				'detail' => empty( $remote_assets ) ? __( 'No third-party remote asset or tracking URLs were found in theme code.', 'epiktetos' ) : sprintf( __( 'Remote URLs found: %s', 'epiktetos' ), implode( ', ', array_slice( $remote_assets, 0, 6 ) ) ),
				'why'    => __( 'Remote resources must be reviewable and should not track visitors without consent.', 'epiktetos' ),
				'fix'    => __( 'Bundle all assets locally. Fonts are already self-hosted, so no external font or tracking requests should remain.', 'epiktetos' ),
			);
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No obfuscated code', 'epiktetos' ),
				'level'  => empty( $forbidden ) ? 'ok' : 'warn',
				'detail' => empty( $forbidden ) ? __( 'No common obfuscation signals were found.', 'epiktetos' ) : __( 'Potential obfuscation or execution calls need review.', 'epiktetos' ),
				'why'    => __( 'Theme reviewers must be able to inspect all shipped code.', 'epiktetos' ),
				'fix'    => __( 'Remove obfuscation and keep all source code readable in the submitted zip.', 'epiktetos' ),
			);
			$debug_logs = self::theme_contains_patterns( array( 'PHP (Notice|Warning|Fatal error)' ), array( 'log' ) );
			$checks[] = array(
				'group'  => 'wordpressorg',
				'label'  => __( 'No PHP notices', 'epiktetos' ),
				'level'  => empty( $debug_logs ) ? 'ok' : 'warn',
				'detail' => empty( $debug_logs ) ? __( 'No debug log with PHP notices was found inside the theme directory.', 'epiktetos' ) : sprintf( __( 'Debug log entries found: %s', 'epiktetos' ), implode( ', ', array_slice( $debug_logs, 0, 6 ) ) ),
				'why'    => __( 'PHP notices during review can block approval.', 'epiktetos' ),
				'fix'    => __( 'Run the theme with WP_DEBUG enabled and fix any notices before packaging.', 'epiktetos' ),
			);

			$required_templates = array( 'index.html', 'front-page.html', 'single.html', 'archive.html', 'category.html', 'tag.html', 'search.html', '404.html', 'page-about.html', 'page-contact.html', 'page-topics.html', 'page-saved.html' );
			$missing_templates = array();
			foreach ( $required_templates as $tpl ) {
				if ( ! file_exists( get_template_directory() . '/templates/' . $tpl ) ) {
					$missing_templates[] = $tpl;
				}
			}
			$checks[] = array( 'group' => 'theme', 'label' => __( 'Template coverage', 'epiktetos' ), 'level' => empty( $missing_templates ) ? 'ok' : 'warn', 'detail' => empty( $missing_templates ) ? __( 'Core publication templates are present.', 'epiktetos' ) : sprintf( __( 'Missing templates: %s', 'epiktetos' ), implode( ', ', $missing_templates ) ), 'why' => __( 'Block themes need clear template coverage for common publishing routes.', 'epiktetos' ), 'fix' => __( 'Add the missing block template file or confirm that another template intentionally handles that route.', 'epiktetos' ) );

				return $checks;
			}

			protected static function theme_file_size( $relative ) {
				$path = trailingslashit( get_template_directory() ) . ltrim( $relative, '/' );
				return file_exists( $path ) ? (int) filesize( $path ) : 0;
			}

			protected static function rendered_image_attribute_audit( $html ) {
				$result = array(
					'missing_dimensions' => 0,
					'missing_loading'    => 0,
					'missing_decoding'   => 0,
				);
				if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $matches ) ) {
					return $result;
				}
				foreach ( $matches[0] as $tag ) {
					if ( ! preg_match( '/\swidth=(["\'])?\d+/i', $tag ) || ! preg_match( '/\sheight=(["\'])?\d+/i', $tag ) ) {
						$result['missing_dimensions']++;
					}
					if ( ! preg_match( '/\sloading=/i', $tag ) && ! preg_match( '/\sfetchpriority=/i', $tag ) ) {
						$result['missing_loading']++;
					}
					if ( ! preg_match( '/\sdecoding=/i', $tag ) ) {
						$result['missing_decoding']++;
					}
				}
				return $result;
			}

			protected static function package_junk_files() {
				$root = trailingslashit( get_template_directory() );
				$found = array();
				$bad_extensions = array( 'zip', 'sql', 'log', 'bak', 'tmp', 'map' );
				$bad_dirs = array( 'node_modules', 'vendor' );
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
				);
				foreach ( $iterator as $file ) {
					$name = $file->getFilename();
					$relative = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
					if ( '.DS_Store' === $name || in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), $bad_extensions, true ) ) {
						$found[] = $relative;
						continue;
					}
					foreach ( $bad_dirs as $dir ) {
						if ( false !== strpos( $relative, $dir . '/' ) ) {
							$found[] = $dir . '/';
							continue 2;
						}
					}
				}
				return array_values( array_unique( $found ) );
			}

			protected static function theme_contains_directories( $names ) {
				$root  = trailingslashit( get_template_directory() );
				$found = array();
				foreach ( (array) $names as $name ) {
					if ( is_dir( $root . $name ) ) {
						$found[] = $name . '/';
					}
				}
				return $found;
			}

			protected static function theme_forbidden_functions() {
				return self::theme_contains_patterns(
					array(
						'\beval\s*\(',
						'\bbase64_decode\s*\(',
						'\bshell_exec\s*\(',
						'\bexec\s*\(',
						'\bsystem\s*\(',
						'\bpassthru\s*\(',
						'\bpopen\s*\(',
						'\bproc_open\s*\(',
					),
					array( 'php', 'js' )
				);
			}

			protected static function theme_contains_patterns( $patterns, $extensions ) {
				$root  = trailingslashit( get_template_directory() );
				$found = array();
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
				);
				foreach ( $iterator as $file ) {
					if ( ! $file->isFile() ) {
						continue;
					}
					$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
					if ( ! in_array( $ext, $extensions, true ) ) {
						continue;
					}
					$relative = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
					$content  = (string) file_get_contents( $file->getPathname() );
					foreach ( (array) $patterns as $pattern ) {
						if ( preg_match( '~' . $pattern . '~i', $content ) ) {
							$found[] = $relative;
							continue 2;
						}
					}
				}
				return array_values( array_unique( $found ) );
			}

			/* ============================================================
		   Layout helpers
		   ============================================================ */

		protected static function open( $title, $sub = '' ) {
			echo '<div class="wrap epi-admin epi-admin-page">';
			echo self::notice(); // phpcs:ignore
			echo '<header class="epi-admin__hero"><div><h2 class="epi-admin__title">' . esc_html( $title ) . '</h2>';
			if ( $sub ) {
				echo '<p class="epi-admin__sub">' . esc_html( $sub ) . '</p>';
			}
			echo '</div></header>';
			echo '<nav class="epi-subnav" aria-label="' . esc_attr__( 'Epiktetos sections', 'epiktetos' ) . '">' . self::tabs() . '</nav>';
		}

		protected static function close() {
			echo '</div>';
		}

		protected static function tabs() {
			// These auxiliary screens are folded into the unified Appearance →
			// Epiktetos tabs; offer a single link back to that hub.
			$url = admin_url( 'themes.php?page=epiktetos-settings' );
			return '<a class="epi-subnav__link" href="' . esc_url( $url ) . '">&larr; ' . esc_html__( 'Epiktetos settings', 'epiktetos' ) . '</a>';
		}

		protected static function action_button( $action, $label, $variant = '', $confirm = '' ) {
			$class = 'button' . ( 'primary' === $variant ? ' button-primary' : '' );
			echo '<form method="post" class="epi-inline">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="epiktetos_action" value="' . esc_attr( $action ) . '" />';
			echo '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-epi-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button>';
			echo '</form>';
		}
	}

	Epiktetos_Admin::init();
}
