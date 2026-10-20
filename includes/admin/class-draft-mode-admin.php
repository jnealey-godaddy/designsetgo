<?php
/**
 * Draft Mode Admin Class
 *
 * Handles admin UI for draft mode (page list columns, row actions, scripts).
 *
 * @package DesignSetGo
 * @since 1.4.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draft Mode Admin Class
 */
class Draft_Mode_Admin {
	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * Constructor
	 *
	 * @param Draft_Mode $draft_mode Draft Mode instance.
	 */
	public function __construct( Draft_Mode $draft_mode ) {
		$this->draft_mode = $draft_mode;

		// Drafts exist even when the feature is off, and so can a blocked publish.
		add_action( 'admin_notices', array( $this, 'render_blocked_publish_notice' ) );

		if ( ! $this->draft_mode->is_enabled() ) {
			return;
		}

		$settings = $this->draft_mode->get_settings();

		if ( $settings['show_page_list_actions'] ) {
			add_filter( 'page_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
		}

		if ( $settings['show_page_list_column'] ) {
			add_filter( 'manage_pages_columns', array( $this, 'add_draft_status_column' ) );
			add_action( 'manage_pages_custom_column', array( $this, 'render_draft_status_column' ), 10, 2 );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Explain why a draft copy was kept as a draft instead of being published.
	 */
	public function render_blocked_publish_notice() {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! get_user_meta( $user_id, Draft_Mode::USER_META_BLOCKED_NOTICE, true ) ) {
			return;
		}

		delete_user_meta( $user_id, Draft_Mode::USER_META_BLOCKED_NOTICE );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__( 'A draft copy of a live page cannot be published directly, so it was kept as a draft. Open the draft in the editor and use Publish there to replace the live page with it.', 'designsetgo' )
		);
	}

	/**
	 * Add row actions to page list
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array Modified actions.
	 */
	public function add_row_actions( $actions, $post ) {
		if ( 'page' !== $post->post_type || ! current_user_can( 'publish_pages' ) ) {
			return $actions;
		}

		$original_id = get_post_meta( $post->ID, Draft_Mode::META_DRAFT_OF, true );

		if ( $original_id ) {
			$original = get_post( $original_id );
			if ( $original ) {
				$actions['dsgo_view_original'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_permalink( $original_id ) ),
					esc_html__( 'View Live', 'designsetgo' )
				);
			}
			return $actions;
		}

		if ( 'publish' === $post->post_status ) {
			$draft = $this->draft_mode->get_draft( $post->ID );

			if ( $draft ) {
				$actions['dsgo_edit_draft'] = sprintf(
					'<a href="%s" style="color: #2271b1; font-weight: 500;">%s</a>',
					esc_url( get_edit_post_link( $draft->ID, 'raw' ) ),
					esc_html__( 'Edit Draft', 'designsetgo' )
				);
			} else {
				$actions['dsgo_create_draft'] = sprintf(
					'<a href="#" class="dsgo-create-draft" data-post-id="%d" data-nonce="%s">%s</a>',
					$post->ID,
					wp_create_nonce( 'wp_rest' ),
					esc_html__( 'Create Draft', 'designsetgo' )
				);
			}
		}

		return $actions;
	}

	/**
	 * Add draft status column to pages list
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_draft_status_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['dsgo_draft_status'] = __( 'Draft Status', 'designsetgo' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render draft status column
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 */
	public function render_draft_status_column( $column_name, $post_id ) {
		if ( 'dsgo_draft_status' !== $column_name ) {
			return;
		}

		$post        = get_post( $post_id );
		$original_id = get_post_meta( $post_id, Draft_Mode::META_DRAFT_OF, true );

		if ( $original_id ) {
			$original = get_post( $original_id );
			if ( $original ) {
				printf(
					'<span class="dsgo-draft-badge dsgo-draft-badge--is-draft" title="%s">%s</span>',
					// translators: %s is the original post title.
					esc_attr( sprintf( __( 'Draft of: %s', 'designsetgo' ), $original->post_title ) ),
					esc_html__( 'Draft Version', 'designsetgo' )
				);
			}
			return;
		}

		if ( 'publish' === $post->post_status ) {
			$draft = $this->draft_mode->get_draft( $post_id );
			if ( $draft ) {
				$created = get_post_meta( $draft->ID, Draft_Mode::META_DRAFT_CREATED, true );
				printf(
					'<span class="dsgo-draft-badge dsgo-draft-badge--has-draft" title="%s">%s</span>',
					// translators: %s is the date the draft was created.
					esc_attr( sprintf( __( 'Draft created: %s', 'designsetgo' ), $created ) ),
					esc_html__( 'Has Draft', 'designsetgo' )
				);
				return;
			}
		}

		echo '<span class="dsgo-draft-badge dsgo-draft-badge--none">&mdash;</span>';
	}

	/**
	 * Enqueue admin scripts for draft mode functionality
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->get_inline_styles() );

		// Register and enqueue a dedicated script for draft mode page list actions.
		wp_register_script( 'dsgo-draft-mode-admin', '', array(), DESIGNSETGO_VERSION, true );
		wp_enqueue_script( 'dsgo-draft-mode-admin' );
		wp_add_inline_script( 'dsgo-draft-mode-admin', $this->get_inline_script() );
	}

	/**
	 * Get inline styles for admin
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_styles() {
		return '
			.dsgo-draft-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 12px;
				line-height: 1.4;
			}
			.dsgo-draft-badge--is-draft {
				background: #fff3cd;
				color: #856404;
			}
			.dsgo-draft-badge--has-draft {
				background: #cce5ff;
				color: #004085;
			}
			.dsgo-draft-badge--none {
				color: #999;
			}
			.dsgo-create-draft.loading {
				opacity: 0.5;
				pointer-events: none;
			}
		';
	}

	/**
	 * Get inline script for admin
	 *
	 * @return string JavaScript code.
	 */
	private function get_inline_script() {
		$creating_text     = esc_js( __( 'Creating...', 'designsetgo' ) );
		$create_draft_text = esc_js( __( 'Create Draft', 'designsetgo' ) );
		$failed_text       = esc_js( __( 'Failed to create draft.', 'designsetgo' ) );
		$dismiss_notice    = esc_js( __( 'Dismiss this notice.', 'designsetgo' ) );
		$rest_url          = esc_url( rest_url( 'designsetgo/v1/draft-mode/create' ) );

		return "
			(function() {
				/**
				 * Show accessible admin notice instead of alert()
				 *
				 * @param {string} message The message to display.
				 * @param {string} type The notice type (error, success, warning, info).
				 */
				function dsgoShowAdminNotice(message, type) {
					type = type || 'error';
					var wpbody = document.getElementById('wpbody-content');
					if (!wpbody) {
						return;
					}

					var containerId = 'dsgo-draft-mode-notices';
					var container = document.getElementById(containerId);
					if (!container) {
						container = document.createElement('div');
						container.id = containerId;
						container.setAttribute('aria-live', 'polite');
						container.setAttribute('aria-atomic', 'true');
						wpbody.insertBefore(container, wpbody.firstChild);
					}

					var notice = document.createElement('div');
					notice.className = 'notice notice-' + type + ' is-dismissible';
					notice.setAttribute('role', 'alert');

					var p = document.createElement('p');
					p.textContent = message;
					notice.appendChild(p);

					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'notice-dismiss';
					button.innerHTML = '<span class=\"screen-reader-text\">{$dismiss_notice}</span>';
					button.addEventListener('click', function() {
						if (notice.parentNode) {
							notice.parentNode.removeChild(notice);
						}
					});
					notice.appendChild(button);

					container.appendChild(notice);

					// Use WordPress accessibility speak if available.
					if (window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
						wp.a11y.speak(message, 'assertive');
					}
				}

				document.addEventListener('click', function(e) {
					if (!e.target.classList.contains('dsgo-create-draft')) {
						return;
					}
					e.preventDefault();

					var link = e.target;
					if (link.classList.contains('loading')) {
						return;
					}

					var postId = link.dataset.postId;
					var nonce = link.dataset.nonce;

					link.classList.add('loading');
					link.textContent = '{$creating_text}';

					fetch('{$rest_url}', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce
						},
						body: JSON.stringify({ post_id: parseInt(postId, 10) })
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data && data.success && data.edit_url) {
							window.location.href = data.edit_url;
						} else {
							dsgoShowAdminNotice(data && data.message ? data.message : '{$failed_text}', 'error');
							link.classList.remove('loading');
							link.textContent = '{$create_draft_text}';
						}
					})
					.catch(function() {
						dsgoShowAdminNotice('{$failed_text}', 'error');
						link.classList.remove('loading');
						link.textContent = '{$create_draft_text}';
					});
				});
			})();
		";
	}
}
