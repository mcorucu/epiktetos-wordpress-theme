<?php
/**
 * Epiktetos — Article Voiceover.
 *
 * Optional per-post audio narration. An audio file is chosen from the WordPress
 * Media Library in the post editor and stored as an attachment ID in post meta.
 * On single posts (only), a calm "Listen to this article" player renders at the
 * top of the reading column. Progressive enhancement: the baseline is a native
 * <audio controls> element; JavaScript replaces it with a lightweight custom UI.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Voiceover' ) ) {

	class Epiktetos_Voiceover {

		const META_KEY    = '_epiktetos_article_voiceover_audio_id';
		const NONCE       = 'epiktetos_voiceover_save';
		const NONCE_FIELD = 'epiktetos_voiceover_nonce';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_meta' ) );
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
			add_action( 'save_post_post', array( __CLASS__, 'save' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_assets' ) );
		}

		/**
		 * Register the meta so it is sanitized and auth-gated. Kept out of REST:
		 * it is edited only through the classic media selector meta box.
		 */
		public static function register_meta() {
			register_post_meta(
				'post',
				self::META_KEY,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'sanitize_callback' => 'absint',
					'show_in_rest'      => false,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		/* ============================================================
		   Admin: media selector meta box
		   ============================================================ */

		public static function add_meta_box() {
			add_meta_box(
				'epiktetos_voiceover',
				__( 'Article Voiceover', 'epiktetos' ),
				array( __CLASS__, 'render_meta_box' ),
				'post',
				'side',
				'default'
			);
		}

		public static function render_meta_box( $post ) {
			wp_nonce_field( self::NONCE, self::NONCE_FIELD );
			$id    = (int) get_post_meta( $post->ID, self::META_KEY, true );
			$audio = self::get_audio_from_id( $id );
			$has   = (bool) $audio;
			?>
			<div class="epiktetos-voiceover" data-epiktetos-voiceover>
				<input type="hidden" name="<?php echo esc_attr( self::META_KEY ); ?>" value="<?php echo esc_attr( $has ? $id : 0 ); ?>" data-voiceover-input />
				<p class="epiktetos-voiceover__desc"><?php esc_html_e( 'Optional. Add an audio narration (mp3, m4a, ogg, or wav) to show a “Listen to this article” player at the top of this post.', 'epiktetos' ); ?></p>
				<p class="epiktetos-voiceover__name" data-voiceover-name><?php echo $has ? esc_html( self::audio_label( $id ) ) : esc_html__( 'No audio selected', 'epiktetos' ); ?></p>
				<p>
					<button type="button" class="button" data-voiceover-select><?php echo $has ? esc_html__( 'Replace audio', 'epiktetos' ) : esc_html__( 'Select audio', 'epiktetos' ); ?></button>
					<button type="button" class="button-link epiktetos-voiceover__remove" data-voiceover-remove <?php echo $has ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'epiktetos' ); ?></button>
				</p>
			</div>
			<?php
		}

		/**
		 * Save the selected attachment ID. Validates nonce, capability, and that
		 * the ID is a real audio attachment; otherwise the meta is cleared.
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		public static function save( $post_id, $post ) {
			if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$raw = isset( $_POST[ self::META_KEY ] ) ? absint( wp_unslash( $_POST[ self::META_KEY ] ) ) : 0;
			if ( $raw && self::get_audio_from_id( $raw ) ) {
				update_post_meta( $post_id, self::META_KEY, $raw );
			} else {
				delete_post_meta( $post_id, self::META_KEY );
			}
		}

		public static function admin_assets( $hook ) {
			if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'post' !== $screen->post_type ) {
				return;
			}
			wp_enqueue_media();
			wp_enqueue_script(
				'epiktetos-voiceover-admin',
				EPIKTETOS_URI . '/assets/js/voiceover-admin.js',
				array( 'jquery' ),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/js/voiceover-admin.js' ) : null,
				true
			);
			wp_localize_script(
				'epiktetos-voiceover-admin',
				'EpiktetosVoiceover',
				array(
					'title'   => __( 'Select article voiceover', 'epiktetos' ),
					'button'  => __( 'Use this audio', 'epiktetos' ),
					'replace' => __( 'Replace audio', 'epiktetos' ),
					'select'  => __( 'Select audio', 'epiktetos' ),
					'empty'   => __( 'No audio selected', 'epiktetos' ),
				)
			);
		}

		/* ============================================================
		   Data helpers
		   ============================================================ */

		/**
		 * Return audio info for a valid audio attachment ID, or false.
		 * Never trusts a raw URL — the URL is derived from the attachment.
		 *
		 * @param int $id Attachment ID.
		 * @return array{id:int,url:string,mime:string}|false
		 */
		public static function get_audio_from_id( $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				return false;
			}
			$attachment = get_post( $id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return false;
			}
			$mime = (string) get_post_mime_type( $id );
			if ( 0 !== strpos( $mime, 'audio/' ) ) {
				return false;
			}
			$url = wp_get_attachment_url( $id );
			if ( ! $url ) {
				return false;
			}
			return array(
				'id'   => $id,
				'url'  => $url,
				'mime' => $mime,
			);
		}

		protected static function audio_label( $id ) {
			$file = get_attached_file( $id );
			return $file ? basename( $file ) : get_the_title( $id );
		}

		public static function has_voiceover( $post_id ) {
			$id = (int) get_post_meta( $post_id, self::META_KEY, true );
			return (bool) self::get_audio_from_id( $id );
		}

		/* ============================================================
		   Frontend
		   ============================================================ */

		/** Load the player assets only on single posts that have a voiceover. */
		public static function frontend_assets() {
			if ( ! is_singular( 'post' ) ) {
				return;
			}
			$post_id = get_queried_object_id();
			if ( ! $post_id || ! self::has_voiceover( $post_id ) ) {
				return;
			}
			wp_enqueue_style(
				'epiktetos-voiceover',
				EPIKTETOS_URI . '/assets/css/voiceover.css',
				array(),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/css/voiceover.css' ) : null
			);
			wp_enqueue_script(
				'epiktetos-voiceover',
				EPIKTETOS_URI . '/assets/js/voiceover.js',
				array(),
				function_exists( 'epiktetos_asset_ver' ) ? epiktetos_asset_ver( 'assets/js/voiceover.js' ) : null,
				true
			);
			wp_localize_script(
				'epiktetos-voiceover',
				'EpiktetosVoiceoverL10n',
				array(
					'play'     => __( 'Play', 'epiktetos' ),
					'pause'    => __( 'Pause', 'epiktetos' ),
					'mute'     => __( 'Mute', 'epiktetos' ),
					'unmute'   => __( 'Unmute', 'epiktetos' ),
					'seek'     => __( 'Seek', 'epiktetos' ),
					'speed'    => __( 'Playback speed', 'epiktetos' ),
					'current'  => __( 'Current time', 'epiktetos' ),
					'duration' => __( 'Duration', 'epiktetos' ),
				)
			);
		}

		/**
		 * Player markup for the single-post template. Returns '' when the post
		 * has no valid audio voiceover (no wrapper, no placeholder).
		 *
		 * @param int|WP_Post|null $post Post.
		 * @return string
		 */
		public static function render_player( $post = null ) {
			$post = get_post( $post );
			if ( ! $post || 'post' !== $post->post_type ) {
				return '';
			}
			$id    = (int) get_post_meta( $post->ID, self::META_KEY, true );
			$audio = self::get_audio_from_id( $id );
			if ( ! $audio ) {
				return '';
			}

			$html  = '<section class="ts-voiceover" data-ts-voiceover aria-label="' . esc_attr__( 'Article voiceover', 'epiktetos' ) . '">';
			$html .= '<div class="ts-voiceover__intro">';
			$html .= '<span class="ts-voiceover__label">' . esc_html__( 'Listen to this article', 'epiktetos' ) . '</span>';
			$html .= '<span class="ts-voiceover__sub">' . esc_html__( 'Audio version of this article', 'epiktetos' ) . '</span>';
			$html .= '</div>';
			// Baseline: native controls (works with JS disabled). JS enhances this.
			$html .= '<audio class="ts-voiceover__audio" preload="metadata" controls src="' . esc_url( $audio['url'] ) . '"></audio>';
			$html .= '</section>';

			return $html;
		}
	}

	Epiktetos_Voiceover::init();
}
