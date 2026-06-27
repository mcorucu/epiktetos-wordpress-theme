<?php
/**
 * Epiktetos - editorial discussion experience.
 *
 * Renders WordPress comments as quiet editorial annotations through
 * [epiktetos_comments] and manages discussion settings.
 *
 * @package Epiktetos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Epiktetos_Comments' ) ) {

	class Epiktetos_Comments {

		const OPTION = 'epiktetos_discussion_options';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_filter( 'comments_open', array( __CLASS__, 'comments_open' ), 20, 2 );
			add_filter( 'option_thread_comments', array( __CLASS__, 'thread_comments_option' ) );
			add_filter( 'option_thread_comments_depth', array( __CLASS__, 'thread_depth_option' ) );
		}

		public static function register_shortcode() {
			add_shortcode( 'epiktetos_comments', array( __CLASS__, 'render_shortcode' ) );
		}

		public static function defaults() {
			return array(
				'enable_comments'          => 1,
				'enable_threaded_replies' => 1,
				'maximum_reply_depth'      => 2,
				'show_website_field'       => 1,
				'show_avatars'             => 0,
				'default_avatar_size'      => 28,
				'moderation_text'          => __( 'Responses usually appear after moderation.', 'epiktetos' ),
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
				'epiktetos_discussion',
				__( 'Discussion', 'epiktetos' ),
				function () {
					echo '<p>' . esc_html__( 'Configure the editorial comment experience below single articles.', 'epiktetos' ) . '</p>';
				},
				'epiktetos-settings'
			);

			$fields = array(
				'enable_comments'          => array( 'label' => __( 'Enable comments', 'epiktetos' ), 'type' => 'checkbox' ),
				'enable_threaded_replies' => array( 'label' => __( 'Enable threaded replies', 'epiktetos' ), 'type' => 'checkbox' ),
				'maximum_reply_depth'      => array( 'label' => __( 'Maximum reply depth', 'epiktetos' ), 'type' => 'number', 'min' => 1, 'max' => 2 ),
				'show_website_field'       => array( 'label' => __( 'Show website field', 'epiktetos' ), 'type' => 'checkbox' ),
				'show_avatars'             => array( 'label' => __( 'Show avatars', 'epiktetos' ), 'type' => 'checkbox' ),
				'default_avatar_size'      => array( 'label' => __( 'Default avatar size', 'epiktetos' ), 'type' => 'number', 'min' => 20, 'max' => 40 ),
				'moderation_text'          => array( 'label' => __( 'Moderation reminder text', 'epiktetos' ), 'type' => 'textarea' ),
			);

			foreach ( $fields as $key => $field ) {
				add_settings_field(
					self::OPTION . '_' . $key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					'epiktetos-settings',
					'epiktetos_discussion',
					array_merge( array( 'key' => $key ), $field )
				);
			}
		}

		public static function render_field( $args ) {
			$key   = $args['key'];
			$value = self::get( $key );
			$name  = self::OPTION . '[' . $key . ']';
			$id    = 'epiktetos-discussion-' . $key;

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
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( isset( $args['min'] ) ? $args['min'] : 1 ),
				esc_attr( isset( $args['max'] ) ? $args['max'] : 40 )
			);
		}

		public static function sanitize( $input ) {
			$defs = self::defaults();
			return array(
				'enable_comments'          => ! empty( $input['enable_comments'] ) ? 1 : 0,
				'enable_threaded_replies' => ! empty( $input['enable_threaded_replies'] ) ? 1 : 0,
				'maximum_reply_depth'      => max( 1, min( 2, (int) ( isset( $input['maximum_reply_depth'] ) ? $input['maximum_reply_depth'] : $defs['maximum_reply_depth'] ) ) ),
				'show_website_field'       => ! empty( $input['show_website_field'] ) ? 1 : 0,
				'show_avatars'             => ! empty( $input['show_avatars'] ) ? 1 : 0,
				'default_avatar_size'      => max( 20, min( 40, (int) ( isset( $input['default_avatar_size'] ) ? $input['default_avatar_size'] : $defs['default_avatar_size'] ) ) ),
				'moderation_text'          => isset( $input['moderation_text'] ) && '' !== trim( (string) $input['moderation_text'] ) ? sanitize_text_field( $input['moderation_text'] ) : $defs['moderation_text'],
			);
		}

		public static function enqueue() {
			if ( is_singular( 'post' ) && (int) self::get( 'enable_threaded_replies' ) && comments_open() && get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
		}

		public static function comments_open( $open, $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && 'post' === $post->post_type && ! (int) self::get( 'enable_comments' ) ) {
				return false;
			}
			return $open;
		}

		public static function thread_comments_option( $value ) {
			return (int) self::get( 'enable_threaded_replies' ) ? '1' : '0';
		}

		public static function thread_depth_option( $value ) {
			return (string) max( 1, min( 2, (int) self::get( 'maximum_reply_depth' ) ) );
		}

		public static function render_shortcode() {
			if ( ! is_singular( 'post' ) ) {
				return '';
			}
			return self::render( get_post() );
		}

		public static function render( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return '';
			}

			$post_id      = (int) $post->ID;
			$count        = (int) get_comments_number( $post_id );
			$comments     = self::approved_comments( $post_id );
			$comments_on  = comments_open( $post_id );
			$enabled      = (int) self::get( 'enable_comments' );
			$heading_id   = 'ts-discussion-title';

			$html  = '<section class="ts-discussion" id="discussion" aria-labelledby="' . esc_attr( $heading_id ) . '">';
			$html .= '<header class="ts-discussion__header">';
			$html .= '<p class="ts-discussion__eyebrow">' . esc_html__( 'Discussion', 'epiktetos' ) . '</p>';
			$html .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html__( 'Discussion', 'epiktetos' ) . '</h2>';
			$html .= '<p class="ts-discussion__count">' . esc_html( self::comment_count_label( $count ) ) . '</p>';
			$html .= '<p class="ts-discussion__intro">' . esc_html__( 'Thoughtful conversations are encouraged.', 'epiktetos' ) . '</p>';
			$html .= '</header>';

			$html .= self::render_notice();

			if ( empty( $comments ) ) {
				$html .= '<div class="ts-discussion__empty" role="status"><p>' . esc_html__( 'No discussion yet.', 'epiktetos' ) . '</p><p>' . esc_html__( 'Be the first reader to share a thoughtful response.', 'epiktetos' ) . '</p></div>';
			} else {
				$html .= self::render_list( $comments );
				$html .= self::render_pagination( $comments );
			}

			if ( $comments_on && $enabled ) {
				$html .= self::render_form( $post_id );
			} else {
				$html .= '<div class="ts-discussion__closed" role="status"><p>' . esc_html__( 'Discussion is closed for this essay.', 'epiktetos' ) . '</p></div>';
			}

			$html .= '<div class="ts-backtop-wrap"><button type="button" class="ts-backtop" data-ts-backtop hidden>' . esc_html__( 'Back to Top', 'epiktetos' ) . '</button></div>';
			$html .= '</section>';

			return self::compress( $html );
		}

		public static function render_statistics( $post ) {
			if ( ! $post instanceof WP_Post ) {
				return '';
			}

			$words = (int) get_post_meta( $post->ID, '_epiktetos_word_count', true );
			if ( ! $words ) {
				$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
			}
			$reading_time = class_exists( 'Epiktetos_Single' ) ? Epiktetos_Single::reading_time( $post->post_content ) : max( 1, (int) ceil( $words / 200 ) );
			$cat = self::primary_category( $post );
			$tags = get_the_tags( $post->ID );
			$tag_names = $tags && ! is_wp_error( $tags ) ? implode( ', ', array_slice( wp_list_pluck( $tags, 'name' ), 0, 3 ) ) : __( 'None', 'epiktetos' );

			$items = array(
				__( 'Words', 'epiktetos' )        => number_format_i18n( $words ),
				__( 'Reading time', 'epiktetos' ) => sprintf( _n( '%d min', '%d min', $reading_time, 'epiktetos' ), $reading_time ),
				__( 'Published', 'epiktetos' )    => get_the_date( '', $post ),
				__( 'Updated', 'epiktetos' )      => get_the_modified_date( '', $post ),
				__( 'Category', 'epiktetos' )     => $cat ? $cat->name : __( 'Uncategorized', 'epiktetos' ),
				__( 'Tags', 'epiktetos' )         => $tag_names,
				__( 'Shares', 'epiktetos' )       => __( 'Quietly uncounted', 'epiktetos' ),
			);

			$html  = '<aside class="ts-reading-stats" aria-labelledby="ts-reading-stats-title">';
			$html .= '<h2 id="ts-reading-stats-title">' . esc_html__( 'Reading Statistics', 'epiktetos' ) . '</h2>';
			$html .= '<dl>';
			foreach ( $items as $label => $value ) {
				$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			$html .= '</dl>';
			$html .= '</aside>';

			return self::compress( $html );
		}

		protected static function approved_comments( $post_id ) {
			$comments = get_comments( array(
				'post_id'      => (int) $post_id,
				'status'       => 'approve',
				'type'         => 'comment',
				'hierarchical' => false,
				'orderby'      => 'comment_date_gmt',
				'order'        => 'ASC',
			) );

			return is_array( $comments ) ? $comments : array();
		}

		protected static function render_list( $comments ) {
			$page_comments = (bool) get_option( 'page_comments' );
			$per_page      = $page_comments ? (int) get_option( 'comments_per_page' ) : 0;
			$threaded      = (int) self::get( 'enable_threaded_replies' );
			$tree          = self::comment_tree( $comments );
			$top_level     = $threaded ? $tree[0] : $comments;
			$total_pages   = self::comment_page_count( $comments );
			$current       = self::current_comment_page( $total_pages );

			if ( $page_comments && $per_page > 0 ) {
				$top_level = array_slice( $top_level, ( $current - 1 ) * $per_page, $per_page );
			}

			$args = array(
				'add_below'  => 'comment',
				'max_depth'  => max( 1, min( 2, (int) self::get( 'maximum_reply_depth' ) ) ),
				'page'       => $current,
				'reply_text' => __( 'Reply', 'epiktetos' ),
				'login_text' => __( 'Log in to reply', 'epiktetos' ),
			);

			$list = '';
			foreach ( $top_level as $comment ) {
				$list .= self::render_comment_markup( $comment, $tree, 1, $args );
			}

			return '<div class="ts-comment-list" aria-label="' . esc_attr__( 'Comments', 'epiktetos' ) . '">' . $list . '</div>';
		}

		protected static function comment_tree( $comments ) {
			$indexed = array();
			$tree    = array( 0 => array() );

			foreach ( $comments as $comment ) {
				$indexed[ (int) $comment->comment_ID ] = true;
			}

			foreach ( $comments as $comment ) {
				$parent = (int) $comment->comment_parent;
				if ( $parent && empty( $indexed[ $parent ] ) ) {
					$parent = 0;
				}
				if ( ! isset( $tree[ $parent ] ) ) {
					$tree[ $parent ] = array();
				}
				$tree[ $parent ][] = $comment;
			}

			return $tree;
		}

		protected static function render_comment_markup( $comment, $tree, $depth, $args ) {
			$GLOBALS['comment'] = $comment;
			$post      = get_post( $comment->comment_post_ID );
			$is_author = $post instanceof WP_Post && (int) $comment->user_id && (int) $comment->user_id === (int) $post->post_author;
			$max_depth = max( 1, min( 2, (int) self::get( 'maximum_reply_depth' ) ) );
			$content   = self::comment_content( $comment );
			$link      = self::comment_permalink( $comment, isset( $args['page'] ) ? (int) $args['page'] : 1 );

			$html  = '<article id="comment-' . esc_attr( $comment->comment_ID ) . '" class="' . esc_attr( 'ts-comment depth-' . (int) $depth ) . '">';
			$html .= '<header class="ts-comment__header">';
			if ( (int) self::get( 'show_avatars' ) ) {
				$html .= '<div class="ts-comment__avatar">' . get_avatar( $comment, (int) self::get( 'default_avatar_size' ), '', get_comment_author( $comment ), array( 'class' => 'ts-comment__avatar-img' ) ) . '</div>';
			}
			$html .= '<div class="ts-comment__meta">';
			$html .= '<p class="ts-comment__author">' . esc_html( get_comment_author( $comment ) );
			if ( $is_author ) {
				$html .= '<span class="ts-comment__author-label">' . esc_html__( 'Author', 'epiktetos' ) . '</span>';
			}
			$html .= '</p>';
			$html .= '<p class="ts-comment__date-line"><a class="ts-comment__date" href="' . esc_url( $link ) . '"><time datetime="' . esc_attr( get_comment_time( 'c', false, true, $comment ) ) . '">' . esc_html( get_comment_date( '', $comment ) ) . '</time></a></p>';
			$html .= '</div>';
			$html .= '</header>';
			$html .= '<div class="ts-comment__content">' . $content . '</div>';
			if ( (int) self::get( 'enable_threaded_replies' ) && comments_open( $comment->comment_post_ID ) && (int) $depth < $max_depth ) {
				$reply = get_comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'     => (int) $depth,
							'max_depth' => $max_depth,
						)
					),
					$comment,
					$comment->comment_post_ID
				);
				if ( $reply ) {
					$reply_url = add_query_arg( 'replytocom', (int) $comment->comment_ID, self::comment_page_url( isset( $args['page'] ) ? (int) $args['page'] : 1 ) ) . '#respond';
					$reply     = preg_replace( '/href=(["\'])(.*?)\\1/', 'href=$1' . esc_url( $reply_url ) . '$1', $reply, 1 );
					$html .= '<div class="ts-comment__reply">' . $reply . '</div>';
				}
			}
			$html .= '</article>';

			if ( (int) self::get( 'enable_threaded_replies' ) && (int) $depth < $max_depth && ! empty( $tree[ (int) $comment->comment_ID ] ) ) {
				foreach ( $tree[ (int) $comment->comment_ID ] as $child ) {
					$html .= self::render_comment_markup( $child, $tree, $depth + 1, $args );
				}
			}

			return $html;
		}

		protected static function comment_content( $comment ) {
			ob_start();
			comment_text( $comment );
			return ob_get_clean();
		}

		protected static function comment_permalink( $comment, $page ) {
			$url = self::comment_page_url( $page );
			return $url . '#comment-' . (int) $comment->comment_ID;
		}

		protected static function comment_page_url( $page ) {
			$url = get_comments_pagenum_link( max( 1, (int) $page ) );
			$url = preg_replace( '/#.*$/', '', $url );
			return $url;
		}

		protected static function render_pagination( $comments ) {
			if ( ! get_option( 'page_comments' ) ) {
				return '';
			}

			$per_page = max( 1, (int) get_option( 'comments_per_page' ) );
			$total    = self::comment_page_count( $comments );
			if ( $total <= 1 ) {
				return '';
			}

			$current = self::current_comment_page( $total );
			$prev = $current > 1 ? self::comment_page_url( $current - 1 ) . '#discussion' : '';
			$next = $current < $total ? self::comment_page_url( $current + 1 ) . '#discussion' : '';

			$html  = '<nav class="ts-archive-pagination ts-comment-pagination" aria-label="' . esc_attr__( 'Comment pagination', 'epiktetos' ) . '">';
			$html .= $prev ? '<a class="ts-archive-pagination__link ts-archive-pagination__prev" href="' . esc_url( $prev ) . '" rel="prev">' . esc_html__( 'Previous', 'epiktetos' ) . '</a>' : '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__prev" aria-disabled="true">' . esc_html__( 'Previous', 'epiktetos' ) . '</span>';
			$html .= '<span class="ts-archive-pagination__count" aria-current="page">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'epiktetos' ), $current, $total ) ) . '</span>';
			$html .= $next ? '<a class="ts-archive-pagination__link ts-archive-pagination__next" href="' . esc_url( $next ) . '" rel="next">' . esc_html__( 'Next', 'epiktetos' ) . '</a>' : '<span class="ts-archive-pagination__link ts-archive-pagination__link--disabled ts-archive-pagination__next" aria-disabled="true">' . esc_html__( 'Next', 'epiktetos' ) . '</span>';
			$html .= '</nav>';

			return $html;
		}

		protected static function comment_page_count( $comments ) {
			if ( ! get_option( 'page_comments' ) ) {
				return 1;
			}

			$per_page = max( 1, (int) get_option( 'comments_per_page' ) );
			$threaded = (int) self::get( 'enable_threaded_replies' );
			$count    = count( $comments );

			if ( $threaded ) {
				$tree  = self::comment_tree( $comments );
				$count = count( $tree[0] );
			}

			return max( 1, (int) ceil( $count / $per_page ) );
		}

		protected static function current_comment_page( $total_pages = 0 ) {
			$current = max( 1, (int) get_query_var( 'cpage' ) );
			if ( $total_pages > 0 ) {
				$current = min( $current, (int) $total_pages );
			}
			return $current;
		}

		protected static function render_form( $post_id ) {
			$commenter = wp_get_current_commenter();
			$required  = get_option( 'require_name_email' );
			$req       = $required ? ' required' : '';

			$fields = array(
				'author' => '<p class="ts-discuss-field ts-discuss-field--half"><label for="author">' . esc_html__( 'Name', 'epiktetos' ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label><input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" autocomplete="name"' . $req . ' /></p>',
				'email'  => '<p class="ts-discuss-field ts-discuss-field--half"><label for="email">' . esc_html__( 'Email', 'epiktetos' ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label><input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" autocomplete="email"' . $req . ' /></p>',
			);
			if ( (int) self::get( 'show_website_field' ) ) {
				$fields['url'] = '<p class="ts-discuss-field"><label for="url">' . esc_html__( 'Website', 'epiktetos' ) . ' <span>' . esc_html__( 'optional', 'epiktetos' ) . '</span></label><input id="url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" autocomplete="url" /></p>';
			}

			$args = array(
				'id_form'              => 'commentform',
				'class_container'      => 'ts-discuss-respond',
				'class_form'           => 'ts-discuss-form',
				'id_submit'            => 'ts-discuss-submit',
				'class_submit'         => 'ts-discuss-submit',
				'name_submit'          => 'submit',
				'label_submit'         => __( 'Publish Comment', 'epiktetos' ),
				'title_reply'          => __( 'Share a response', 'epiktetos' ),
				'title_reply_to'       => __( 'Reply to %s', 'epiktetos' ),
				'cancel_reply_link'    => __( 'Cancel reply', 'epiktetos' ),
				'title_reply_before'   => '<h3 id="reply-title" class="ts-discuss-respond__title">',
				'title_reply_after'    => '</h3>',
				'comment_notes_before' => '<fieldset class="ts-discuss-fieldset"><legend>' . esc_html__( 'Comment details', 'epiktetos' ) . '</legend><p class="ts-discuss-note">' . esc_html( self::get( 'moderation_text' ) ) . '</p>',
				'comment_notes_after'  => '',
				'fields'               => $fields,
				'comment_field'        => '<p class="ts-discuss-field ts-discuss-field--comment"><label for="comment">' . esc_html__( 'Comment', 'epiktetos' ) . '</label><textarea id="comment" name="comment" rows="5" maxlength="65525" required data-ts-comment-textarea data-ts-comment-storage="' . esc_attr( 'epiktetos-comment-' . (int) $post_id ) . '"></textarea><span class="ts-discuss-counter" data-ts-comment-counter aria-live="polite">0</span></p>',
				'submit_field'         => '<div class="ts-discuss-submit-row">%1$s%2$s</div><div class="ts-discuss-success" data-ts-comment-status aria-live="polite"></div></fieldset>',
				'format'               => 'html5',
			);

			add_filter( 'comment_form_fields', array( __CLASS__, 'order_form_fields' ) );
			ob_start();
			comment_form( $args, $post_id );
			$form = ob_get_clean();
			remove_filter( 'comment_form_fields', array( __CLASS__, 'order_form_fields' ) );

			return str_replace( array( '<!-- #respond -->', '<!-- #comments -->' ), '', $form );
		}

		public static function order_form_fields( $fields ) {
			$ordered = array();
			foreach ( array( 'author', 'email', 'url', 'comment', 'cookies' ) as $key ) {
				if ( isset( $fields[ $key ] ) ) {
					$ordered[ $key ] = $fields[ $key ];
				}
			}
			foreach ( $fields as $key => $field ) {
				if ( ! isset( $ordered[ $key ] ) ) {
					$ordered[ $key ] = $field;
				}
			}
			return $ordered;
		}

		protected static function render_notice() {
			if ( isset( $_GET['unapproved'] ) ) {
				return '<div class="ts-discussion__notice" role="status"><p>' . esc_html__( 'Your response is awaiting moderation.', 'epiktetos' ) . '</p></div>';
			}
			if ( isset( $_GET['replytocom'] ) ) {
				return '<div class="ts-discussion__notice" role="status"><p>' . esc_html__( 'Reply mode is active.', 'epiktetos' ) . '</p></div>';
			}
			return '';
		}

		protected static function comment_count_label( $count ) {
			return sprintf( _n( '%d Comment', '%d Comments', (int) $count, 'epiktetos' ), (int) $count );
		}

		protected static function primary_category( $post ) {
			$cats = get_the_category( $post->ID );
			return ! empty( $cats ) ? $cats[0] : null;
		}

		protected static function compress( $html ) {
			$html = preg_replace( '/>\s+</', '><', $html );
			$html = str_replace( array( "\n", "\r", "\t" ), '', $html );
			return trim( $html );
		}
	}

	Epiktetos_Comments::init();
}
