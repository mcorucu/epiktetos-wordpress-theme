<?php
/**
 * Epiktetos - branding assets and favicon support.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Branding' ) ) {

	class Epiktetos_Branding {

		const OPTION = 'epiktetos_branding_options';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_favicons' ), 4 );
			add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_uploads' ) );
		}

		public static function defaults() {
			return array(
				'logo_type'          => 'text',
				'logo_svg'           => 'logo-wordmark.svg',
				'logo_width'         => 160,
				'header_logo_id'     => 0,
				'footer_logo_id'     => 0,
				'logo_mark_id'       => 0,
				'site_icon_id'       => 0,
				'apple_touch_icon_id' => 0,
				'default_og_image_id' => 0,
				'header_logo_source' => 'branding',
				'footer_logo_source' => 'text',
			);
		}

		public static function get( $key ) {
			$opts = get_option( self::OPTION, array() );
			$defs = self::defaults();
			if ( is_array( $opts ) && array_key_exists( $key, $opts ) && '' !== $opts[ $key ] ) {
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
				'epiktetos_branding_identity',
				__( 'Identity', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Choose how the publication mark appears in shared theme surfaces.', 'epiktetos' ) . '</p>';
					echo self::asset_status();
				},
				'epiktetos-settings'
			);

			add_settings_section(
				'epiktetos_branding_logos',
				__( 'Logos', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Upload SVG, PNG, JPG, or WebP logo assets. Uploaded assets are preferred; bundled assets remain an internal fallback.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			add_settings_section(
				'epiktetos_branding_icons',
				__( 'Icons and social image', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Upload browser icons and the default social card image.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'logo_type'          => array(
					'label'   => __( 'Logo type', 'epiktetos' ),
					'section' => 'epiktetos_branding_identity',
					'type'    => 'select',
					'choices' => array(
						'text'     => __( 'Text logo', 'epiktetos' ),
						'wordmark' => __( 'Uploaded / SVG logo', 'epiktetos' ),
						'mark'     => __( 'SVG mark/icon', 'epiktetos' ),
					),
				),
				'header_logo_source' => array(
					'label'   => __( 'Header logo source', 'epiktetos' ),
					'section' => 'epiktetos_branding_identity',
					'type'    => 'select',
					'choices' => array(
						'branding'    => __( 'Branding setting', 'epiktetos' ),
						'text'        => __( 'Text logo', 'epiktetos' ),
						'header_logo' => __( 'Header logo', 'epiktetos' ),
						'mark'        => __( 'Logo mark/icon', 'epiktetos' ),
					),
				),
				'footer_logo_source' => array(
					'label'   => __( 'Footer logo source', 'epiktetos' ),
					'section' => 'epiktetos_branding_identity',
					'type'    => 'select',
					'choices' => array(
						'text'        => __( 'Text logo', 'epiktetos' ),
						'branding'    => __( 'Branding setting', 'epiktetos' ),
						'footer_logo' => __( 'Footer logo', 'epiktetos' ),
						'mark'        => __( 'Logo mark/icon', 'epiktetos' ),
					),
				),
				'header_logo_id'     => array( 'label' => __( 'Header logo', 'epiktetos' ), 'section' => 'epiktetos_branding_logos', 'type' => 'media', 'library' => array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ) ),
				'footer_logo_id'     => array( 'label' => __( 'Footer logo', 'epiktetos' ), 'section' => 'epiktetos_branding_logos', 'type' => 'media', 'library' => array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ) ),
				'logo_mark_id'       => array( 'label' => __( 'Logo mark/icon', 'epiktetos' ), 'section' => 'epiktetos_branding_logos', 'type' => 'media', 'library' => array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ) ),
				'logo_svg'           => array(
					'label'   => __( 'Logo SVG / wordmark', 'epiktetos' ),
					'section' => 'epiktetos_branding_logos',
					'type'    => 'select',
					'choices' => self::svg_choices(),
				),
				'logo_width'         => array( 'label' => __( 'Brand logo width (px)', 'epiktetos' ), 'section' => 'epiktetos_branding_logos', 'type' => 'number', 'min' => 48, 'max' => 320 ),
				'site_icon_id'       => array( 'label' => __( 'Site icon / browser favicon', 'epiktetos' ), 'section' => 'epiktetos_branding_icons', 'type' => 'media', 'library' => array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ) ),
				'apple_touch_icon_id' => array( 'label' => __( 'Apple touch icon', 'epiktetos' ), 'section' => 'epiktetos_branding_icons', 'type' => 'media', 'library' => array( 'image/png', 'image/jpeg', 'image/webp' ) ),
				'default_og_image_id' => array( 'label' => __( 'Default OG image', 'epiktetos' ), 'section' => 'epiktetos_branding_icons', 'type' => 'media', 'library' => array( 'image/png', 'image/jpeg', 'image/webp' ) ),
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
			$id    = 'epiktetos-branding-' . $key;

			if ( 'number' === $args['type'] ) {
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $args['min'] ),
					esc_attr( $args['max'] )
				);
				return;
			}

			if ( 'media' === $args['type'] ) {
				self::render_media_field( $key, (int) $value, isset( $args['library'] ) ? $args['library'] : array( 'image' ) );
				return;
			}

			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $args['choices'] as $choice_value => $choice_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $choice_value ),
					selected( $value, $choice_value, false ),
					esc_html( $choice_label )
				);
			}
			echo '</select>';
			self::preview_for_field( $key, $value );
		}

		protected static function render_media_field( $key, $value, $library ) {
			$name = self::OPTION . '[' . $key . ']';
			$id   = 'epiktetos-branding-' . $key;
			$url  = $value ? wp_get_attachment_url( $value ) : '';
			$type = $value ? get_post_mime_type( $value ) : '';
			$label = $value ? get_the_title( $value ) : __( 'No asset selected', 'epiktetos' );
			$meta  = self::attachment_meta_label( $value );

			echo '<div class="epi-media-field" data-epi-media-field data-library="' . esc_attr( implode( ',', (array) $library ) ) . '">';
			echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-epi-media-input />';
			echo '<div class="epi-media-preview" data-epi-media-preview>';
			if ( $url ) {
				echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label ) . '" />';
			}
			echo '</div>';
			echo '<div class="epi-media-meta" data-epi-media-meta>';
			echo '<p class="epi-media-name" data-epi-media-name>' . esc_html( $label ) . '</p>';
			echo '<p class="epi-media-detail" data-epi-media-detail>' . esc_html( $meta ? $meta : $type ) . '</p>';
			echo '</div>';
			echo '<div class="epi-tools">';
			echo '<button type="button" class="button button-secondary" data-epi-media-select data-label-empty="' . esc_attr__( 'Upload / Select', 'epiktetos' ) . '" data-label-replace="' . esc_attr__( 'Replace', 'epiktetos' ) . '">' . esc_html( $value ? __( 'Replace', 'epiktetos' ) : __( 'Upload / Select', 'epiktetos' ) ) . '</button>';
			echo '<button type="button" class="button button-secondary" data-epi-media-remove ' . ( $value ? '' : 'hidden' ) . '>' . esc_html__( 'Remove', 'epiktetos' ) . '</button>';
			echo '</div>';
			echo '</div>';
		}

		public static function sanitize( $input ) {
			$defs = self::defaults();
			$out  = array();

			$logo_types = array( 'text', 'wordmark', 'mark' );
			$sources    = array( 'branding', 'text', 'header_logo', 'footer_logo', 'mark' );
			$svgs       = array_keys( self::svg_choices() );

			$out['logo_type']          = isset( $input['logo_type'] ) && in_array( $input['logo_type'], $logo_types, true ) ? $input['logo_type'] : $defs['logo_type'];
			$out['logo_svg']           = isset( $input['logo_svg'] ) && in_array( $input['logo_svg'], $svgs, true ) ? $input['logo_svg'] : $defs['logo_svg'];
			$out['logo_width']         = max( 48, min( 320, (int) ( isset( $input['logo_width'] ) ? $input['logo_width'] : $defs['logo_width'] ) ) );
			foreach ( array( 'header_logo_id', 'footer_logo_id', 'logo_mark_id', 'site_icon_id', 'apple_touch_icon_id', 'default_og_image_id' ) as $media_key ) {
				$out[ $media_key ] = isset( $input[ $media_key ] ) ? max( 0, (int) $input[ $media_key ] ) : 0;
			}
			$out['header_logo_source'] = isset( $input['header_logo_source'] ) && in_array( $input['header_logo_source'], $sources, true ) ? $input['header_logo_source'] : $defs['header_logo_source'];
			$out['footer_logo_source'] = isset( $input['footer_logo_source'] ) && in_array( $input['footer_logo_source'], $sources, true ) ? $input['footer_logo_source'] : $defs['footer_logo_source'];

			return $out;
		}

		public static function render_logo( $context = 'header', $fallback_width = 160 ) {
			$source = 'footer' === $context ? self::get( 'footer_logo_source' ) : self::get( 'header_logo_source' );
			$type   = 'branding' === $source ? self::get( 'logo_type' ) : $source;
			$width  = (int) ( self::get( 'logo_width' ) ? self::get( 'logo_width' ) : $fallback_width );
			$name   = get_bloginfo( 'name', 'display' );

			if ( 'text' === $type ) {
				return '<span class="ts-logo__text">' . esc_html( $name ) . '</span>';
			}

			$url = '';
			if ( 'header_logo' === $type || 'wordmark' === $type ) {
				$attachment_id = ( 'footer' === $context && 'wordmark' === $type ) ? self::get( 'footer_logo_id' ) : self::get( 'header_logo_id' );
				$url           = self::attachment_url( (int) $attachment_id );
			} elseif ( 'footer_logo' === $type ) {
				$url = self::attachment_url( (int) self::get( 'footer_logo_id' ) );
			} elseif ( 'mark' === $type ) {
				$url = self::attachment_url( (int) self::get( 'logo_mark_id' ) );
			}
			if ( ! $url ) {
				$file = 'mark' === $type ? 'logo-mark.svg' : self::get( 'logo_svg' );
				$url  = self::asset_url( 'svg/' . $file );
			}
			if ( ! $url ) {
				return '<span class="ts-logo__text">' . esc_html( $name ) . '</span>';
			}

			return '<span class="ts-logo__asset" style="--ts-logo-w:' . (int) $width . 'px;"><img class="ts-logo__img" src="' . esc_url( $url ) . '" alt="" decoding="async" /></span>';
		}

		public static function print_favicons() {
			if ( get_site_icon_url() ) {
				return;
			}

			$icon_id   = (int) self::get( 'site_icon_id' );
			$apple_id  = (int) self::get( 'apple_touch_icon_id' );
			$icon      = self::attachment_url( $icon_id );
			$icon_type = $icon ? self::attachment_mime( $icon_id ) : 'image/png';
			if ( ! $icon ) {
				$icon = self::asset_url( 'brand/icon-256.png' );
				$icon_type = 'image/png';
			}
			$apple = self::attachment_url( $apple_id );
			if ( ! $apple ) {
				$apple = self::asset_url( 'brand/icon-256.png' );
			}
			$svg = self::asset_url( 'svg/logo-mark.svg' );

			echo "\n" . '<!-- Epiktetos branding icons -->' . "\n";
			if ( $icon ) {
				echo '<link rel="icon" href="' . esc_url( $icon ) . '" sizes="32x32" type="' . esc_attr( $icon_type ) . '">' . "\n";
				echo '<link rel="icon" href="' . esc_url( $icon ) . '" sizes="256x256" type="' . esc_attr( $icon_type ) . '">' . "\n";
			}
			if ( $apple ) {
				echo '<link rel="apple-touch-icon" href="' . esc_url( $apple ) . '" sizes="180x180">' . "\n";
			}
			if ( $svg ) {
				echo '<link rel="icon" href="' . esc_url( $svg ) . '" type="image/svg+xml">' . "\n";
			}
			echo '<!-- /Epiktetos branding icons -->' . "\n";
		}

		public static function default_og_image_url() {
			return self::attachment_url( (int) self::get( 'default_og_image_id' ) );
		}

		public static function allow_svg_uploads( $mimes ) {
			if ( current_user_can( 'edit_theme_options' ) ) {
				$mimes['svg'] = 'image/svg+xml';
			}
			return $mimes;
		}

		public static function svg_choices() {
			return array(
				'logo-wordmark.svg' => __( 'logo-wordmark.svg', 'epiktetos' ),
				'logo-mono.svg'     => __( 'logo-mono.svg', 'epiktetos' ),
				'logo-mark.svg'     => __( 'logo-mark.svg', 'epiktetos' ),
			);
		}

		public static function asset_status() {
			$assets = array(
				'assets/svg/logo-wordmark.svg',
				'assets/svg/logo-mark.svg',
				'assets/svg/logo-mono.svg',
			);
			$html = '<div class="epi-asset-status" aria-label="' . esc_attr__( 'Brand asset status', 'epiktetos' ) . '">';
			foreach ( $assets as $asset ) {
				$exists = file_exists( trailingslashit( get_template_directory() ) . $asset );
				$html .= '<span class="' . esc_attr( $exists ? 'is-ready' : 'is-missing' ) . '">' . esc_html( basename( $asset ) ) . '</span>';
			}
			$html .= '</div>';
			return $html;
		}

		protected static function preview_for_field( $key, $value ) {
			if ( 'logo_svg' === $key && $value ) {
				$svg = self::inline_svg( $value );
				if ( $svg ) {
					echo '<div class="epi-brand-preview epi-brand-preview--svg">' . $svg . '</div>';
				}
			}
		}

		protected static function attachment_url( $id ) {
			$id = (int) $id;
			if ( ! $id ) {
				return '';
			}
			$url = wp_get_attachment_url( $id );
			return $url ? $url : '';
		}

		protected static function attachment_mime( $id ) {
			$type = get_post_mime_type( (int) $id );
			return $type ? $type : 'image/png';
		}

		protected static function attachment_meta_label( $id ) {
			$id = (int) $id;
			if ( ! $id ) {
				return '';
			}
			$parts = array();
			$type = get_post_mime_type( $id );
			if ( $type ) {
				$parts[] = $type;
			}
			$meta = wp_get_attachment_metadata( $id );
			if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$parts[] = (int) $meta['width'] . '×' . (int) $meta['height'];
			}
			$file = get_attached_file( $id );
			if ( $file ) {
				array_unshift( $parts, basename( $file ) );
			}
			return implode( ' · ', array_filter( $parts ) );
		}

		protected static function inline_svg( $file ) {
			$file = sanitize_file_name( $file );
			$path = trailingslashit( get_template_directory() ) . 'assets/svg/' . $file;
			if ( ! file_exists( $path ) ) {
				return '';
			}
			$svg = file_get_contents( $path );
			$svg = preg_replace( '/<\?xml.*?\?>/s', '', $svg );
			$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
			return self::compress( $svg );
		}

		protected static function asset_url( $path ) {
			$path = ltrim( $path, '/' );
			if ( 0 !== strpos( $path, 'assets/' ) ) {
				$path = 'assets/' . $path;
			}
			$file = trailingslashit( get_template_directory() ) . $path;
			if ( ! file_exists( $file ) ) {
				return '';
			}
			return trailingslashit( get_template_directory_uri() ) . $path;
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Branding::init();
}
