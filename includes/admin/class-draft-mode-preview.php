<?php
/**
 * Draft Mode Preview Class
 *
 * Provides site-wide preview of draft changes for logged-in administrators.
 * When the AI agent (Airo) creates drafts of published pages, admins automatically
 * see draft content while navigating the frontend. Regular visitors always see
 * published content.
 *
 * Security note: Draft content is swapped into the post object without additional
 * sanitization (e.g., wp_kses_post) because draft content is created by users with
 * edit_pages capability, who are already trusted to author unfiltered HTML in WordPress.
 * Applying wp_kses_post would break block markup — see PR #166 for background.
 * This preview is only served to logged-in administrators.
 *
 * @package DesignSetGo
 * @since 2.0.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draft Mode Preview Class - Serves draft content on the frontend for admins
 */
class Draft_Mode_Preview {
	/**
	 * Cookie name for opting out of preview mode.
	 */
	const COOKIE_LIVE_MODE = 'dsgo_live_mode';

	/**
	 * Query parameter for toggling live mode.
	 */
	const PARAM_LIVE_MODE = 'dsgo_live';

	/**
	 * Transient key prefix for draft map cache.
	 */
	const TRANSIENT_DRAFT_MAP = 'dsgo_draft_map';

	/**
	 * Option key for the latest draft creation timestamp.
	 *
	 * Used to auto-reset users to preview mode when new drafts
	 * are created after they opted out.
	 */
	const OPTION_LAST_DRAFT_CREATED = 'dsgo_last_draft_created';

	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * Whether we're currently in preview mode for this request.
	 *
	 * @var bool|null
	 */
	private $is_preview_active = null;

	/**
	 * Cache of draft mappings (original_id => draft post).
	 *
	 * @var array|null
	 */
	private $draft_map = null;

	/**
	 * Constructor
	 *
	 * @param Draft_Mode $draft_mode Draft Mode instance.
	 */
	public function __construct( Draft_Mode $draft_mode ) {
		$this->draft_mode = $draft_mode;

		// Handle live mode toggle early (before template_redirect).
		add_action( 'init', array( $this, 'handle_live_mode_toggle' ) );

		// Register the_posts filter early so it catches the main query.
		// The frontend guard is inside the callback since is_admin() is
		// reliable at filter time but query conditionals are not yet
		// available when this constructor runs.
		add_filter( 'the_posts', array( $this, 'swap_draft_content' ), 10, 2 );

		// Register remaining frontend hooks after query context is set up.
		add_action( 'wp', array( $this, 'register_frontend_hooks' ) );

		// Invalidate draft map cache when drafts change.
		add_action( 'designsetgo_draft_created', array( $this, 'invalidate_draft_map_cache' ) );
		add_action( 'designsetgo_draft_published', array( $this, 'invalidate_draft_map_cache' ) );
		add_action( 'designsetgo_draft_discarded', array( $this, 'invalidate_draft_map_cache' ) );

		// Record timestamp when new drafts are created to auto-reset preview mode.
		add_action( 'designsetgo_draft_created', array( $this, 'record_draft_created_time' ) );
	}

	/**
	 * Register frontend-only hooks after WordPress context is available.
	 *
	 * Deferred to the 'wp' action to ensure is_admin() and query context
	 * are reliable (not available during plugin load).
	 */
	public function register_frontend_hooks() {
		// Only run on the frontend, not in admin or REST API contexts.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Check if frontend preview is enabled in settings.
		$settings = $this->draft_mode->get_settings();
		if ( empty( $settings['show_frontend_preview'] ) ) {
			return;
		}

		// Add the preview banner to the frontend.
		add_action( 'wp_footer', array( $this, 'render_preview_banner' ) );

		// Add body class server-side to prevent layout shift.
		add_filter( 'body_class', array( $this, 'add_preview_body_class' ) );

		// Also swap featured images.
		add_filter( 'post_thumbnail_id', array( $this, 'swap_thumbnail' ), 10, 2 );
	}

	/**
	 * Check if preview mode is active for the current request.
	 *
	 * Preview is active when:
	 * 1. Draft mode is enabled
	 * 2. User is logged in with edit_pages capability
	 * 3. There are active drafts
	 * 4. User hasn't opted out via the live mode cookie
	 *
	 * @return bool True if preview mode is active.
	 */
	public function is_preview_active() {
		if ( null !== $this->is_preview_active ) {
			return $this->is_preview_active;
		}

		// Must be a logged-in user with edit capability.
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			$this->is_preview_active = false;
			return false;
		}

		// Draft mode must be enabled.
		if ( ! $this->draft_mode->is_enabled() ) {
			$this->is_preview_active = false;
			return false;
		}

		// Check if user has opted out via cookie.
		if ( isset( $_COOKIE[ self::COOKIE_LIVE_MODE ] ) ) {
			$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_LIVE_MODE ] ) );
			if ( ! empty( $cookie_value ) ) {
				// Cookie stores the opt-out timestamp. If a new draft was
				// created after the user opted out, auto-reset to preview.
				$opt_out_time     = (int) $cookie_value;
				$last_draft_time  = (int) get_option( self::OPTION_LAST_DRAFT_CREATED, 0 );

				if ( 0 === $last_draft_time || $opt_out_time >= $last_draft_time ) {
					$this->is_preview_active = false;
					return false;
				}
				// New draft created after opt-out — fall through to preview mode.
			}
		}

		// Check if there are any active drafts.
		if ( empty( $this->get_draft_map() ) ) {
			$this->is_preview_active = false;
			return false;
		}

		$this->is_preview_active = true;
		return true;
	}

	/**
	 * Get a map of original post IDs to their draft posts.
	 *
	 * Uses transient caching (10 minutes) to avoid running the meta query
	 * on every page load. Cache is invalidated when drafts are created,
	 * published, or discarded via the designsetgo_draft_* action hooks.
	 *
	 * @return array Associative array of original_id => WP_Post (draft).
	 */
	private function get_draft_map() {
		if ( null !== $this->draft_map ) {
			return $this->draft_map;
		}

		// Try transient cache first.
		$cached = get_transient( self::TRANSIENT_DRAFT_MAP );
		if ( false !== $cached && is_array( $cached ) ) {
			// Cached value is an array of original_id => draft_id.
			// Hydrate with actual WP_Post objects.
			$this->draft_map = array();
			foreach ( $cached as $original_id => $draft_id ) {
				$draft = get_post( $draft_id );
				if ( $draft && 'draft' === $draft->post_status ) {
					$this->draft_map[ $original_id ] = $draft;
				}
			}
			return $this->draft_map;
		}

		$this->draft_map = array();

		// Allow the draft preview query limit to be filtered for larger sites.
		$posts_per_page = (int) apply_filters( 'designsetgo_preview_draft_limit', 100 );

		// Find all pages that have drafts.
		$pages_with_drafts = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded by posts_per_page (default 100, filterable), fetches IDs only, and the result is cached in a transient for 10 minutes; the meta lookup runs at most once per cache window.
				'meta_key'       => Draft_Mode::META_HAS_DRAFT,
				'posts_per_page' => $posts_per_page,
				'fields'         => 'ids',
			)
		);

		$cache_data = array();
		foreach ( $pages_with_drafts as $original_id ) {
			$draft = $this->draft_mode->get_draft( $original_id );
			if ( $draft ) {
				$this->draft_map[ $original_id ] = $draft;
				$cache_data[ $original_id ]      = $draft->ID;
			}
		}

		// Cache the ID mapping for 10 minutes.
		set_transient( self::TRANSIENT_DRAFT_MAP, $cache_data, 10 * MINUTE_IN_SECONDS );

		return $this->draft_map;
	}

	/**
	 * Invalidate the draft map transient cache.
	 *
	 * Hooked to designsetgo_draft_created, designsetgo_draft_published,
	 * and designsetgo_draft_discarded actions.
	 */
	public function invalidate_draft_map_cache() {
		delete_transient( self::TRANSIENT_DRAFT_MAP );
		$this->draft_map         = null;
		$this->is_preview_active = null;
	}

	/**
	 * Record the timestamp when a draft is created.
	 *
	 * This allows auto-resetting users to preview mode when new
	 * drafts appear after they opted out to live mode.
	 *
	 * Hooked to designsetgo_draft_created action.
	 */
	public function record_draft_created_time() {
		update_option( self::OPTION_LAST_DRAFT_CREATED, time(), false );
	}

	/**
	 * Handle the live mode toggle via query parameter.
	 *
	 * When ?dsgo_live=1 is present, set a cookie to opt out of preview.
	 * When ?dsgo_live=0 is present, remove the cookie to opt back in.
	 */
	public function handle_live_mode_toggle() {
		if ( ! isset( $_GET[ self::PARAM_LIVE_MODE ] ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// Verify nonce for the toggle action.
		$nonce = isset( $_GET['_dsgo_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_dsgo_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dsgo_live_mode_toggle' ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'designsetgo' ),
				esc_html__( 'Security Error', 'designsetgo' ),
				array( 'response' => 403 )
			);
		}

		$live_mode = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_LIVE_MODE ] ) );

		if ( '1' === $live_mode ) {
			// Opt out of preview — set cookie with timestamp for 24 hours.
			// The timestamp allows auto-reset when new drafts are created.
			$now = (string) time();
			setcookie(
				self::COOKIE_LIVE_MODE,
				$now,
				array(
					'expires'  => time() + DAY_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly'  => true,
					'samesite' => 'Lax',
				)
			);
			$_COOKIE[ self::COOKIE_LIVE_MODE ] = $now;
		} else {
			// Opt back into preview — remove cookie.
			setcookie(
				self::COOKIE_LIVE_MODE,
				'',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly'  => true,
					'samesite' => 'Lax',
				)
			);
			unset( $_COOKIE[ self::COOKIE_LIVE_MODE ] );
		}

		// Reset cached state.
		$this->is_preview_active = null;

		// Redirect to remove query params from URL.
		$redirect_url = remove_query_arg( array( self::PARAM_LIVE_MODE, '_dsgo_nonce' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Swap published content with draft content for the main query.
	 *
	 * Only applies to the main query to avoid affecting widgets, navigation
	 * menus, or secondary queries from themes/plugins.
	 *
	 * @param \WP_Post[] $posts Array of post objects.
	 * @param \WP_Query  $query The WP_Query instance.
	 * @return \WP_Post[] Modified posts array.
	 */
	public function swap_draft_content( $posts, $query ) {
		// Only run on the frontend, not in admin or REST API contexts.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $posts;
		}

		// Check if frontend preview is enabled in settings.
		$settings = $this->draft_mode->get_settings();
		if ( empty( $settings['show_frontend_preview'] ) ) {
			return $posts;
		}

		if ( ! $this->is_preview_active() ) {
			return $posts;
		}

		// Only apply to the main query to avoid affecting widgets, menus, etc.
		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		$draft_map = $this->get_draft_map();

		if ( empty( $draft_map ) ) {
			return $posts;
		}

		foreach ( $posts as &$post ) {
			if ( 'page' !== $post->post_type ) {
				continue;
			}

			if ( isset( $draft_map[ $post->ID ] ) ) {
				$draft = $draft_map[ $post->ID ];

				// Swap content fields while preserving the original post object.
				// See class docblock for security rationale re: unfiltered content.
				$post->post_content = $draft->post_content;
				$post->post_title   = $draft->post_title;
				$post->post_excerpt = $draft->post_excerpt;
			}
		}
		unset( $post );

		return $posts;
	}

	/**
	 * Swap the featured image to use the draft's featured image.
	 *
	 * @param int          $thumbnail_id The thumbnail/featured image ID.
	 * @param int|\WP_Post $post_id      The post ID or post object.
	 * @return int Modified thumbnail ID.
	 */
	public function swap_thumbnail( $thumbnail_id, $post_id ) {
		if ( ! $this->is_preview_active() ) {
			return $thumbnail_id;
		}

		// The post_thumbnail_id filter may pass a WP_Post object as $post_id.
		if ( $post_id instanceof \WP_Post ) {
			$post_id = $post_id->ID;
		}

		$draft_map = $this->get_draft_map();

		if ( isset( $draft_map[ $post_id ] ) ) {
			$draft_thumbnail = get_post_thumbnail_id( $draft_map[ $post_id ]->ID );
			if ( $draft_thumbnail ) {
				return $draft_thumbnail;
			}
		}

		return $thumbnail_id;
	}

	/**
	 * Add body class server-side to prevent layout shift from the banner.
	 *
	 * @param string[] $classes Array of body CSS classes.
	 * @return string[] Modified classes array.
	 */
	public function add_preview_body_class( $classes ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return $classes;
		}

		if ( ! $this->draft_mode->is_enabled() ) {
			return $classes;
		}

		$draft_map = $this->get_draft_map();
		if ( ! empty( $draft_map ) ) {
			$classes[] = 'dsgo-has-draft-banner';
		}

		return $classes;
	}

	/**
	 * Render the preview mode banner on the frontend.
	 *
	 * Shows a floating bar when preview mode is active, with:
	 * - Status indicator showing how many pages have draft changes
	 * - Toggle to switch between draft preview and live view
	 * - Links to edit individual draft pages
	 */
	public function render_preview_banner() {
		// Show banner for admins — either preview is active, or they opted out
		// but drafts still exist (so they can opt back in).
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		if ( ! $this->draft_mode->is_enabled() ) {
			return;
		}

		$draft_map   = $this->get_draft_map();
		$draft_count = count( $draft_map );

		if ( 0 === $draft_count ) {
			return;
		}

		$this->enqueue_banner_assets();

		$is_active    = $this->is_preview_active();
		$toggle_value = $is_active ? '1' : '0';
		$toggle_url   = wp_nonce_url(
			add_query_arg( self::PARAM_LIVE_MODE, $toggle_value ),
			'dsgo_live_mode_toggle',
			'_dsgo_nonce'
		);

		// Check if the current page has a draft.
		$current_page_has_draft = false;
		$queried_object         = get_queried_object();
		if ( $queried_object instanceof \WP_Post && isset( $draft_map[ $queried_object->ID ] ) ) {
			$current_page_has_draft = true;
		}

		$draft_list_items = $this->build_draft_list_items( $draft_map, $queried_object );

		$this->render_banner_html( $is_active, $draft_count, $toggle_url, $draft_list_items, $current_page_has_draft );
	}

	/**
	 * Enqueue banner CSS and JS assets via the WordPress enqueue system.
	 */
	private function enqueue_banner_assets() {
		$css_file = DESIGNSETGO_PATH . 'includes/admin/css/draft-mode-preview-banner.css';
		$js_file  = DESIGNSETGO_PATH . 'includes/admin/js/draft-mode-preview-banner.js';

		wp_enqueue_style(
			'dsgo-preview-banner',
			DESIGNSETGO_URL . 'includes/admin/css/draft-mode-preview-banner.css',
			array(),
			file_exists( $css_file ) ? filemtime( $css_file ) : DESIGNSETGO_VERSION
		);

		wp_enqueue_script(
			'dsgo-preview-banner',
			DESIGNSETGO_URL . 'includes/admin/js/draft-mode-preview-banner.js',
			array(),
			file_exists( $js_file ) ? filemtime( $js_file ) : DESIGNSETGO_VERSION,
			true
		);
	}

	/**
	 * Build an array of draft list item data for the details panel.
	 *
	 * @param array         $draft_map      Draft map of original_id => draft post.
	 * @param \WP_Post|null $queried_object Currently queried object.
	 * @return array[] Array of item data arrays with keys: page_title, page_url, edit_url, is_current.
	 */
	private function build_draft_list_items( $draft_map, $queried_object ) {
		$items = array();

		foreach ( $draft_map as $original_id => $draft ) {
			$original   = get_post( $original_id );
			$is_current = ( $queried_object instanceof \WP_Post && $queried_object->ID === $original_id );

			$items[] = array(
				'page_title' => $original ? $original->post_title : __( 'Untitled', 'designsetgo' ),
				'page_url'   => get_permalink( $original_id ),
				'edit_url'   => get_edit_post_link( $draft->ID, 'raw' ),
				'is_current' => $is_current,
			);
		}

		return $items;
	}

	/**
	 * Render the banner HTML.
	 *
	 * @param bool   $is_active              Whether preview mode is active.
	 * @param int    $draft_count            Number of pages with drafts.
	 * @param string $toggle_url             URL to toggle preview mode.
	 * @param array  $draft_list_items       Array of draft item data for the details panel.
	 * @param bool   $current_page_has_draft Whether the current page has a draft.
	 */
	private function render_banner_html( $is_active, $draft_count, $toggle_url, $draft_list_items, $current_page_has_draft ) {
		$status_text = $is_active
			? sprintf(
				/* translators: %d is the number of pages with pending changes */
				_n(
					'Preview mode — %d page with changes',
					'Preview mode — %d pages with changes',
					$draft_count,
					'designsetgo'
				),
				$draft_count
			)
			: sprintf(
				/* translators: %d is the number of pages with pending changes */
				_n(
					'Live mode — %d page has pending changes',
					'Live mode — %d pages have pending changes',
					$draft_count,
					'designsetgo'
				),
				$draft_count
			);

		$toggle_label = $is_active
			? __( 'View Live', 'designsetgo' )
			: __( 'Preview Changes', 'designsetgo' );

		$indicator_class = $is_active ? 'dsgo-preview-banner--active' : 'dsgo-preview-banner--live';
		?>
		<div id="dsgo-preview-banner" class="dsgo-preview-banner <?php echo esc_attr( $indicator_class ); ?>" role="status" aria-live="polite">
			<div class="dsgo-preview-banner__inner">
				<div class="dsgo-preview-banner__status">
					<span class="dsgo-preview-banner__indicator"></span>
					<span class="dsgo-preview-banner__text"><?php echo esc_html( $status_text ); ?></span>
					<?php if ( $is_active && $current_page_has_draft ) : ?>
						<span class="dsgo-preview-banner__page-badge"><?php esc_html_e( 'This page has changes', 'designsetgo' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="dsgo-preview-banner__actions">
					<button type="button" class="dsgo-preview-banner__details-toggle" aria-expanded="false" aria-controls="dsgo-preview-draft-list">
						<?php esc_html_e( 'Details', 'designsetgo' ); ?>
						<svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M3 5L6 8L9 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>

					<a href="<?php echo esc_url( $toggle_url ); ?>" class="dsgo-preview-banner__toggle">
						<?php echo esc_html( $toggle_label ); ?>
					</a>
				</div>
			</div>

			<div id="dsgo-preview-draft-list" class="dsgo-preview-banner__details" hidden>
				<div class="dsgo-preview-banner__details-inner">
					<h3 class="dsgo-preview-banner__details-title"><?php esc_html_e( 'Pages with pending changes:', 'designsetgo' ); ?></h3>
					<ul class="dsgo-preview-banner__draft-list">
						<?php foreach ( $draft_list_items as $item ) : ?>
							<li class="dsgo-preview-draft-item<?php echo $item['is_current'] ? ' dsgo-preview-draft-item--current' : ''; ?>">
								<a href="<?php echo esc_url( $item['page_url'] ); ?>" class="dsgo-preview-draft-link"><?php echo esc_html( $item['page_title'] ); ?></a>
								<a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="dsgo-preview-draft-edit" title="<?php esc_attr_e( 'Edit page', 'designsetgo' ); ?>"><?php esc_html_e( 'Edit', 'designsetgo' ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
