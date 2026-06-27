<?php
/**
 * Epiktetos admin settings control center.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Settings' ) ) {

	class Epiktetos_Settings {

		const HOOK_SUFFIX_KEY = 'epiktetos_settings_hook';

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'admin_post_epiktetos_clear_cache', array( __CLASS__, 'handle_clear_cache' ) );
			add_action( 'admin_post_epiktetos_regenerate_intelligence', array( __CLASS__, 'handle_regenerate_intelligence' ) );
			add_action( 'admin_post_epiktetos_import_settings', array( __CLASS__, 'handle_import_settings' ) );
		}

		public static function register_page() {
			// Registered by Epiktetos_Admin as a top-level submenu.
		}

		public static function enqueue( $hook ) {
			if ( empty( $GLOBALS[ self::HOOK_SUFFIX_KEY ] ) || $hook !== $GLOBALS[ self::HOOK_SUFFIX_KEY ] ) {
				return;
			}
			wp_enqueue_style(
				'epiktetos-admin',
				get_template_directory_uri() . '/assets/css/admin.css',
				array(),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/css/admin.css' ) : null
			);
			wp_enqueue_script(
				'epiktetos-admin',
				get_template_directory_uri() . '/assets/js/admin.js',
				array(),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/js/admin.js' ) : null,
				true
			);
			wp_enqueue_media();
		}

		public static function render_page() {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return;
			}

			$version = defined( 'EPIKTETOS_VERSION' ) ? EPIKTETOS_VERSION : wp_get_theme()->get( 'Version' );
			$tabs    = self::tabs();
			$active  = 'general';
			?>
			<div class="wrap epi-admin" data-epi-admin>
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Epiktetos', 'epiktetos' ); ?></h1>
				<?php if ( $version ) : ?><span class="epi-admin__version">v<?php echo esc_html( $version ); ?></span><?php endif; ?>
				<hr class="wp-header-end" />

				<?php
				self::render_notice();
				if ( class_exists( 'Epiktetos_Admin' ) && method_exists( 'Epiktetos_Admin', 'notice' ) ) {
					echo Epiktetos_Admin::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped notice markup.
				}
				?>

				<nav class="epi-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Epiktetos settings sections', 'epiktetos' ); ?>">
					<?php foreach ( $tabs as $slug => $tab ) : ?>
						<button type="button" role="tab" class="epi-tabs__button<?php echo $slug === $active ? ' is-active' : ''; ?>" data-epi-tab="<?php echo esc_attr( $slug ); ?>" id="epi-tab-button-<?php echo esc_attr( $slug ); ?>" aria-controls="epi-tab-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>">
							<?php echo esc_html( $tab['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</nav>

				<div class="epi-panels">
					<?php
					// Render panels in tab order. The contiguous run of settings
					// tabs is wrapped in a single options.php form; custom tabs
					// (general, sample, system, about-theme, validator) render
					// outside the form because they have their own forms/links.
					$settings_open = false;
					foreach ( $tabs as $slug => $tab ) {
						$is_settings = ! empty( $tab['sections'] );
						if ( $is_settings && ! $settings_open ) {
							echo '<form action="options.php" method="post" id="epi-settings-form" class="epi-settings-form">';
							settings_fields( 'epiktetos_settings' );
							$settings_open = true;
						} elseif ( ! $is_settings && $settings_open ) {
							echo '<div class="epi-admin__submit" data-epi-submit hidden>';
							submit_button( __( 'Save changes', 'epiktetos' ), 'primary', 'submit', false );
							echo '</div></form>';
							$settings_open = false;
						}

						$is_active = ( $slug === $active );
						printf(
							'<section class="epi-panel%1$s" role="tabpanel" id="epi-tab-%2$s" data-epi-panel="%2$s" aria-labelledby="epi-tab-button-%2$s"%3$s>',
							$is_active ? ' is-active' : '',
							esc_attr( $slug ),
							$is_active ? '' : ' hidden'
						);
						if ( $is_settings ) {
							self::render_tab_intro( $tab );
							self::render_sections( 'epiktetos-settings', $tab['sections'] );
						} else {
							self::render_custom_panel( isset( $tab['kind'] ) ? $tab['kind'] : '', $version );
						}
						echo '</section>';
					}
					if ( $settings_open ) {
						echo '<div class="epi-admin__submit" data-epi-submit hidden>';
						submit_button( __( 'Save changes', 'epiktetos' ), 'primary', 'submit', false );
						echo '</div></form>';
					}
					?>
				</div>
			</div>
			<?php
		}

		/** Render a non-settings (custom) tab panel by kind. */
		protected static function render_custom_panel( $kind, $version ) {
			switch ( $kind ) {
				case 'overview':
					self::render_overview( $version );
					break;
				case 'sample':
					echo '<div class="epi-tab-intro"><h2>' . esc_html__( 'Sample content', 'epiktetos' ) . '</h2><p>' . esc_html__( 'Create or remove local example posts so you can preview the editorial layouts.', 'epiktetos' ) . '</p></div>';
					if ( class_exists( 'Epiktetos_Admin' ) ) {
						Epiktetos_Admin::render_sample_content_panel();
					}
					break;
				case 'advanced':
					self::render_advanced();
					break;
				case 'about':
					self::render_about_theme( $version );
					break;
				case 'validator':
					echo '<div class="epi-tab-intro"><h2>' . esc_html__( 'Theme health', 'epiktetos' ) . '</h2><p>' . esc_html__( 'A plain-language check of content, accessibility, and repository readiness.', 'epiktetos' ) . '</p></div>';
					if ( class_exists( 'Epiktetos_Admin' ) ) {
						Epiktetos_Admin::render_validator_panel();
					}
					break;
			}
		}

		protected static function tabs() {
			return array(
				'general'     => array(
					'label' => __( 'General', 'epiktetos' ),
					'kind'  => 'overview',
				),
				'branding'    => array(
					'label'    => __( 'Branding', 'epiktetos' ),
					'title'    => __( 'Branding assets', 'epiktetos' ),
					'desc'     => __( 'Manage uploaded identity assets for logos, browser icons, and social cards.', 'epiktetos' ),
					'sections' => array( 'epiktetos_branding_identity', 'epiktetos_branding_logos', 'epiktetos_branding_icons' ),
				),
				'header'      => array(
					'label'    => __( 'Header', 'epiktetos' ),
					'title'    => __( 'Header', 'epiktetos' ),
					'desc'     => __( 'Configure the sticky header, transparent header, RSS visibility, default theme mode, and header logo size.', 'epiktetos' ),
					'sections' => array( 'epiktetos_header' ),
				),
				'footer'      => array(
					'label'    => __( 'Footer', 'epiktetos' ),
					'title'    => __( 'Footer', 'epiktetos' ),
					'desc'     => __( 'Manage the colophon, newsletter copy, RSS visibility, social URLs, and footer credit.', 'epiktetos' ),
					'sections' => array( 'epiktetos_footer' ),
				),
				'reader'      => array(
					'label'    => __( 'Reader', 'epiktetos' ),
					'title'    => __( 'Reader features', 'epiktetos' ),
					'desc'     => __( 'Local reading history, read later, quote copy, image zoom, publication stats, and editor picks.', 'epiktetos' ),
					'sections' => array( 'epiktetos_reader_features', 'epiktetos_editor_picks' ),
				),
				'editorial'   => array(
					'label'    => __( 'Editorial', 'epiktetos' ),
					'title'    => __( 'Editorial', 'epiktetos' ),
					'desc'     => __( 'Single-post reading aids, discussion, category order, topics, and the About page manifesto.', 'epiktetos' ),
					'sections' => array( 'epiktetos_single', 'epiktetos_discussion', 'epiktetos_category_order', 'epiktetos_taxonomies', 'epiktetos_about' ),
				),
				'seo'         => array(
					'label'    => __( 'SEO', 'epiktetos' ),
					'title'    => __( 'SEO and metadata', 'epiktetos' ),
					'desc'     => __( 'Control publication metadata, social cards, JSON-LD, Open Graph, and canonical URLs.', 'epiktetos' ),
					'sections' => array( 'epiktetos_seo_metadata', 'epiktetos_seo_social', 'epiktetos_seo_technical' ),
				),
				'sample'      => array(
					'label' => __( 'Sample Content', 'epiktetos' ),
					'kind'  => 'sample',
				),
				'system'      => array(
					'label' => __( 'System', 'epiktetos' ),
					'kind'  => 'advanced',
				),
				'about-theme' => array(
					'label' => __( 'About', 'epiktetos' ),
					'kind'  => 'about',
				),
				'validator'   => array(
					'label' => __( 'Theme health', 'epiktetos' ),
					'kind'  => 'validator',
				),
			);
		}

		protected static function render_overview( $version ) {
			$theme = wp_get_theme();
			$counts = array(
				__( 'Posts', 'epiktetos' )      => wp_count_posts( 'post' )->publish,
				__( 'Pages', 'epiktetos' )      => wp_count_posts( 'page' )->publish,
				__( 'Categories', 'epiktetos' ) => self::term_count( 'category' ),
				__( 'Tags', 'epiktetos' )       => self::term_count( 'post_tag' ),
				__( 'Comments', 'epiktetos' )   => wp_count_comments()->approved,
			);
			$mode = class_exists( 'Epiktetos_Header' ) ? Epiktetos_Header::get( 'default_theme_mode' ) : 'light';
			$cache = false !== get_transient( 'epiktetos_cat_showcase' ) ? __( 'Warm', 'epiktetos' ) : __( 'Cold / empty', 'epiktetos' );
			$about = get_page_by_path( 'about' );
			$topics = get_page_by_path( 'topics' );
			$quick = array(
				__( 'View Site', 'epiktetos' )  => home_url( '/' ),
				__( 'Customize', 'epiktetos' )  => admin_url( 'customize.php' ),
				__( 'Edit Site', 'epiktetos' )  => admin_url( 'site-editor.php' ),
				__( 'About Page', 'epiktetos' ) => $about ? get_permalink( $about ) : home_url( '/about/' ),
				__( 'Topics Page', 'epiktetos' ) => $topics ? get_permalink( $topics ) : home_url( '/topics/' ),
				__( 'Search Page', 'epiktetos' ) => home_url( '/?s=' ),
			);
			?>
			<div class="epi-overview">
				<div class="epi-overview__main epi-card">
					<p class="epi-kicker"><?php esc_html_e( 'Epiktetos', 'epiktetos' ); ?></p>
					<h2><?php echo esc_html( $theme->get( 'Name' ) ); ?></h2>
					<p><?php esc_html_e( 'A quiet editorial publication system for long-form reading, discovery, metadata, and discussion.', 'epiktetos' ); ?></p>
					<dl class="epi-meta-grid">
						<div><dt><?php esc_html_e( 'Version', 'epiktetos' ); ?></dt><dd><?php echo esc_html( $version ? $version : $theme->get( 'Version' ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Active theme', 'epiktetos' ); ?></dt><dd><?php echo esc_html( $theme->get_stylesheet() ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Current mode', 'epiktetos' ); ?></dt><dd><?php echo esc_html( ucfirst( (string) $mode ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Cache status', 'epiktetos' ); ?></dt><dd><?php echo esc_html( $cache ); ?></dd></div>
					</dl>
				</div>
				<div class="epi-card">
					<h2><?php esc_html_e( 'System summary', 'epiktetos' ); ?></h2>
					<div class="epi-counts">
						<?php foreach ( $counts as $label => $count ) : ?>
							<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></strong></div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="epi-card">
					<h2><?php esc_html_e( 'Quick links', 'epiktetos' ); ?></h2>
					<div class="epi-quick-links">
						<?php foreach ( $quick as $label => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php
		}

		protected static function render_tab_intro( $tab ) {
			echo '<div class="epi-tab-intro">';
			echo '<h2>' . esc_html( $tab['title'] ) . '</h2>';
			echo '<p>' . esc_html( $tab['desc'] ) . '</p>';
			echo '</div>';
		}

		protected static function term_count( $taxonomy ) {
			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			return is_wp_error( $count ) ? 0 : (int) $count;
		}

		protected static function render_future_state( $tab ) {
			echo '<div class="epi-card epi-empty-state">';
			echo '<h2>' . esc_html__( 'Future-ready', 'epiktetos' ) . '</h2>';
			echo '<p>' . esc_html( $tab['desc'] ) . '</p>';
			echo '</div>';
		}

		protected static function render_sections( $page, $section_ids ) {
			global $wp_settings_sections, $wp_settings_fields;
			if ( ! isset( $wp_settings_sections[ $page ] ) ) {
				return;
			}
			foreach ( $section_ids as $id ) {
				if ( empty( $wp_settings_sections[ $page ][ $id ] ) ) {
					continue;
				}
				$section = $wp_settings_sections[ $page ][ $id ];
				echo '<div class="epi-section">';
				if ( $section['title'] ) {
					echo '<h3>' . esc_html( $section['title'] ) . '</h3>';
				}
				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}
				if ( isset( $wp_settings_fields[ $page ][ $id ] ) ) {
					echo '<table class="form-table" role="presentation">';
					do_settings_fields( $page, $id );
					echo '</table>';
				}
				echo '</div>';
			}
		}

		protected static function render_about_theme( $version ) {
			$theme = wp_get_theme();
			$rows  = array(
				__( 'Version', 'epiktetos' )            => $version ? $version : $theme->get( 'Version' ),
				__( 'Requires WordPress', 'epiktetos' ) => $theme->get( 'RequiresWP' ) ? $theme->get( 'RequiresWP' ) : '6.5',
				__( 'Tested up to', 'epiktetos' )       => '7.0',
				__( 'Requires PHP', 'epiktetos' )       => $theme->get( 'RequiresPHP' ) ? $theme->get( 'RequiresPHP' ) : '8.0',
				__( 'License', 'epiktetos' )            => $theme->get( 'License' ) ? $theme->get( 'License' ) : 'GNU General Public License v2 or later',
				__( 'Text Domain', 'epiktetos' )        => $theme->get( 'TextDomain' ) ? $theme->get( 'TextDomain' ) : 'epiktetos',
			);
			$theme_uri = $theme->get( 'ThemeURI' );
			if ( $theme_uri ) {
				$rows[ __( 'Theme URI', 'epiktetos' ) ] = $theme_uri;
			}
			?>
			<div class="epi-tab-intro"><h2><?php esc_html_e( 'About Epiktetos', 'epiktetos' ); ?></h2><p><?php esc_html_e( 'Version, compatibility, license, and the resources bundled with the theme.', 'epiktetos' ); ?></p></div>
			<div class="epi-card">
				<table class="form-table" role="presentation">
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</div>
			<div class="epi-card">
				<h3><?php esc_html_e( 'Bundled fonts', 'epiktetos' ); ?></h3>
				<p class="epi-muted"><?php esc_html_e( 'Inter and Libre Baskerville are self-hosted (no external requests), licensed under the SIL Open Font License 1.1. Full text: assets/fonts/LICENSE.md.', 'epiktetos' ); ?></p>
				<h3><?php esc_html_e( 'Bundled assets', 'epiktetos' ); ?></h3>
				<p class="epi-muted"><?php esc_html_e( 'The logo marks and interface icons in assets/svg, assets/icons, and assets/brand are original works for this theme. See readme.txt for the full resource list.', 'epiktetos' ); ?></p>
			</div>
			<div class="epi-card">
				<h3><?php esc_html_e( 'Project links', 'epiktetos' ); ?></h3>
				<p>
					<a class="button" href="https://docs.mcorucu.com/epiktetos-theme/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'epiktetos' ); ?></a>
					<a class="button" href="https://github.com/mcorucu/epiktetos-wordpress-theme" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub repository', 'epiktetos' ); ?></a>
					<a class="button" href="https://github.com/mcorucu/epiktetos-wordpress-theme/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an issue', 'epiktetos' ); ?></a>
				</p>
			</div>
			<?php
		}

		protected static function render_advanced() {
			echo '<div class="epi-tab-intro"><h2>' . esc_html__( 'System', 'epiktetos' ) . '</h2><p>' . esc_html__( 'Environment details and safe maintenance tools — clear theme caches, refresh editorial metadata, and export or import settings.', 'epiktetos' ) . '</p></div>';
			$export = self::export_json();
			?>
			<div class="epi-advanced-grid">
				<?php self::render_system_info(); ?>
				<div class="epi-section">
					<h3><?php esc_html_e( 'Cache', 'epiktetos' ); ?></h3>
					<p><?php esc_html_e( 'Clear Epiktetos transients without touching posts, settings, or uploaded media.', 'epiktetos' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="epiktetos_clear_cache" />
						<?php wp_nonce_field( 'epiktetos_clear_cache' ); ?>
						<button type="submit" class="button button-secondary" data-epi-confirm="<?php esc_attr_e( 'Clear Epiktetos transient cache?', 'epiktetos' ); ?>"><?php esc_html_e( 'Clear transient cache', 'epiktetos' ); ?></button>
					</form>
				</div>
				<div class="epi-section">
					<h3><?php esc_html_e( 'Editorial intelligence', 'epiktetos' ); ?></h3>
					<p><?php esc_html_e( 'Refresh word count, reading time, heading count, and reading-level metadata for published posts.', 'epiktetos' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="epiktetos_regenerate_intelligence" />
						<?php wp_nonce_field( 'epiktetos_regenerate_intelligence' ); ?>
						<button type="submit" class="button button-secondary" data-epi-confirm="<?php esc_attr_e( 'Regenerate editorial intelligence metadata for published posts?', 'epiktetos' ); ?>"><?php esc_html_e( 'Regenerate metadata', 'epiktetos' ); ?></button>
					</form>
				</div>
				<div class="epi-section epi-section--wide">
					<h3><?php esc_html_e( 'Export settings JSON', 'epiktetos' ); ?></h3>
					<p><?php esc_html_e( 'Export contains Epiktetos option families only. It is read-only and does not include content.', 'epiktetos' ); ?></p>
					<textarea class="epi-export" readonly data-epi-export><?php echo esc_textarea( $export ); ?></textarea>
					<div class="epi-tools">
						<button type="button" class="button button-secondary" data-epi-copy-export><?php esc_html_e( 'Copy JSON', 'epiktetos' ); ?></button>
						<button type="button" class="button button-secondary" data-epi-download-export data-filename="epiktetos-settings.json"><?php esc_html_e( 'Download JSON', 'epiktetos' ); ?></button>
						<span class="epi-export-status" data-epi-export-status aria-live="polite"></span>
					</div>
				</div>
				<div class="epi-section epi-section--wide">
					<h3><?php esc_html_e( 'Import settings JSON', 'epiktetos' ); ?></h3>
					<p><?php esc_html_e( 'Paste a JSON export from this theme. Only known Epiktetos option families are imported.', 'epiktetos' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="epiktetos_import_settings" />
						<?php wp_nonce_field( 'epiktetos_import_settings' ); ?>
						<textarea class="epi-export" name="settings_json" spellcheck="false" aria-label="<?php esc_attr_e( 'Settings JSON', 'epiktetos' ); ?>"></textarea>
						<div class="epi-tools">
							<button type="submit" class="button button-secondary" data-epi-confirm="<?php esc_attr_e( 'Import Epiktetos settings from this JSON?', 'epiktetos' ); ?>"><?php esc_html_e( 'Import JSON', 'epiktetos' ); ?></button>
						</div>
					</form>
				</div>
			</div>
			<?php
		}

		protected static function render_system_info() {
			global $wpdb;
			$reader = class_exists( 'Epiktetos_Reader' ) ? Epiktetos_Reader::client_settings() : array();
			$reader_enabled = array();
			foreach ( $reader as $key => $value ) {
				if ( true === $value ) {
					$reader_enabled[] = $key;
				}
			}
			$info = array(
				__( 'Theme Version', 'epiktetos' )           => defined( 'EPIKTETOS_VERSION' ) ? EPIKTETOS_VERSION : wp_get_theme()->get( 'Version' ),
				__( 'WordPress Version', 'epiktetos' )       => get_bloginfo( 'version' ),
				__( 'PHP Version', 'epiktetos' )             => PHP_VERSION,
				__( 'MySQL Version', 'epiktetos' )           => $wpdb->db_version(),
				__( 'Memory Limit', 'epiktetos' )            => WP_MEMORY_LIMIT,
				__( 'Upload Limit', 'epiktetos' )            => size_format( wp_max_upload_size() ),
				__( 'Active Theme', 'epiktetos' )            => wp_get_theme()->get_stylesheet(),
				__( 'Dark Mode Enabled', 'epiktetos' )       => class_exists( 'Epiktetos_Header' ) && 'dark' === Epiktetos_Header::get( 'default_theme_mode' ) ? __( 'Yes', 'epiktetos' ) : __( 'Available', 'epiktetos' ),
				__( 'Reader Features Enabled', 'epiktetos' ) => ! empty( $reader_enabled ) ? implode( ', ', $reader_enabled ) : __( 'None', 'epiktetos' ),
				__( 'Total Posts', 'epiktetos' )             => number_format_i18n( (int) wp_count_posts( 'post' )->publish ),
				__( 'Categories', 'epiktetos' )              => number_format_i18n( self::term_count( 'category' ) ),
				__( 'Tags', 'epiktetos' )                    => number_format_i18n( self::term_count( 'post_tag' ) ),
				__( 'Pages', 'epiktetos' )                   => number_format_i18n( (int) wp_count_posts( 'page' )->publish ),
				__( 'Comments', 'epiktetos' )                => number_format_i18n( (int) wp_count_comments()->approved ),
			);
			echo '<div class="epi-section epi-section--wide epi-system-info">';
			echo '<h3>' . esc_html__( 'System information', 'epiktetos' ) . '</h3>';
			echo '<dl>';
			foreach ( $info as $label => $value ) {
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
			}
			echo '</dl>';
			echo '</div>';
		}

		protected static function export_json() {
			$data = array();
			foreach ( self::option_names() as $name ) {
				$data[ $name ] = get_option( $name );
			}
			return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		protected static function option_names() {
			return array(
				'epiktetos_options',
				'epiktetos_branding_options',
				'epiktetos_footer_options',
				'epiktetos_single_options',
				'epiktetos_discussion_options',
				'epiktetos_taxonomy_options',
				'epiktetos_seo_options',
				'epiktetos_category_order',
				'epiktetos_about_options',
				'epiktetos_reader_options',
			);
		}

		public static function handle_clear_cache() {
			self::verify_action( 'epiktetos_clear_cache' );
			if ( class_exists( 'Epiktetos_Categories' ) && method_exists( 'Epiktetos_Categories', 'flush_cache' ) ) {
				Epiktetos_Categories::flush_cache();
			}
			delete_transient( 'epiktetos_cat_showcase' );
			delete_transient( 'epiktetos_editor_picks' );
			delete_transient( 'epiktetos_publication_stats' );
			self::redirect_with_notice( 'cache-cleared' );
		}

		public static function handle_import_settings() {
			self::verify_action( 'epiktetos_import_settings' );
			$raw = isset( $_POST['settings_json'] ) ? wp_unslash( $_POST['settings_json'] ) : '';
			if ( strlen( (string) $raw ) > 1048576 ) {
				self::redirect_with_notice( 'import-failed' );
			}
			$data = json_decode( (string) $raw, true );
			if ( ! is_array( $data ) ) {
				self::redirect_with_notice( 'import-failed' );
			}
			if ( isset( $data['_meta']['theme'] ) && 'epiktetos' !== sanitize_key( $data['_meta']['theme'] ) ) {
				self::redirect_with_notice( 'import-failed' );
			}
			$imported = 0;
			foreach ( self::option_names() as $name ) {
				if ( array_key_exists( $name, $data ) ) {
					$value = class_exists( 'Epiktetos_Admin' ) && method_exists( 'Epiktetos_Admin', 'sanitize_imported_option' )
						? Epiktetos_Admin::sanitize_imported_option( $name, $data[ $name ] )
						: $data[ $name ];
					update_option( $name, $value );
					$imported++;
				}
			}
			self::redirect_with_notice( 'settings-imported', array( 'count' => $imported ) );
		}

		public static function handle_regenerate_intelligence() {
			self::verify_action( 'epiktetos_regenerate_intelligence' );
			$posts = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				)
			);
			$count = 0;
			if ( class_exists( 'Epiktetos_SEO' ) && method_exists( 'Epiktetos_SEO', 'store_post_intelligence' ) ) {
				foreach ( $posts as $post ) {
					delete_post_meta( $post->ID, '_epiktetos_intelligence_hash' );
					Epiktetos_SEO::store_post_intelligence( $post->ID, $post, true );
					$count++;
				}
			}
			self::redirect_with_notice( 'intelligence-regenerated', array( 'count' => $count ) );
		}

		protected static function verify_action( $action ) {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Epiktetos settings.', 'epiktetos' ) );
			}
			check_admin_referer( $action );
		}

		protected static function redirect_with_notice( $notice, $args = array() ) {
			$url = add_query_arg( array_merge( array( 'page' => 'epiktetos-settings', 'epi_notice' => $notice ), $args ), admin_url( 'themes.php' ) );
			wp_safe_redirect( $url );
			exit;
		}

		protected static function render_notice() {
			if ( empty( $_GET['epi_notice'] ) ) {
				return;
			}
			$notice = sanitize_key( wp_unslash( $_GET['epi_notice'] ) );
			$message = '';
			$type    = 'success';
			if ( 'cache-cleared' === $notice ) {
				$message = __( 'Epiktetos transient cache cleared.', 'epiktetos' );
			}
			if ( 'intelligence-regenerated' === $notice ) {
				$count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
				$message = sprintf( _n( 'Editorial intelligence regenerated for %d post.', 'Editorial intelligence regenerated for %d posts.', $count, 'epiktetos' ), $count );
			}
			if ( 'settings-imported' === $notice ) {
				$count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
				$message = sprintf( _n( 'Imported %d Epiktetos option family.', 'Imported %d Epiktetos option families.', $count, 'epiktetos' ), $count );
			}
			if ( 'import-failed' === $notice ) {
				$message = __( 'Settings import failed. Please paste valid JSON.', 'epiktetos' );
				$type    = 'error';
			}
			if ( $message ) {
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible epi-notice"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
	}

	Epiktetos_Settings::init();
}
