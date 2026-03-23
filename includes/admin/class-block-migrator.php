<?php
/**
 * Block Migrator Class
 *
 * Scans and converts DesignSetGo blocks to core equivalents during plugin deactivation.
 *
 * @package DesignSetGo
 * @since 2.0.27
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Migrator Class
 *
 * Provides AJAX endpoints and utilities for scanning and converting
 * DesignSetGo blocks to their core WordPress equivalents.
 */
class Block_Migrator {

	/**
	 * List of DesignSetGo blocks that can be converted to core equivalents.
	 *
	 * @var array
	 */
	const CONVERTIBLE_BLOCKS = array(
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		'designsetgo/icon-button',
	);

	/**
	 * Post types to scan for DesignSetGo blocks.
	 *
	 * @var array
	 */
	const POST_TYPES = array(
		'post',
		'page',
		'wp_template',
		'wp_template_part',
		'wp_block',
	);

	/**
	 * Number of posts to process per batch during conversion.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Constructor
	 *
	 * Registers AJAX handlers and admin script enqueue hook.
	 */
	public function __construct() {
		add_action( 'wp_ajax_designsetgo_scan_blocks', array( $this, 'ajax_scan_blocks' ) );
		add_action( 'wp_ajax_designsetgo_convert_blocks', array( $this, 'ajax_convert_blocks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_deactivation_scripts' ) );
	}

	/**
	 * AJAX handler for scanning posts that contain DesignSetGo blocks.
	 *
	 * Returns a count of posts and total blocks found. Processes posts in
	 * batches to avoid loading all post content into memory at once.
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function ajax_scan_blocks() {
		check_ajax_referer( 'designsetgo_block_migrator', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'designsetgo' ) ) );
		}

		$total_sql_rows = $this->count_matching_posts();
		$total_posts    = 0;
		$total_blocks   = 0;
		$offset         = 0;

		// Process in batches to avoid loading all post_content into memory.
		while ( $offset < $total_sql_rows ) {
			$results = $this->scan_for_dsgo_blocks( self::BATCH_SIZE, $offset );

			$total_posts += count( $results );

			foreach ( $results as $result ) {
				$total_blocks += $result['count'];
			}

			$offset += self::BATCH_SIZE;
		}

		wp_send_json_success(
			array(
				'posts'  => $total_posts,
				'blocks' => $total_blocks,
			)
		);
	}

	/**
	 * AJAX handler for converting DesignSetGo blocks to core equivalents.
	 *
	 * Processes one batch of posts per request. The client drives batching by
	 * sending 'offset' and 'batch_size' parameters. WordPress automatically
	 * creates revisions for rollback.
	 *
	 * @since 2.0.27
	 * @return void Sends JSON response and exits.
	 */
	public function ajax_convert_blocks() {
		check_ajax_referer( 'designsetgo_block_migrator', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'designsetgo' ) ) );
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : self::BATCH_SIZE;
		$batch_size = max( 10, min( $batch_size, 100 ) ); // Clamp between 10 and 100.

		$total_matching = $this->count_matching_posts();
		$results        = $this->scan_for_dsgo_blocks( $batch_size, $offset );
		$converted      = 0;
		$failed         = 0;

		foreach ( $results as $result ) {
			$post = get_post( $result['post_id'] );

			if ( ! $post ) {
				++$failed;
				continue;
			}

			$blocks           = parse_blocks( $post->post_content );
			$converted_blocks = $this->convert_blocks_recursive( $blocks );
			$new_content      = serialize_blocks( $converted_blocks );

			$update_result = wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $new_content,
				),
				true
			);

			if ( is_wp_error( $update_result ) ) {
				++$failed;
				error_log(
					sprintf(
						'DesignSetGo Block Migrator: Failed to convert post ID %d: %s',
						$post->ID,
						$update_result->get_error_message()
					)
				);
			} else {
				++$converted;
			}
		}

		$next_offset = $offset + $batch_size;
		$has_more    = $next_offset < $total_matching;

		wp_send_json_success(
			array(
				'converted'  => $converted,
				'failed'     => $failed,
				'hasMore'    => $has_more,
				'nextOffset' => $has_more ? $next_offset : 0,
				'total'      => $total_matching,
			)
		);
	}

	/**
	 * Enqueue scripts for the deactivation modal.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_deactivation_scripts( $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->get_modal_styles() );

		wp_register_script( 'dsgo-deactivation-modal', '', array(), DESIGNSETGO_VERSION, true );
		wp_enqueue_script( 'dsgo-deactivation-modal' );

		wp_localize_script(
			'dsgo-deactivation-modal',
			'dsgoDeactivation',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'designsetgo_block_migrator' ),
				'pluginBasename' => DESIGNSETGO_BASENAME,
				'batchSize'      => self::BATCH_SIZE,
				'strings'        => array(
					'title'         => __( 'Deactivate DesignSetGo', 'designsetgo' ),
					'intro'         => __( 'If your content uses DesignSetGo blocks, we can convert them to standard WordPress blocks before deactivating. This keeps your layouts intact.', 'designsetgo' ),
					'scanBtn'       => __( 'Scan & Convert Blocks', 'designsetgo' ),
					'scanning'      => __( 'Scanning your content for DesignSetGo blocks...', 'designsetgo' ),
					// translators: %blocks% is the number of blocks, %posts% is the number of posts.
					'found'         => __( 'Found %blocks% DesignSetGo blocks in %posts% posts.', 'designsetgo' ),
					'noBlocks'      => __( 'No DesignSetGo blocks found in your content. Safe to deactivate!', 'designsetgo' ),
					'warning'       => __( 'Some DesignSetGo-specific features (animations, shape dividers, icons) will be removed during conversion. Post revisions are created automatically so you can undo changes.', 'designsetgo' ),
					'convertBtn'    => __( 'Convert & Deactivate', 'designsetgo' ),
					'deactivateBtn' => __( 'Just Deactivate', 'designsetgo' ),
					'cancel'        => __( 'Cancel', 'designsetgo' ),
					'converting'    => __( 'Converting blocks...', 'designsetgo' ),
					// translators: %converted% is the number converted so far, %total% is the total number.
					'progress'      => __( 'Converting... %converted% of %total% posts processed', 'designsetgo' ),
					// translators: %count% is the number of posts converted.
					'success'       => __( 'Successfully converted blocks in %count% posts. Deactivating...', 'designsetgo' ),
					// translators: %converted% is the number converted, %failed% is the number that failed.
					'partial'       => __( 'Converted %converted% posts, %failed% failed. Deactivating...', 'designsetgo' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- Not printf placeholders.
					'scanError'     => __( 'Failed to scan content. You can still deactivate normally.', 'designsetgo' ),
					'convertError'  => __( 'Conversion failed. Your blocks have not been modified.', 'designsetgo' ),
				),
			)
		);

		wp_add_inline_script( 'dsgo-deactivation-modal', $this->get_modal_script() );
	}

	/**
	 * Get CSS styles for the deactivation modal.
	 *
	 * @since 2.0.27
	 * @return string CSS styles string.
	 */
	private function get_modal_styles() {
		return '
			.dsgo-deactivation-overlay {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0, 0, 0, 0.7);
				z-index: 100000;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.dsgo-deactivation-modal {
				background: #fff;
				border-radius: 8px;
				padding: 24px 32px;
				max-width: 520px;
				width: 90%;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
			}
			.dsgo-deactivation-modal h2 {
				margin: 0 0 16px;
				font-size: 18px;
			}
			.dsgo-deactivation-modal p {
				color: #50575e;
				font-size: 13px;
			}
			.dsgo-warning {
				border-left: 4px solid #dba617;
				background: #fcf9e8;
				padding: 12px 16px;
				margin: 12px 0;
			}
			.dsgo-modal-actions {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
				margin-top: 20px;
			}
			.dsgo-spinner {
				display: inline-flex;
				align-items: center;
				gap: 8px;
			}
			.dsgo-result.success {
				color: #00a32a;
			}
			.dsgo-result.error {
				color: #d63638;
			}
		';
	}

	/**
	 * Get JavaScript for the deactivation modal.
	 *
	 * @since 2.0.27
	 * @return string JavaScript code string.
	 */
	private function get_modal_script() {
		// All translatable strings are passed via wp_localize_script() as config.strings
		// to avoid esc_js() HTML-entity encoding issues with textContent.
		return <<<'JS'
(function() {
	'use strict';

	var config = window.dsgoDeactivation;
	if (!config) {
		return;
	}

	var deactivateLink = document.querySelector('tr[data-plugin="' + config.pluginBasename + '"] .deactivate a');
	if (!deactivateLink) {
		return;
	}

	var deactivateUrl = deactivateLink.href;

	deactivateLink.addEventListener('click', function(e) {
		e.preventDefault();
		showModal();
	});

	function showModal() {
		var overlay = document.createElement('div');
		overlay.className = 'dsgo-deactivation-overlay';

		var modal = document.createElement('div');
		modal.className = 'dsgo-deactivation-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', 'dsgo-modal-title');

		var heading = document.createElement('h2');
		heading.id = 'dsgo-modal-title';
		heading.textContent = config.strings.title;
		modal.appendChild(heading);

		var body = document.createElement('div');
		body.className = 'dsgo-modal-body';

		var introP = document.createElement('p');
		introP.textContent = config.strings.intro;
		body.appendChild(introP);

		var actions = document.createElement('div');
		actions.className = 'dsgo-modal-actions';

		var scanBtn = document.createElement('button');
		scanBtn.type = 'button';
		scanBtn.className = 'button';
		scanBtn.textContent = config.strings.scanBtn;
		scanBtn.addEventListener('click', function() {
			startScan(overlay);
		});
		actions.appendChild(scanBtn);

		var deactLink = document.createElement('a');
		deactLink.href = deactivateUrl;
		deactLink.className = 'button button-primary';
		deactLink.textContent = config.strings.deactivateBtn;
		actions.appendChild(deactLink);

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button button-link';
		cancelBtn.textContent = config.strings.cancel;
		cancelBtn.addEventListener('click', function() {
			closeModal(overlay);
		});
		actions.appendChild(cancelBtn);

		body.appendChild(actions);
		modal.appendChild(body);
		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		overlay.addEventListener('click', function(e) {
			if (e.target === overlay) {
				closeModal(overlay);
			}
		});

		// Store handler reference on overlay for cleanup in closeModal.
		overlay._dsgoKeydown = function(e) {
			if (e.key === 'Escape') {
				closeModal(overlay);
				return;
			}

			// Focus trap: keep Tab/Shift+Tab within the modal.
			if (e.key === 'Tab') {
				var focusable = modal.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
				if (focusable.length === 0) {
					e.preventDefault();
					return;
				}

				var first = focusable[0];
				var last = focusable[focusable.length - 1];

				if (e.shiftKey) {
					if (document.activeElement === first || !modal.contains(document.activeElement)) {
						e.preventDefault();
						last.focus();
					}
				} else {
					if (document.activeElement === last || !modal.contains(document.activeElement)) {
						e.preventDefault();
						first.focus();
					}
				}
			}
		};

		document.addEventListener('keydown', overlay._dsgoKeydown);

		scanBtn.focus();
	}

	function startScan(overlay) {
		var modal = overlay.querySelector('.dsgo-deactivation-modal');
		var body = modal.querySelector('.dsgo-modal-body');
		body.textContent = '';

		var spinnerWrap = document.createElement('div');
		spinnerWrap.className = 'dsgo-spinner';

		var spinner = document.createElement('span');
		spinner.className = 'spinner is-active';
		spinnerWrap.appendChild(spinner);

		var scanText = document.createElement('span');
		scanText.textContent = config.strings.scanning;
		spinnerWrap.appendChild(scanText);

		body.appendChild(spinnerWrap);

		doAjax('designsetgo_scan_blocks', {}, function(data) {
			if (data.blocks === 0) {
				body.textContent = '';

				var noBlocksP = document.createElement('p');
				noBlocksP.className = 'dsgo-result success';
				noBlocksP.textContent = config.strings.noBlocks;
				body.appendChild(noBlocksP);

				var actions = document.createElement('div');
				actions.className = 'dsgo-modal-actions';

				var deactLink = document.createElement('a');
				deactLink.href = deactivateUrl;
				deactLink.className = 'button button-primary';
				deactLink.textContent = config.strings.deactivateBtn;
				actions.appendChild(deactLink);

				body.appendChild(actions);
				deactLink.focus();
				return;
			}
			showSummary(overlay, data.posts, data.blocks);
		}, function() {
			body.textContent = '';

			var errorP = document.createElement('p');
			errorP.className = 'dsgo-result error';
			errorP.textContent = config.strings.scanError;
			body.appendChild(errorP);

			var actions = document.createElement('div');
			actions.className = 'dsgo-modal-actions';

			var deactLink = document.createElement('a');
			deactLink.href = deactivateUrl;
			deactLink.className = 'button';
			deactLink.textContent = config.strings.deactivateBtn;
			actions.appendChild(deactLink);

			var cancelBtn = document.createElement('button');
			cancelBtn.type = 'button';
			cancelBtn.className = 'button';
			cancelBtn.textContent = config.strings.cancel;
			cancelBtn.addEventListener('click', function() {
				closeModal(overlay);
			});
			actions.appendChild(cancelBtn);

			body.appendChild(actions);
		});
	}

	function showSummary(overlay, posts, blocks) {
		var modal = overlay.querySelector('.dsgo-deactivation-modal');
		var body = modal.querySelector('.dsgo-modal-body');
		body.textContent = '';

		var foundText = config.strings.found.replace('%blocks%', blocks).replace('%posts%', posts);
		var foundP = document.createElement('p');
		foundP.textContent = foundText;
		body.appendChild(foundP);

		var warningDiv = document.createElement('div');
		warningDiv.className = 'dsgo-warning';
		var warningP = document.createElement('p');
		warningP.textContent = config.strings.warning;
		warningDiv.appendChild(warningP);
		body.appendChild(warningDiv);

		var actions = document.createElement('div');
		actions.className = 'dsgo-modal-actions';

		var deactLink = document.createElement('a');
		deactLink.href = deactivateUrl;
		deactLink.className = 'button button-primary';
		deactLink.textContent = config.strings.deactivateBtn;
		actions.appendChild(deactLink);

		var convertBtn = document.createElement('button');
		convertBtn.type = 'button';
		convertBtn.className = 'button';
		convertBtn.textContent = config.strings.convertBtn;
		convertBtn.addEventListener('click', function() {
			startConversion(overlay, posts);
		});
		actions.appendChild(convertBtn);

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = config.strings.cancel;
		cancelBtn.addEventListener('click', function() {
			closeModal(overlay);
		});
		actions.appendChild(cancelBtn);

		body.appendChild(actions);

		deactLink.focus();
	}

	function startConversion(overlay, totalPosts) {
		var modal = overlay.querySelector('.dsgo-deactivation-modal');
		var body = modal.querySelector('.dsgo-modal-body');
		body.textContent = '';

		var spinnerWrap = document.createElement('div');
		spinnerWrap.className = 'dsgo-spinner';

		var spinner = document.createElement('span');
		spinner.className = 'spinner is-active';
		spinnerWrap.appendChild(spinner);

		var progressText = document.createElement('span');
		progressText.textContent = config.strings.converting;
		spinnerWrap.appendChild(progressText);

		body.appendChild(spinnerWrap);

		var totalConverted = 0;
		var totalFailed = 0;

		function processBatch(offset) {
			doAjax('designsetgo_convert_blocks', { offset: offset, batch_size: config.batchSize }, function(data) {
				totalConverted += data.converted;
				totalFailed += data.failed;

				if (data.hasMore) {
					progressText.textContent = config.strings.progress
						.replace('%converted%', totalConverted)
						.replace('%total%', totalPosts);
					processBatch(data.nextOffset);
					return;
				}

				// All batches done.
				body.textContent = '';

				var resultP = document.createElement('p');
				resultP.className = 'dsgo-result success';

				if (totalFailed === 0) {
					resultP.textContent = config.strings.success.replace('%count%', totalConverted);
				} else {
					resultP.textContent = config.strings.partial.replace('%converted%', totalConverted).replace('%failed%', totalFailed);
				}

				body.appendChild(resultP);

				setTimeout(function() {
					window.location.href = deactivateUrl;
				}, 1500);
			}, function() {
				body.textContent = '';

				var errorP = document.createElement('p');
				errorP.className = 'dsgo-result error';
				errorP.textContent = config.strings.convertError;
				body.appendChild(errorP);

				// Show partial results if some batches succeeded.
				if (totalConverted > 0) {
					var partialP = document.createElement('p');
					partialP.textContent = config.strings.partial.replace('%converted%', totalConverted).replace('%failed%', totalFailed + '?');
					body.appendChild(partialP);
				}

				var actions = document.createElement('div');
				actions.className = 'dsgo-modal-actions';

				var deactLink = document.createElement('a');
				deactLink.href = deactivateUrl;
				deactLink.className = 'button';
				deactLink.textContent = config.strings.deactivateBtn;
				actions.appendChild(deactLink);

				var cancelBtn = document.createElement('button');
				cancelBtn.type = 'button';
				cancelBtn.className = 'button';
				cancelBtn.textContent = config.strings.cancel;
				cancelBtn.addEventListener('click', function() {
					closeModal(overlay);
				});
				actions.appendChild(cancelBtn);

				body.appendChild(actions);
			});
		}

		processBatch(0);
	}

	function closeModal(overlay) {
		if (overlay && overlay._dsgoKeydown) {
			document.removeEventListener('keydown', overlay._dsgoKeydown);
		}
		if (overlay && overlay.parentNode) {
			overlay.parentNode.removeChild(overlay);
		}
		// Restore focus to the original deactivate link for keyboard/screen-reader users.
		if (deactivateLink) {
			deactivateLink.focus();
		}
	}

	function doAjax(action, extraData, onSuccess, onError) {
		var formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', config.nonce);

		if (extraData) {
			for (var key in extraData) {
				if (extraData.hasOwnProperty(key)) {
					formData.append(key, extraData[key]);
				}
			}
		}

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(result) {
			if (result && result.success) {
				onSuccess(result.data);
			} else {
				onError();
			}
		})
		.catch(function() {
			onError();
		});
	}
})();
JS;
	}

	/**
	 * Scan relevant post types for posts containing DesignSetGo blocks.
	 *
	 * Queries the database for posts whose content contains any of the
	 * convertible block names, then parses each post to get an accurate count.
	 * Supports LIMIT/OFFSET for batched processing to avoid memory issues.
	 *
	 * @param int $limit  Maximum number of posts to return. 0 for no limit.
	 * @param int $offset Number of posts to skip.
	 * @return array Array of associative arrays with 'post_id' and 'count' keys.
	 */
	private function scan_for_dsgo_blocks( $limit = 0, $offset = 0 ) {
		global $wpdb;

		// Build LIKE conditions for each convertible block.
		$like_clauses = array();
		$like_values  = array();

		foreach ( self::CONVERTIBLE_BLOCKS as $block_name ) {
			$like_clauses[] = 'post_content LIKE %s';
			$like_values[]  = '%' . $wpdb->esc_like( 'wp:' . $block_name ) . '%';
		}

		$like_sql = implode( ' OR ', $like_clauses );

		// Build post type placeholders.
		$type_placeholders = implode( ', ', array_fill( 0, count( self::POST_TYPES ), '%s' ) );

		$statuses            = array( 'publish', 'draft', 'pending', 'future', 'private' );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// Merge all values for prepare: LIKE values, then post types, then statuses.
		$prepare_values = array_merge( $like_values, self::POST_TYPES, $statuses );

		$sql = "SELECT ID, post_content FROM {$wpdb->posts}
				WHERE ( {$like_sql} )
				AND post_type IN ( {$type_placeholders} )
				AND post_status IN ( {$status_placeholders} )
				ORDER BY ID ASC";

		if ( $limit > 0 ) {
			$sql             .= ' LIMIT %d OFFSET %d';
			$prepare_values[] = $limit;
			$prepare_values[] = $offset;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders generated from safe constants; $sql variable is built above from safe constants.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				...$prepare_values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $posts ) ) {
			return array();
		}

		$results = array();

		foreach ( $posts as $post ) {
			$blocks = parse_blocks( $post->post_content );
			$count  = $this->count_dsgo_blocks( $blocks );

			if ( $count > 0 ) {
				$results[] = array(
					'post_id' => (int) $post->ID,
					'count'   => $count,
				);
			}
		}

		return $results;
	}

	/**
	 * Count total matching posts without loading content.
	 *
	 * Lightweight query that returns only the count of posts containing
	 * DesignSetGo blocks, avoiding loading post_content into memory.
	 *
	 * @return int Number of posts matching the block query.
	 */
	private function count_matching_posts() {
		global $wpdb;

		$like_clauses = array();
		$like_values  = array();

		foreach ( self::CONVERTIBLE_BLOCKS as $block_name ) {
			$like_clauses[] = 'post_content LIKE %s';
			$like_values[]  = '%' . $wpdb->esc_like( 'wp:' . $block_name ) . '%';
		}

		$like_sql = implode( ' OR ', $like_clauses );

		$type_placeholders = implode( ', ', array_fill( 0, count( self::POST_TYPES ), '%s' ) );

		$statuses            = array( 'publish', 'draft', 'pending', 'future', 'private' );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$prepare_values = array_merge( $like_values, self::POST_TYPES, $statuses );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic placeholders generated from safe constants.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE ( {$like_sql} )
				AND post_type IN ( {$type_placeholders} )
				AND post_status IN ( {$status_placeholders} )",
				...$prepare_values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $count;
	}

	/**
	 * Recursively count DesignSetGo blocks in a parsed block tree.
	 *
	 * @param array $blocks Array of parsed block arrays from parse_blocks().
	 * @return int Total count of convertible DesignSetGo blocks found.
	 */
	private function count_dsgo_blocks( $blocks ) {
		$count = 0;

		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], self::CONVERTIBLE_BLOCKS, true ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_dsgo_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	/**
	 * Recursively convert DesignSetGo blocks to core equivalents.
	 *
	 * Uses depth-first traversal to convert inner blocks before their parents.
	 *
	 * @since 2.0.27
	 * @param array $blocks Array of parsed block arrays from parse_blocks().
	 * @return array Modified blocks array with DesignSetGo blocks replaced.
	 */
	private function convert_blocks_recursive( $blocks ) {
		foreach ( $blocks as $index => $block ) {
			// Depth-first: recurse into innerBlocks first.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $index ]['innerBlocks'] = $this->convert_blocks_recursive( $block['innerBlocks'] );
				$block                           = $blocks[ $index ];
			}

			switch ( $block['blockName'] ) {
				case 'designsetgo/section':
					$blocks[ $index ] = $this->convert_section( $block );
					break;

				case 'designsetgo/row':
					$blocks[ $index ] = $this->convert_row( $block );
					break;

				case 'designsetgo/grid':
					$blocks[ $index ] = $this->convert_grid( $block );
					break;

				case 'designsetgo/icon-button':
					$blocks[ $index ] = $this->convert_icon_button( $block );
					break;
			}
		}

		return $blocks;
	}

	/**
	 * Convert a designsetgo/section block to core/group with constrained layout.
	 *
	 * @since 2.0.27
	 * @param array $block The parsed section block.
	 * @return array The converted core/group block.
	 */
	private function convert_section( $block ) {
		$attrs           = $block['attrs'] ?? array();
		$constrain_width = $attrs['constrainWidth'] ?? true;

		if ( true === $constrain_width ) {
			$layout = array( 'type' => 'constrained' );

			if ( ! empty( $attrs['contentWidth'] ) ) {
				$layout['contentSize'] = $attrs['contentWidth'];
			}
		} else {
			$layout = array( 'type' => 'default' );
		}

		$new_attrs = $this->build_group_attrs( $attrs, $layout );

		return $this->build_group_block( $new_attrs, $block['innerBlocks'] ?? array() );
	}

	/**
	 * Convert a designsetgo/row block to core/group with flex layout.
	 *
	 * @since 2.0.27
	 * @param array $block The parsed row block.
	 * @return array The converted core/group block.
	 */
	private function convert_row( $block ) {
		$attrs      = $block['attrs'] ?? array();
		$dsg_layout = $attrs['layout'] ?? array();

		$layout = array(
			'type'     => 'flex',
			'flexWrap' => $dsg_layout['flexWrap'] ?? 'wrap',
		);

		if ( ! empty( $dsg_layout['justifyContent'] ) ) {
			$layout['justifyContent'] = $dsg_layout['justifyContent'];
		}

		if ( ! empty( $dsg_layout['verticalAlignment'] ) ) {
			$layout['verticalAlignment'] = $dsg_layout['verticalAlignment'];
		}

		$new_attrs = $this->build_group_attrs( $attrs, $layout );

		return $this->build_group_block( $new_attrs, $block['innerBlocks'] ?? array() );
	}

	/**
	 * Convert a designsetgo/grid block to core/group with grid layout.
	 *
	 * @since 2.0.27
	 * @param array $block The parsed grid block.
	 * @return array The converted core/group block.
	 */
	private function convert_grid( $block ) {
		$attrs = $block['attrs'] ?? array();

		$layout = array(
			'type'        => 'grid',
			'columnCount' => $attrs['desktopColumns'] ?? 3,
		);

		$new_attrs = $this->build_group_attrs( $attrs, $layout );

		return $this->build_group_block( $new_attrs, $block['innerBlocks'] ?? array() );
	}

	/**
	 * Convert a designsetgo/icon-button block to core/buttons wrapping core/button.
	 *
	 * @since 2.0.27
	 * @param array $block The parsed icon-button block.
	 * @return array The converted core/buttons block wrapping a core/button.
	 */
	private function convert_icon_button( $block ) {
		$attrs = $block['attrs'] ?? array();

		// Build core/button attributes.
		$button_attrs = array();

		if ( ! empty( $attrs['text'] ) ) {
			$button_attrs['text'] = $attrs['text'];
		}

		if ( ! empty( $attrs['url'] ) ) {
			$button_attrs['url'] = $attrs['url'];
		}

		if ( ! empty( $attrs['linkTarget'] ) && '_self' !== $attrs['linkTarget'] ) {
			$button_attrs['linkTarget'] = $attrs['linkTarget'];
		}

		if ( ! empty( $attrs['rel'] ) ) {
			$button_attrs['rel'] = $attrs['rel'];
		}

		if ( ! empty( $attrs['backgroundColor'] ) ) {
			$button_attrs['backgroundColor'] = $attrs['backgroundColor'];
		}

		if ( ! empty( $attrs['textColor'] ) ) {
			$button_attrs['textColor'] = $attrs['textColor'];
		}

		if ( ! empty( $attrs['fontSize'] ) ) {
			$button_attrs['fontSize'] = $attrs['fontSize'];
		}

		if ( ! empty( $attrs['anchor'] ) ) {
			$button_attrs['anchor'] = $attrs['anchor'];
		}

		if ( ! empty( $attrs['style'] ) ) {
			$button_attrs['style'] = $attrs['style'];
		}

		// Build button link classes.
		$link_classes = array( 'wp-block-button__link', 'wp-element-button' );

		if ( ! empty( $attrs['backgroundColor'] ) ) {
			$link_classes[] = 'has-' . $attrs['backgroundColor'] . '-background-color';
			$link_classes[] = 'has-background';
		}

		if ( ! empty( $attrs['textColor'] ) ) {
			$link_classes[] = 'has-' . $attrs['textColor'] . '-color';
			$link_classes[] = 'has-text-color';
		}

		if ( ! empty( $attrs['fontSize'] ) ) {
			$link_classes[] = 'has-' . $attrs['fontSize'] . '-font-size';
		}

		// Build inline styles for the link element.
		$link_styles = array();

		if ( ! empty( $attrs['style']['color']['background'] ) ) {
			$link_styles[] = 'background-color:' . $attrs['style']['color']['background'];
			if ( ! in_array( 'has-background', $link_classes, true ) ) {
				$link_classes[] = 'has-background';
			}
		}

		if ( ! empty( $attrs['style']['color']['text'] ) ) {
			$link_styles[] = 'color:' . $attrs['style']['color']['text'];
			if ( ! in_array( 'has-text-color', $link_classes, true ) ) {
				$link_classes[] = 'has-text-color';
			}
		}

		if ( ! empty( $attrs['style']['border']['radius'] ) && is_string( $attrs['style']['border']['radius'] ) ) {
			$link_styles[] = 'border-radius:' . $attrs['style']['border']['radius'];
		}

		$link_classes = array_unique( $link_classes );

		// Always use <a> tag to match core/button output.
		$tag = 'a';

		// Build the inner link HTML.
		$class_attr  = esc_attr( implode( ' ', $link_classes ) );
		$style_attr  = ! empty( $link_styles ) ? ' style="' . esc_attr( implode( ';', $link_styles ) ) . '"' : '';
		$href_attr   = '';
		$target_attr = '';

		if ( ! empty( $attrs['url'] ) ) {
			$href_attr = ' href="' . esc_url( $attrs['url'] ) . '"';
		}

		if ( ! empty( $attrs['linkTarget'] ) && '_self' !== $attrs['linkTarget'] ) {
			$target_attr = ' target="' . esc_attr( $attrs['linkTarget'] ) . '"';
		}

		if ( ! empty( $attrs['rel'] ) ) {
			$target_attr .= ' rel="' . esc_attr( $attrs['rel'] ) . '"';
		}

		$content   = wp_kses_post( $attrs['text'] ?? '' );
		$link_html = '<' . $tag . $href_attr . ' class="' . $class_attr . '"' . $style_attr . $target_attr . '>' . $content . '</' . $tag . '>';

		// core/button wraps the link in a <div class="wp-block-button">.
		$button_html = '<div class="wp-block-button">' . $link_html . '</div>';

		// Build the core/button block.
		$button_block = array(
			'blockName'    => 'core/button',
			'attrs'        => $button_attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $button_html,
			'innerContent' => array( $button_html ),
		);

		// Build core/buttons wrapper layout.
		$buttons_layout = array( 'type' => 'flex' );

		if ( ! empty( $attrs['align'] ) ) {
			$align_map = array(
				'left'   => 'left',
				'center' => 'center',
				'right'  => 'right',
			);

			if ( isset( $align_map[ $attrs['align'] ] ) ) {
				$buttons_layout['justifyContent'] = $align_map[ $attrs['align'] ];
			}
		}

		$buttons_attrs = array( 'layout' => $buttons_layout );

		// Build core/buttons wrapper HTML.
		$buttons_open  = '<div class="wp-block-buttons">';
		$buttons_close = '</div>';

		return array(
			'blockName'    => 'core/buttons',
			'attrs'        => $buttons_attrs,
			'innerBlocks'  => array( $button_block ),
			'innerHTML'    => $buttons_open . $buttons_close,
			'innerContent' => array( $buttons_open, null, $buttons_close ),
		);
	}

	/**
	 * Build attributes array for a core/group block.
	 *
	 * Preserves supported attributes from the source block and adds the layout.
	 *
	 * @since 2.0.27
	 * @param array $attrs  Source block attributes.
	 * @param array $layout Layout configuration for the group block.
	 * @return array Attributes array for the core/group block.
	 */
	private function build_group_attrs( $attrs, $layout ) {
		$new_attrs = array( 'layout' => $layout );

		$preserve_keys = array(
			'align',
			'tagName',
			'style',
			'backgroundColor',
			'textColor',
			'fontSize',
			'anchor',
		);

		foreach ( $preserve_keys as $key ) {
			if ( isset( $attrs[ $key ] ) && '' !== $attrs[ $key ] ) {
				$new_attrs[ $key ] = $attrs[ $key ];
			}
		}

		return $new_attrs;
	}

	/**
	 * Build a complete core/group block array with proper HTML markup.
	 *
	 * Generates the wrapper HTML with appropriate CSS classes and inline styles
	 * based on the block attributes.
	 *
	 * @since 2.0.27
	 * @param array $attrs        Attributes for the core/group block.
	 * @param array $inner_blocks Array of inner block arrays.
	 * @return array Complete core/group block array.
	 */
	private function build_group_block( $attrs, $inner_blocks ) {
		$tag     = $attrs['tagName'] ?? 'div';
		$classes = array( 'wp-block-group' );
		$styles  = array();

		// Add preset color classes.
		if ( ! empty( $attrs['backgroundColor'] ) ) {
			$classes[] = 'has-' . $attrs['backgroundColor'] . '-background-color';
			$classes[] = 'has-background';
		}

		if ( ! empty( $attrs['textColor'] ) ) {
			$classes[] = 'has-' . $attrs['textColor'] . '-color';
			$classes[] = 'has-text-color';
		}

		if ( ! empty( $attrs['fontSize'] ) ) {
			$classes[] = 'has-' . $attrs['fontSize'] . '-font-size';
		}

		// Build inline styles from the style attribute object.
		if ( ! empty( $attrs['style'] ) ) {
			$style = $attrs['style'];

			// Spacing: padding.
			if ( ! empty( $style['spacing']['padding'] ) && is_array( $style['spacing']['padding'] ) ) {
				foreach ( $style['spacing']['padding'] as $side => $value ) {
					if ( ! empty( $value ) ) {
						$styles[] = 'padding-' . $side . ':' . $this->convert_preset_to_css_var( $value );
					}
				}
			}

			// Spacing: margin.
			if ( ! empty( $style['spacing']['margin'] ) && is_array( $style['spacing']['margin'] ) ) {
				foreach ( $style['spacing']['margin'] as $side => $value ) {
					if ( ! empty( $value ) ) {
						$styles[] = 'margin-' . $side . ':' . $this->convert_preset_to_css_var( $value );
					}
				}
			}

			// Border: radius (string only).
			if ( ! empty( $style['border']['radius'] ) && is_string( $style['border']['radius'] ) ) {
				$styles[] = 'border-radius:' . $style['border']['radius'];
			}

			// Color: background (custom).
			if ( ! empty( $style['color']['background'] ) ) {
				$styles[] = 'background-color:' . $style['color']['background'];
				if ( ! in_array( 'has-background', $classes, true ) ) {
					$classes[] = 'has-background';
				}
			}

			// Color: text (custom).
			if ( ! empty( $style['color']['text'] ) ) {
				$styles[] = 'color:' . $style['color']['text'];
				if ( ! in_array( 'has-text-color', $classes, true ) ) {
					$classes[] = 'has-text-color';
				}
			}

			// Color: gradient (custom).
			if ( ! empty( $style['color']['gradient'] ) ) {
				$styles[] = 'background:' . $style['color']['gradient'];
				if ( ! in_array( 'has-background', $classes, true ) ) {
					$classes[] = 'has-background';
				}
			}
		}

		$classes = array_unique( $classes );

		// Build open and close tags.
		$class_str = esc_attr( implode( ' ', $classes ) );
		$style_str = ! empty( $styles ) ? ' style="' . esc_attr( implode( ';', $styles ) ) . '"' : '';
		$open_tag  = '<' . $tag . ' class="' . $class_str . '"' . $style_str . '>';
		$close_tag = '</' . $tag . '>';

		// Build innerContent array: open tag, one null per inner block, close tag.
		$inner_content    = array( $open_tag );
		$inner_html_parts = array( $open_tag );

		foreach ( $inner_blocks as $inner_block ) {
			$inner_content[]    = null;
			$inner_html_parts[] = '';
		}

		$inner_content[]    = $close_tag;
		$inner_html_parts[] = $close_tag;

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => implode( '', $inner_html_parts ),
			'innerContent' => $inner_content,
		);
	}

	/**
	 * Convert a WordPress preset value to a CSS custom property.
	 *
	 * Transforms values like 'var:preset|spacing|50' into
	 * 'var(--wp--preset--spacing--50)'.
	 *
	 * @since 2.0.27
	 * @param mixed $value The value to convert.
	 * @return string The converted CSS value, or the original value if not a preset.
	 */
	private function convert_preset_to_css_var( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return '';
		}

		if ( 0 === strpos( $value, 'var:' ) ) {
			$without_prefix = substr( $value, 4 );
			$css_var_path   = str_replace( '|', '--', $without_prefix );

			return 'var(--wp--' . $css_var_path . ')';
		}

		return $value;
	}
}
