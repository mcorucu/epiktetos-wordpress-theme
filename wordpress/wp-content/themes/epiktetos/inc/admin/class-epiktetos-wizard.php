<?php
/**
 * Epiktetos — first-run setup wizard.
 *
 * Offered after activation via a dismissible admin notice (never a redirect,
 * per the WordPress Theme Review guidelines), and reopenable from the Epiktetos
 * Setup Wizard page. Server-rendered steps with a progress indicator,
 * Previous / Next / Skip, writing to real theme options.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Wizard' ) ) {

	class Epiktetos_Wizard {

		const CAP         = 'edit_theme_options';
		const PENDING_OPT = 'epiktetos_wizard_pending';
		const DONE_OPT    = 'epiktetos_wizard_complete';
		const NONCE       = 'epiktetos_wizard';
		const SLUG        = 'epiktetos-wizard';

		public static function steps() {
			return array(
				'welcome'  => __( 'Welcome', 'epiktetos' ),
				'identity' => __( 'Site Identity', 'epiktetos' ),
				'branding' => __( 'Branding', 'epiktetos' ),
				'menus'    => __( 'Menus', 'epiktetos' ),
				'homepage' => __( 'Homepage', 'epiktetos' ),
				'sample'   => __( 'Sample Content', 'epiktetos' ),
				'finish'   => __( 'Finish', 'epiktetos' ),
			);
		}

		public static function init() {
			add_action( 'after_switch_theme', array( __CLASS__, 'on_activate' ) );
			add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 20 );
			add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_dismiss' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		}

		public static function on_activate() {
			if ( ! get_option( self::DONE_OPT ) ) {
				update_option( self::PENDING_OPT, 1 );
			}
		}

		public static function register_page() {
			// Registered by Epiktetos_Admin as a top-level submenu.
		}

		/**
		 * After activation, offer the wizard with a dismissible admin notice.
		 * The Theme Review guidelines forbid redirecting on activation, so this
		 * is a notice the user can act on or dismiss — never an automatic jump.
		 */
		public static function activation_notice() {
			if ( ! get_option( self::PENDING_OPT ) || ! current_user_can( self::CAP ) ) {
				return;
			}
			if ( isset( $_GET['page'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
				return; // Already on the wizard.
			}
			$start   = admin_url( 'admin.php?page=' . self::SLUG );
			$dismiss = wp_nonce_url( add_query_arg( 'epiktetos_wizard_dismiss', '1' ), self::NONCE . '_dismiss' );
			echo '<div class="notice notice-info is-dismissible"><p>';
			echo esc_html__( 'Welcome to Epiktetos. Run the setup wizard to configure your site identity, logo, favicon, and homepage.', 'epiktetos' );
			echo ' <a class="button button-primary" href="' . esc_url( $start ) . '">' . esc_html__( 'Start setup', 'epiktetos' ) . '</a> ';
			echo '<a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'epiktetos' ) . '</a>';
			echo '</p></div>';
		}

		/** Persist dismissal of the activation notice (user-initiated). */
		public static function handle_dismiss() {
			if ( empty( $_GET['epiktetos_wizard_dismiss'] ) || ! current_user_can( self::CAP ) ) {
				return;
			}
			check_admin_referer( self::NONCE . '_dismiss' );
			delete_option( self::PENDING_OPT );
			update_option( self::DONE_OPT, 1 );
			wp_safe_redirect( remove_query_arg( array( 'epiktetos_wizard_dismiss', '_wpnonce' ) ) );
			exit;
		}

		/** Persist a step, then advance. */
		public static function handle_save() {
			if ( empty( $_POST['epiktetos_wizard_save'] ) || ! current_user_can( self::CAP ) ) {
				return;
			}
			check_admin_referer( self::NONCE );
			$step = sanitize_key( wp_unslash( $_POST['epiktetos_wizard_save'] ) );

			switch ( $step ) {
				case 'identity':
					if ( isset( $_POST['blogname'] ) ) {
						update_option( 'blogname', sanitize_text_field( wp_unslash( $_POST['blogname'] ) ) );
					}
					if ( isset( $_POST['blogdescription'] ) ) {
						update_option( 'blogdescription', sanitize_text_field( wp_unslash( $_POST['blogdescription'] ) ) );
					}
					break;
				case 'branding':
					$logo_id = isset( $_POST['logo_id'] ) ? (int) $_POST['logo_id'] : 0;
					self::merge_branding( array( 'header_logo_id' => $logo_id, 'header_logo_source' => $logo_id ? 'branding' : 'text' ) );
					$fav_id = isset( $_POST['favicon_id'] ) ? (int) $_POST['favicon_id'] : 0;
					self::merge_branding( array( 'site_icon_id' => $fav_id ) );
					if ( $fav_id ) { update_option( 'site_icon', $fav_id ); }
					break;
				case 'homepage':
					$choice = isset( $_POST['front_choice'] ) ? sanitize_key( $_POST['front_choice'] ) : 'posts';
					if ( 'page' === $choice && ! empty( $_POST['front_page_id'] ) ) {
						update_option( 'show_on_front', 'page' );
						update_option( 'page_on_front', (int) $_POST['front_page_id'] );
					} else {
						update_option( 'show_on_front', 'posts' );
					}
					break;
				case 'sample':
					if ( ! empty( $_POST['create_sample'] ) && class_exists( 'Epiktetos_Admin' ) ) {
						Epiktetos_Admin::demo_import( false );
					}
					break;
			}

			$next = isset( $_POST['next_step'] ) ? sanitize_key( $_POST['next_step'] ) : 'finish';
			if ( 'finish' === $next || 'done' === $next ) {
				update_option( self::DONE_OPT, 1 );
				delete_option( self::PENDING_OPT );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $next ) );
			exit;
		}

		protected static function merge_branding( $changes ) {
			if ( ! class_exists( 'Epiktetos_Branding' ) ) {
				return;
			}
			$opt = (array) get_option( Epiktetos_Branding::OPTION, array() );
			update_option( Epiktetos_Branding::OPTION, array_merge( $opt, $changes ) );
		}

		protected static function ensure_about_page() {
			$existing = get_page_by_path( 'about' );
			if ( $existing ) {
				return $existing->ID;
			}
			return wp_insert_post( array(
				'post_title'   => __( 'About', 'epiktetos' ),
				'post_name'    => 'about',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html__( 'Tell readers who you are and what Epiktetos is about.', 'epiktetos' ) . '</p><!-- /wp:paragraph -->',
			) );
		}

		/* ============================================================
		   Render
		   ============================================================ */

		public static function render() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			$steps   = self::steps();
			$keys    = array_keys( $steps );
			$current = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';
			if ( ! isset( $steps[ $current ] ) ) {
				$current = 'welcome';
			}
			$idx     = array_search( $current, $keys, true );
			$next    = isset( $keys[ $idx + 1 ] ) ? $keys[ $idx + 1 ] : 'finish';
			$prev    = $idx > 0 ? $keys[ $idx - 1 ] : '';

			echo '<div class="wrap epi-admin epi-wizard">';

			// Progress.
			echo '<ol class="epi-wizard__steps" aria-label="' . esc_attr__( 'Setup progress', 'epiktetos' ) . '">';
			foreach ( $keys as $i => $k ) {
				$state = $i < $idx ? 'is-done' : ( $i === $idx ? 'is-current' : '' );
				echo '<li class="' . esc_attr( $state ) . '"><span class="epi-wizard__num">' . ( $i + 1 ) . '</span><span class="epi-wizard__lbl">' . esc_html( $steps[ $k ] ) . '</span></li>';
			}
			echo '</ol>';

			echo '<div class="epi-card epi-wizard__panel">';
			$method = 'step_' . $current;
			if ( method_exists( __CLASS__, $method ) ) {
				self::$method( $next, $prev );
			}
			echo '</div>';

			echo '<p class="epi-admin__credit"><strong>' . esc_html__( 'Built by Mehmet Can Orucu', 'epiktetos' ) . '</strong></p>';
			echo '</div>';
		}

		protected static function form_open( $step, $next ) {
			echo '<form method="post" class="epi-wizard__form">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="epiktetos_wizard_save" value="' . esc_attr( $step ) . '" />';
			echo '<input type="hidden" name="next_step" value="' . esc_attr( $next ) . '" />';
		}

		protected static function nav( $prev, $is_last = false ) {
			echo '<div class="epi-wizard__nav">';
			if ( $prev ) {
				echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $prev ) ) . '">' . esc_html__( '← Previous', 'epiktetos' ) . '</a>';
			} else {
				echo '<span></span>';
			}
			echo '<span class="epi-wizard__navright">';
			echo '<button type="submit" name="skip" value="1" class="button-link epi-skip">' . esc_html__( 'Skip', 'epiktetos' ) . '</button> ';
			echo '<button type="submit" class="button button-primary">' . ( $is_last ? esc_html__( 'Finish', 'epiktetos' ) : esc_html__( 'Save & Continue', 'epiktetos' ) ) . '</button>';
			echo '</span></div>';
			echo '</form>';
		}

		protected static function step_welcome( $next, $prev ) {
			echo '<h1>' . esc_html__( 'Welcome to Epiktetos', 'epiktetos' ) . '</h1>';
			echo '<p class="epi-lead">' . esc_html__( 'A few quick steps will get your publication looking its best — site identity, branding, menus, homepage, and optional sample content. You can change everything later.', 'epiktetos' ) . '</p>';
			echo '<form method="post" class="epi-wizard__form">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="epiktetos_wizard_save" value="welcome" />';
			echo '<input type="hidden" name="next_step" value="' . esc_attr( $next ) . '" />';
			echo '<div class="epi-wizard__nav"><span></span><span class="epi-wizard__navright"><button type="submit" name="skip" value="1" class="button-link epi-skip">' . esc_html__( 'Skip setup', 'epiktetos' ) . '</button> <button type="submit" class="button button-primary">' . esc_html__( 'Get started', 'epiktetos' ) . '</button></span></div>';
			echo '</form>';
		}

		protected static function step_identity( $next, $prev ) {
			echo '<h1>' . esc_html__( 'Site identity', 'epiktetos' ) . '</h1>';
			self::form_open( 'identity', $next );
			echo '<p class="epi-field"><label for="epi-blogname">' . esc_html__( 'Site title', 'epiktetos' ) . '</label>';
			echo '<input type="text" id="epi-blogname" name="blogname" class="regular-text" value="' . esc_attr( get_option( 'blogname' ) ) . '" /></p>';
			echo '<p class="epi-field"><label for="epi-tagline">' . esc_html__( 'Tagline', 'epiktetos' ) . '</label>';
			echo '<input type="text" id="epi-tagline" name="blogdescription" class="regular-text" value="' . esc_attr( get_option( 'blogdescription' ) ) . '" /></p>';
			self::nav( $prev );
		}

		protected static function step_branding( $next, $prev ) {
			$logo = class_exists( 'Epiktetos_Branding' ) ? (int) Epiktetos_Branding::get( 'header_logo_id' ) : 0;
			$fav  = class_exists( 'Epiktetos_Branding' ) ? (int) Epiktetos_Branding::get( 'site_icon_id' ) : 0;
			echo '<h1>' . esc_html__( 'Branding', 'epiktetos' ) . '</h1>';
			echo '<p class="epi-lead">' . esc_html__( 'Optional. Add a logo and a favicon, or leave them empty to use the text wordmark.', 'epiktetos' ) . '</p>';
			self::form_open( 'branding', $next );
			echo '<p class="epi-field"><strong>' . esc_html__( 'Logo', 'epiktetos' ) . '</strong></p>';
			self::media_field( 'logo_id', $logo, __( 'Choose logo', 'epiktetos' ) );
			echo '<p class="epi-field" style="margin-top:1.5em"><strong>' . esc_html__( 'Favicon', 'epiktetos' ) . '</strong> ' . esc_html__( '(square image, 512×512 recommended)', 'epiktetos' ) . '</p>';
			self::media_field( 'favicon_id', $fav, __( 'Choose favicon', 'epiktetos' ) );
			self::nav( $prev );
		}

		protected static function step_menus( $next, $prev ) {
			echo '<h1>' . esc_html__( 'Menus', 'epiktetos' ) . '</h1>';
			echo '<p class="epi-lead">' . esc_html__( 'Epiktetos has two menu locations: Primary Navigation (header) and Footer Navigation. Manage them in Appearance → Menus. Until you assign a menu, a sensible default is shown.', 'epiktetos' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">' . esc_html__( 'Open Appearance → Menus', 'epiktetos' ) . '</a></p>';
			self::form_open( 'menus', $next );
			self::nav( $prev );
		}

		protected static function step_homepage( $next, $prev ) {
			$front  = get_option( 'show_on_front' );
			$pages  = get_pages();
			echo '<h1>' . esc_html__( 'Choose your homepage', 'epiktetos' ) . '</h1>';
			self::form_open( 'homepage', $next );
			echo '<p class="epi-field"><label><input type="radio" name="front_choice" value="posts" ' . checked( 'posts', $front, false ) . ' /> ' . esc_html__( 'Editorial homepage with latest posts (recommended)', 'epiktetos' ) . '</label></p>';
			echo '<p class="epi-field"><label><input type="radio" name="front_choice" value="page" ' . checked( 'page', $front, false ) . ' /> ' . esc_html__( 'A static page:', 'epiktetos' ) . '</label> ';
			echo '<select name="front_page_id"><option value="0">' . esc_html__( '— select —', 'epiktetos' ) . '</option>';
			foreach ( $pages as $pg ) {
				echo '<option value="' . (int) $pg->ID . '" ' . selected( (int) get_option( 'page_on_front' ), $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . '</option>';
			}
			echo '</select></p>';
			self::nav( $prev );
		}

		protected static function step_sample( $next, $prev ) {
			$count = class_exists( 'Epiktetos_Admin' ) ? (int) get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_epiktetos_demo', 'meta_value' => 1 ) ) : 0;
			$full  = class_exists( 'Epiktetos_Admin' ) && Epiktetos_Admin::full_demo_present();
			echo '<h1>' . esc_html__( 'Sample content', 'epiktetos' ) . '</h1>';
			echo '<p class="epi-lead">' . esc_html__( 'Optional. Create a few local example posts to preview the layouts. Nothing is downloaded and your own content is never changed — remove the examples any time from Appearance → Epiktetos → Sample Content.', 'epiktetos' ) . '</p>';
			self::form_open( 'sample', $next );
			if ( $full ) {
				echo '<p class="epi-lead">' . esc_html__( 'Your site already has a full set of articles, so this will add nothing. Leave it unchecked.', 'epiktetos' ) . '</p>';
			}
			echo '<p class="epi-field"><label><input type="checkbox" name="create_sample" value="1" ' . ( ( $count || $full ) ? '' : 'checked' ) . ' /> ' . esc_html__( 'Create sample content now', 'epiktetos' ) . '</label></p>';
			self::nav( $prev );
		}

		protected static function step_finish( $next, $prev ) {
			update_option( self::DONE_OPT, 1 );
			delete_option( self::PENDING_OPT );
			echo '<h1>' . esc_html__( 'You’re all set', 'epiktetos' ) . '</h1>';
			echo '<p class="epi-lead">' . esc_html__( 'Epiktetos is ready. Fine-tune anything from Appearance → Epiktetos.', 'epiktetos' ) . '</p>';
			echo '<div class="epi-wizard__nav"><span></span><span class="epi-wizard__navright">';
			echo '<a class="button" href="' . esc_url( admin_url( 'themes.php?page=epiktetos-settings' ) ) . '">' . esc_html__( 'Open settings', 'epiktetos' ) . '</a> ';
			echo '<a class="button button-primary" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'View site', 'epiktetos' ) . '</a>';
			echo '</span></div>';
		}

		/** Media picker using the shared admin.js handler (data-epi-media-field). */
		protected static function media_field( $name, $value, $label ) {
			$url = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
			echo '<div class="epi-media" data-epi-media-field>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . (int) $value . '" data-epi-media-input />';
			echo '<div class="epi-media__preview" data-epi-media-preview>' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="" />' : '' ) . '</div>';
			echo '<p class="epi-media__name" data-epi-media-name>' . ( $value ? esc_html( get_the_title( $value ) ) : esc_html__( 'No asset selected', 'epiktetos' ) ) . '</p>';
			echo '<button type="button" class="button" data-epi-media-select data-label-empty="' . esc_attr( $label ) . '" data-label-replace="' . esc_attr__( 'Replace', 'epiktetos' ) . '">' . esc_html( $value ? __( 'Replace', 'epiktetos' ) : $label ) . '</button> ';
			echo '<button type="button" class="button-link epi-media__clear" data-epi-media-remove' . ( $value ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'epiktetos' ) . '</button>';
			echo '</div>';
		}
	}

	Epiktetos_Wizard::init();
}
