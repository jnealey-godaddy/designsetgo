<?php
/**
 * Main Plugin Class
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
class Plugin {
	/**
	 * Allowed hover animation slugs for Icon Button blocks.
	 *
	 * Single source of truth used by both the regex check and the
	 * allowlist validation in apply_default_icon_button_hover().
	 *
	 * @var string[]
	 */
	private const ALLOWED_HOVER_ANIMATIONS = array(
		'fill-diagonal',
		'zoom-in',
		'slide-left',
		'slide-right',
		'slide-down',
		'slide-up',
		'border-pulse',
		'border-glow',
		'lift',
		'shrink',
	);

	/**
	 * CSS properties used in block save() output that must pass through
	 * wp_kses_post() sanitization.
	 *
	 * WordPress's default safe_style_css allowlist does not include modern
	 * layout properties (flex, grid, etc.). Without this filter, block inline
	 * styles are stripped on multisite, REST API content creation, and other
	 * contexts where wp_kses_post() runs.
	 *
	 * @var string[]
	 */
	private const SAFE_STYLE_PROPERTIES = array(
		// Layout.
		'display',
		'gap',
		'row-gap',
		'column-gap',
		'grid-template-columns',
		// Flex.
		'flex',
		'flex-direction',
		'flex-wrap',
		'flex-shrink',
		'align-items',
		'justify-content',
		'align-self',
		// Positioning.
		'position',
		'left',
		'overflow',
		// Visual.
		'transform',
		'transition',
		'aspect-ratio',
		'object-fit',
		'object-position',
		'backdrop-filter',
		'box-sizing',
	);

	/**
	 * CSS function names used in block save() output that WordPress's KSES
	 * does not recognize as safe.
	 *
	 * WordPress allows var(), calc(), repeat(), etc. but strips other CSS
	 * functions like rotate() and blur() because they contain parentheses.
	 * These are standard visual CSS functions that cannot execute scripts.
	 *
	 * Covers:
	 * - Color: rgb(), rgba(), hsl(), hsla()
	 * - Gradients: linear-gradient(), radial-gradient(), conic-gradient() + repeating-*
	 * - Transform: rotate(), scale(), translate(), skew(), matrix(), perspective()
	 * - Filter: blur(), brightness(), contrast(), drop-shadow(), grayscale(), etc.
	 *
	 * @var string
	 */
	private const SAFE_CSS_FUNCTIONS_PATTERN = '/\b(?:(?:repeating-)?(?:linear|radial|conic)-gradient|rgba?|hsla?|rotate(?:3d|[XYZ])?|scale(?:3d|[XYZ])?|skew[XY]?|translate(?:3d|[XYZ])?|matrix(?:3d)?|blur|brightness|contrast|drop-shadow|grayscale|saturate|sepia|invert|hue-rotate|opacity|perspective)(\((?:[^()]|(?1))*\))/';

	/**
	 * SVG elements used in block save() output.
	 *
	 * WordPress's default wp_kses_post() strips all SVG elements.
	 * These are used by shape dividers, icons, comparison tables,
	 * timeline markers, accordion toggles, and counter decorations.
	 *
	 * Only presentation elements are included. Script-capable elements
	 * (script, foreignObject, animate, animateMotion, animateTransform, set)
	 * and link elements (a, image, feImage) are intentionally excluded.
	 * Event handler attributes (onclick, onload, etc.) are not in
	 * SVG_ATTRIBUTES and are therefore blocked by KSES.
	 *
	 * @var string[]
	 */
	private const SVG_ELEMENTS = array(
		'svg',
		'path',
		'circle',
		'rect',
		'line',
		'polyline',
		'polygon',
		'ellipse',
		'g',
		'defs',
		'symbol',
		'clippath',
		'lineargradient',
		'radialgradient',
		'stop',
	);

	/**
	 * SVG attributes allowed in block save() output.
	 *
	 * @var array<string, bool>
	 */
	private const SVG_ATTRIBUTES = array(
		'xmlns'              => true,
		'viewbox'            => true,
		'preserveaspectratio' => true,
		'width'              => true,
		'height'             => true,
		'fill'               => true,
		'fill-rule'          => true,
		'fill-opacity'       => true,
		'stroke'             => true,
		'stroke-width'       => true,
		'stroke-linecap'     => true,
		'stroke-linejoin'    => true,
		'stroke-dasharray'   => true,
		'stroke-dashoffset'  => true,
		'stroke-opacity'     => true,
		'opacity'            => true,
		'transform'          => true,
		'x'                  => true,
		'y'                  => true,
		'x1'                 => true,
		'y1'                 => true,
		'x2'                 => true,
		'y2'                 => true,
		'cx'                 => true,
		'cy'                 => true,
		'r'                  => true,
		'rx'                 => true,
		'ry'                 => true,
		'd'                  => true,
		'points'             => true,
		'clip-path'          => true,
		'clip-rule'          => true,
		'class'              => true,
		'id'                 => true,
		'style'              => true,
		'aria-hidden'        => true,
		'aria-label'         => true,
		'role'               => true,
		'focusable'          => true,
		// Gradient stop attributes.
		'offset'             => true,
		'stop-color'         => true,
		'stop-opacity'       => true,
	);

	/**
	 * Form elements used in form block save() output.
	 *
	 * WordPress's default wp_kses_post() does not include form, input,
	 * select, or option tags. Without this allowlist, form block content
	 * is stripped on REST API content creation, multisite, and other
	 * contexts where wp_kses_post() runs.
	 *
	 * Each key is an element name; its value is an array of element-specific
	 * attributes. Global attributes (id, class, style, data-*, aria-*, etc.)
	 * are merged in at runtime via FORM_GLOBAL_ATTRIBUTES.
	 *
	 * @since 2.0.29
	 * @var array<string, array<string, bool>>
	 */
	private const FORM_ELEMENTS = array(
		'form'     => array(
			'method'     => true,
			'action'     => true,
			'novalidate' => true,
			'enctype'    => true,
		),
		'input'    => array(
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'placeholder'  => true,
			'required'     => true,
			'minlength'    => true,
			'maxlength'    => true,
			'pattern'      => true,
			'autocomplete' => true,
			'checked'      => true,
			'disabled'     => true,
			'readonly'     => true,
			'size'         => true,
			'accept'       => true,
			'multiple'     => true,
			'min'          => true,
			'max'          => true,
			'step'         => true,
		),
		'select'   => array(
			'name'     => true,
			'required' => true,
			'multiple' => true,
			'disabled' => true,
			'size'     => true,
		),
		'option'   => array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
		),
		'optgroup' => array(
			'label'    => true,
			'disabled' => true,
		),
	);

	/**
	 * Global attributes merged onto every FORM_ELEMENTS entry.
	 *
	 * WordPress KSES supports data-* and aria-* wildcards since WP 5.0.
	 *
	 * @since 2.0.29
	 * @var array<string, bool>
	 */
	private const FORM_GLOBAL_ATTRIBUTES = array(
		'id'       => true,
		'class'    => true,
		'style'    => true,
		'role'     => true,
		'tabindex' => true,
		'title'    => true,
		'hidden'   => true,
		'aria-*'   => true,
		'data-*'   => true,
	);

	/**
	 * Additional attributes for tags that WordPress already allows in
	 * wp_kses_post() but with an incomplete attribute set.
	 *
	 * For example, textarea is allowed by default but lacks placeholder
	 * and required — both used by form block save() output. The button
	 * element needs aria-* and data-* for the form submit button.
	 *
	 * @since 2.0.29
	 * @var array<string, array<string, bool>>
	 */
	private const FORM_EXTRA_ATTRIBUTES = array(
		'textarea' => array(
			'placeholder' => true,
			'required'    => true,
			'maxlength'   => true,
			'minlength'   => true,
			'aria-*'      => true,
			'data-*'      => true,
		),
		'button'   => array(
			'aria-*' => true,
			'data-*' => true,
		),
		'div'      => array(
			'aria-*' => true,
			'data-*' => true,
		),
	);

	/**
	 * Instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Assets instance.
	 *
	 * @var Assets
	 */
	public $assets;

	/**
	 * Blocks Loader instance.
	 *
	 * @var Blocks\Loader
	 */
	public $blocks;

	/**
	 * Modal Hooks instance.
	 *
	 * @var Blocks\Modal_Hooks
	 */
	public $modal_hooks;

	/**
	 * Form Handler instance.
	 *
	 * @var Blocks\Form_Handler
	 */
	public $form_handler;

	/**
	 * Form Submissions instance.
	 *
	 * @var Blocks\Form_Submissions
	 */
	public $form_submissions;

	/**
	 * Patterns Loader instance.
	 *
	 * @var Patterns\Loader
	 */
	public $patterns;

	/**
	 * Global Styles instance.
	 *
	 * @var Admin\Global_Styles
	 */
	public $global_styles;

	/**
	 * Button Global Styles instance.
	 *
	 * @var Button_Global_Styles
	 */
	public $button_global_styles;

	/**
	 * Admin Menu instance.
	 *
	 * @var Admin\Admin_Menu
	 */
	public $admin_menu;

	/**
	 * Block Migrator instance.
	 *
	 * @var Admin\Block_Migrator
	 */
	public $block_migrator;

	/**
	 * Settings instance.
	 *
	 * @var Admin\Settings
	 */
	public $settings;

	/**
	 * Block Manager instance.
	 *
	 * @var Admin\Block_Manager
	 */
	public $block_manager;

	/**
	 * GDPR Compliance instance.
	 *
	 * @var Admin\GDPR_Compliance
	 */
	public $gdpr_compliance;

	/**
	 * Custom CSS Renderer instance.
	 *
	 * @var Custom_CSS_Renderer
	 */
	public $custom_css_renderer;

	/**
	 * Abilities Registry instance.
	 *
	 * @var Abilities\Abilities_Registry
	 */
	public $abilities_registry;

	/**
	 * Section Styles instance.
	 *
	 * @var Section_Styles
	 */
	public $section_styles;

	/**
	 * Sticky Header instance.
	 *
	 * @var Sticky_Header
	 */
	public $sticky_header;

	/**
	 * Overlay Header instance.
	 *
	 * @var Overlay_Header
	 */
	public $overlay_header;

	/**
	 * Icon Injector instance.
	 *
	 * @var Icon_Injector
	 */
	public $icon_injector;

	/**
	 * Draft Mode instance.
	 *
	 * @var Admin\Draft_Mode
	 */
	public $draft_mode;

	/**
	 * SVG Pattern Renderer instance.
	 *
	 * @var SVG_Pattern_Renderer
	 */
	public $svg_pattern_renderer;

	/**
	 * LLMS TXT instance.
	 *
	 * @var LLMS_Txt\Controller
	 */
	public $llms_txt;

	/**
	 * Extension Attributes instance.
	 *
	 * @var Extension_Attributes
	 */
	public $extension_attrs;

	/**
	 * Style Binding instance.
	 *
	 * @var StyleBinding
	 */
	public $style_binding;

	/**
	 * Block Bindings Support instance.
	 *
	 * @var Block_Bindings_Support
	 */
	public $block_bindings_support;

	/**
	 * Query Block Controller instance.
	 *
	 * @var Blocks\Query\Controller
	 */
	public $query_controller;

	/**
	 * Query Block Bindings instance.
	 *
	 * @var Blocks\Query\Bindings
	 */
	public $query_bindings;

	/**
	 * Dynamic Tags bootstrap instance.
	 *
	 * @var Blocks\DynamicTags\Bootstrap
	 */
	public $dynamic_tags;

	/**
	 * Query Filter Index instance.
	 *
	 * @var Blocks\Query\FilterIndex
	 */
	public $filter_index;

	/**
	 * Query Filter Registry instance.
	 *
	 * @var Blocks\Query\FilterRegistry
	 */
	public $filter_registry;

	/**
	 * Query Filter Index Admin page instance.
	 *
	 * @var Admin\Query_Filter_Index_Admin
	 */
	public $query_filter_index_admin;

	/**
	 * Returns the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init();
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once DESIGNSETGO_PATH . 'includes/class-block-visibility.php';
		BlockVisibility::register();
		require_once DESIGNSETGO_PATH . 'includes/class-assets.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-loader.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-form-security.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-form-handler.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-form-submissions.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-modal-hooks.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-template-controller.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-helpers.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-metabox.php';
		\DesignSetGo\Blocks\Query\MetaBoxBindings::bootstrap();
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-pods.php';
		\DesignSetGo\Blocks\Query\PodsBindings::bootstrap();
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-jetengine.php';
		\DesignSetGo\Blocks\Query\JetEngineBindings::bootstrap();

		// Dynamic Tags subsystem (registry, source catalog, REST, image resolver).
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-registry.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-sources-post.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-sources-site.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-sources-archive.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-sources-user.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-field-discovery.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-image-resolver.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-rest.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/dynamic-tags/class-dynamic-tags-bootstrap.php';

		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-filter-index.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-filter-index-hooks.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-filter-index-rebuilder.php';
		require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-filter-registry.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-filter-index-cli.php';
			Blocks\Query\FilterIndexCLI::register();
		}

		// Query Monitor integration — deferred to plugins_loaded so QM has finished
		// loading regardless of plugin order; the files themselves bail if the QM
		// base classes still aren't present at the deferred point.
		add_action(
			'plugins_loaded',
			static function () {
				if ( ! class_exists( '\QM_Collector' ) ) {
					return;
				}
				require_once DESIGNSETGO_PATH . 'includes/class-query-qm-collector.php';
				require_once DESIGNSETGO_PATH . 'includes/class-query-qm-output.php';
			},
			20
		);

		require_once DESIGNSETGO_PATH . 'includes/patterns/class-loader.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-global-styles.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-settings.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-settings-schema.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-block-manager.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-gdpr-compliance.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-admin-menu.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-draft-mode-rest.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-draft-mode-admin.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-draft-mode.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-draft-mode-preview.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-block-migrator.php';
		require_once DESIGNSETGO_PATH . 'includes/admin/class-query-filter-index-admin.php';
		require_once DESIGNSETGO_PATH . 'includes/class-custom-css-renderer.php';
		require_once DESIGNSETGO_PATH . 'includes/class-section-styles.php';
		require_once DESIGNSETGO_PATH . 'includes/class-sticky-header.php';
		require_once DESIGNSETGO_PATH . 'includes/class-overlay-header.php';
		require_once DESIGNSETGO_PATH . 'includes/class-icon-injector.php';
		require_once DESIGNSETGO_PATH . 'includes/class-button-global-styles.php';
		require_once DESIGNSETGO_PATH . 'includes/class-extension-attributes.php';
		require_once DESIGNSETGO_PATH . 'includes/class-style-binding.php';
		require_once DESIGNSETGO_PATH . 'includes/class-block-bindings-support.php';
		require_once DESIGNSETGO_PATH . 'includes/svg-pattern-data.php';
		require_once DESIGNSETGO_PATH . 'includes/class-svg-pattern-renderer.php';

		// LLMS TXT classes.
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-file-manager.php';
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-generator.php';
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-conflict-detector.php';
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-rest-controller.php';
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-negotiation-handler.php';
		require_once DESIGNSETGO_PATH . 'includes/llms-txt/class-controller.php';

		// Markdown converter classes.
		require_once DESIGNSETGO_PATH . 'includes/markdown/class-core-handlers.php';
		require_once DESIGNSETGO_PATH . 'includes/markdown/class-dsgo-handlers.php';
		require_once DESIGNSETGO_PATH . 'includes/markdown/class-converter.php';

		require_once DESIGNSETGO_PATH . 'includes/helpers.php';

		// Load Composer autoloader if available.
		if ( file_exists( DESIGNSETGO_PATH . 'vendor/autoload.php' ) ) {
			require_once DESIGNSETGO_PATH . 'vendor/autoload.php';
		}

		// Load WordPress Abilities API polyfill for WordPress < 6.9.
		// WordPress 6.9+ includes the Abilities API natively.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			if ( file_exists( DESIGNSETGO_PATH . 'vendor/wordpress/abilities-api/includes/bootstrap.php' ) ) {
				if ( ! defined( 'WP_ABILITIES_API_DIR' ) ) {
					define( 'WP_ABILITIES_API_DIR', DESIGNSETGO_PATH . 'vendor/wordpress/abilities-api/' );
				}
				require_once DESIGNSETGO_PATH . 'vendor/wordpress/abilities-api/includes/bootstrap.php';
			}
		}

		// Load Abilities Registry.
		require_once DESIGNSETGO_PATH . 'includes/abilities/class-abilities-registry.php';
	}

	/**
	 * Initialize the plugin.
	 */
	private function init() {
		// Initialize components.
		$this->assets              = new Assets();
		$this->blocks              = new Blocks\Loader();
		$this->extension_attrs     = new Extension_Attributes();
		$this->style_binding       = new StyleBinding();
		$this->block_bindings_support = new Block_Bindings_Support();
		$this->block_bindings_support->register();
		$this->modal_hooks         = new Blocks\Modal_Hooks();
		$this->form_handler        = new Blocks\Form_Handler();
		$this->form_submissions    = new Blocks\Form_Submissions();
		$this->query_controller          = new Blocks\Query\Controller();
		add_action( 'rest_api_init', array( 'DesignSetGo\Blocks\Query\Template_Controller', 'register_routes' ) );
		$this->query_bindings      = new Blocks\Query\Bindings();
		$this->dynamic_tags        = new Blocks\DynamicTags\Bootstrap();
		$this->filter_index         = new Blocks\Query\FilterIndex();
		Blocks\Query\FilterIndexHooks::register_hooks();
		$this->filter_registry      = new Blocks\Query\FilterRegistry();
		$this->patterns            = new Patterns\Loader();
		$this->global_styles       = new Admin\Global_Styles();
		$this->settings            = new Admin\Settings();
		$this->block_manager       = new Admin\Block_Manager();
		$this->gdpr_compliance     = new Admin\GDPR_Compliance();
		$this->custom_css_renderer = new Custom_CSS_Renderer();
		$this->section_styles      = new Section_Styles();
		$this->section_styles->init();
		$this->sticky_header  = new Sticky_Header();
		$this->overlay_header = new Overlay_Header();
		$this->icon_injector         = new Icon_Injector();
		$this->svg_pattern_renderer  = new SVG_Pattern_Renderer();
		$this->button_global_styles  = new Button_Global_Styles();
		$this->button_global_styles->init();
		$this->llms_txt      = new LLMS_Txt\Controller();

		// Initialize admin-only features.
		if ( is_admin() ) {
			$this->admin_menu         = new Admin\Admin_Menu();
			$this->block_migrator     = new Admin\Block_Migrator();
			$this->query_filter_index_admin  = new Admin\Query_Filter_Index_Admin();
		}

		// Initialize draft mode (works on both admin and REST API).
		$this->draft_mode = new Admin\Draft_Mode();

		// Initialize Abilities Registry (AI-native API).
		if ( class_exists( 'DesignSetGo\Abilities\Abilities_Registry' ) ) {
			$this->abilities_registry = Abilities\Abilities_Registry::get_instance();
		}

		// Hook into WordPress.
		// Note: load_plugin_textdomain() is not needed for WordPress.org plugins since WP 4.6+.
		// WordPress automatically loads translations from wordpress.org.
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );

		// Add block category.
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );

		// Conditionally register WooCommerce-dependent blocks.
		add_filter( 'designsetgo_register_block', array( $this, 'gate_woocommerce_blocks' ), 10, 2 );

		// Inject API keys into Map block on render.
		add_filter( 'render_block_designsetgo/map', array( $this, 'inject_map_api_key' ), 10, 2 );

		// Inject parallax data attributes into block output (server-side fallback).
		add_filter( 'render_block', array( $this, 'inject_parallax_attributes' ), 10, 2 );

		// Apply global default hover animation to Icon Button blocks.
		add_filter( 'render_block_designsetgo/icon-button', array( $this, 'apply_default_icon_button_hover' ), 10, 2 );

		// Apply global default hover animation to Form Builder submit buttons.
		add_filter( 'render_block_designsetgo/form-builder', array( $this, 'apply_default_form_button_hover' ), 10, 2 );

		// Allow block CSS properties, function values, and SVG through wp_kses_post().
		add_filter( 'safe_style_css', array( $this, 'allow_block_style_properties' ) );
		add_filter( 'safecss_filter_attr_allow_css', array( $this, 'allow_block_css_functions' ), 10, 2 );
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_block_svg_elements' ), 10, 2 );
	}

	/**
	 * Runs any pending database schema upgrades.
	 *
	 * Hooked onto admin_init so it only runs in the admin context where
	 * ABSPATH/wp-admin/includes/upgrade.php is guaranteed to be present.
	 * Running in the constructor caused phpstan analysis failures because
	 * FilterIndex::install() requires that file at analysis time.
	 *
	 * Compares the stored designsetgo_db_version option against
	 * DESIGNSETGO_VERSION and installs missing schema when the plugin
	 * moves past a version that introduced a schema change.
	 */
	public function maybe_upgrade(): void {
		$stored = get_option( 'designsetgo_db_version', '0.0.0' );

		if ( version_compare( $stored, '2.2.0', '<' ) ) {
			Blocks\Query\FilterIndex::install();
			// Only seal the upgrade gate after verifying the table actually
			// exists. Previously the version was bumped unconditionally, so a
			// silent dbDelta failure (permissions, disk full, early-load order
			// quirks) left the option at 2.2.0 with no table, and the gate
			// never retried on subsequent requests.
			Blocks\Query\FilterIndex::reset_table_cache();
			if ( Blocks\Query\FilterIndex::table_exists() ) {
				update_option( 'designsetgo_db_version', '2.2.0', false );
			}
		}
	}

	/**
	 * Enqueue editor assets.
	 *
	 * Note: Block-specific assets are loaded automatically via block.json.
	 */
	public function editor_assets() {
		// Enqueue the branded SVG icon for the DesignSetGo block category.
		$asset_file = DESIGNSETGO_PATH . 'build/block-category-icon.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;

			wp_enqueue_script(
				'designsetgo-block-category-icon',
				DESIGNSETGO_URL . 'build/block-category-icon.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// Localize sticky header global settings for FSE controls.
			$settings        = \DesignSetGo\Admin\Settings::get_settings();
			$sticky_settings = isset( $settings['sticky_header'] ) ? $settings['sticky_header'] : array();
			$defaults        = \DesignSetGo\Admin\Settings::get_defaults();
			$sticky_settings = wp_parse_args( $sticky_settings, $defaults['sticky_header'] );

			wp_localize_script(
				'designsetgo-block-category-icon',
				'dsgoStickyHeaderGlobalSettings',
				array(
					'enabled' => (bool) $sticky_settings['enable'],
				)
			);

			// Localize integrations settings (Google Maps API key, etc.).
			$integrations_settings = isset( $settings['integrations'] ) ? $settings['integrations'] : array();
			$integrations_defaults = isset( $defaults['integrations'] ) ? $defaults['integrations'] : array();
			$integrations_settings = wp_parse_args( $integrations_settings, $integrations_defaults );

			wp_localize_script(
				'designsetgo-block-category-icon',
				'dsgoIntegrations',
				array(
					'googleMapsApiKey'    => ! empty( $integrations_settings['google_maps_api_key'] ) ? esc_js( $integrations_settings['google_maps_api_key'] ) : '',
					'turnstileSiteKey'    => ! empty( $integrations_settings['turnstile_site_key'] ) ? esc_js( $integrations_settings['turnstile_site_key'] ) : '',
					'turnstileConfigured' => ! empty( $integrations_settings['turnstile_site_key'] ) && ! empty( $integrations_settings['turnstile_secret_key'] ),
				)
			);
		}

		// Enqueue llms.txt editor panel.
		$llms_asset_file = DESIGNSETGO_PATH . 'build/llms-txt.asset.php';

		if ( file_exists( $llms_asset_file ) ) {
			$llms_asset = include $llms_asset_file;

			wp_enqueue_script(
				'dsgo-llms-txt-panel',
				DESIGNSETGO_URL . 'build/llms-txt.js',
				$llms_asset['dependencies'],
				$llms_asset['version'],
				true
			);
		}

		// Enqueue overlay header editor panel.
		$overlay_asset_file = DESIGNSETGO_PATH . 'build/overlay-header.asset.php';

		if ( file_exists( $overlay_asset_file ) ) {
			$overlay_asset = include $overlay_asset_file;

			wp_enqueue_script(
				'dsgo-overlay-header-panel',
				DESIGNSETGO_URL . 'build/overlay-header.js',
				$overlay_asset['dependencies'],
				$overlay_asset['version'],
				true
			);
		}

		// Localize excluded blocks setting for extension filtering.
		// This is done outside the block-category-icon conditional because
		// the 'designsetgo-extensions' script is registered separately in the Assets class.
		if ( wp_script_is( 'designsetgo-extensions', 'registered' ) || wp_script_is( 'designsetgo-extensions', 'enqueued' ) ) {
			$settings        = $settings ?? \DesignSetGo\Admin\Settings::get_settings();
			$excluded_blocks = isset( $settings['excluded_blocks'] ) ? $settings['excluded_blocks'] : array();

			$enabled_extensions = isset( $settings['enabled_extensions'] ) ? (array) $settings['enabled_extensions'] : array();

			wp_localize_script(
				'designsetgo-extensions',
				'dsgoSettings',
				array(
					'excludedBlocks'         => $excluded_blocks,
					'defaultIconButtonHover' => isset( $settings['animations']['default_icon_button_hover'] )
						? sanitize_key( $settings['animations']['default_icon_button_hover'] )
						: 'fill-diagonal',
					// Empty list = all extensions enabled (matches the
					// PHP convention in Block_Manager::should_load_extension).
					'enabledExtensions'      => array_values( array_map( 'sanitize_key', $enabled_extensions ) ),
				)
			);
		}
	}

	/**
	 * Register DesignSetGo block category.
	 *
	 * @param array    $categories Block categories.
	 * @param \WP_Post $post       Current post object (unused).
	 * @return array Modified categories.
	 */
	public function register_block_category( $categories, $post = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$categories[] = array(
			'slug'  => 'designsetgo',
			'title' => __( 'DesignSetGo', 'designsetgo' ),
			'icon'  => 'layout',
		);

		return $categories;
	}

	/**
	 * Gate WooCommerce-dependent blocks behind WooCommerce availability.
	 *
	 * Prevents blocks that require WooCommerce from being registered
	 * when WooCommerce is not installed or active.
	 *
	 * @since 2.1.0
	 * @param bool   $should_register Whether the block should be registered.
	 * @param string $block_name      The block name (e.g. 'designsetgo/product-showcase-hero').
	 * @return bool Whether to register the block.
	 */
	public function gate_woocommerce_blocks( $should_register, $block_name ) {
		$wc_blocks = array(
			'designsetgo/product-showcase-hero',
			'designsetgo/product-categories-grid',
		);

		if ( in_array( $block_name, $wc_blocks, true ) ) {
			return $should_register && class_exists( 'WooCommerce' );
		}

		return $should_register;
	}

	/**
	 * Inject Google Maps API key into Map block on render.
	 *
	 * Security Note: Google Maps JavaScript API keys are designed to be public
	 * (client-side). Security is enforced through:
	 * 1. HTTP referrer restrictions (configured in Google Cloud Console)
	 * 2. API quotas and billing limits
	 * 3. Rate limiting
	 *
	 * This is the standard recommended approach per Google's documentation.
	 * Users are warned to configure referrer restrictions in the settings panel.
	 *
	 * @param string $block_content Block HTML content.
	 * @param array  $block         Block data including name and attributes.
	 * @return string Modified block content.
	 */
	public function inject_map_api_key( $block_content, $block ) {
		// Get settings.
		$settings = \DesignSetGo\Admin\Settings::get_settings();

		// Get Google Maps API key from settings.
		$api_key = isset( $settings['integrations']['google_maps_api_key'] )
			? $settings['integrations']['google_maps_api_key']
			: '';

		// If no API key or not using Google Maps, return unmodified content.
		if ( empty( $api_key ) ) {
			return $block_content;
		}

		// Only inject if the block is using Google Maps provider.
		$provider = isset( $block['attrs']['dsgoProvider'] ) ? $block['attrs']['dsgoProvider'] : 'openstreetmap';
		if ( 'googlemaps' !== $provider ) {
			return $block_content;
		}

		// Inject API key as a data attribute.
		// Find the opening div tag with class dsgo-map.
		$pattern     = '/(<div[^>]*class="[^"]*dsgo-map[^"]*"[^>]*)/';
		$replacement = '$1 data-dsgo-api-key="' . esc_attr( $api_key ) . '"';

		return preg_replace( $pattern, $replacement, $block_content, 1 );
	}

	/**
	 * Inject parallax data attributes into block output.
	 *
	 * Server-side fallback that ensures parallax data attributes are present
	 * on block HTML even if the client-side blocks.getSaveContent.extraProps
	 * filter didn't persist them. Uses WP_HTML_Tag_Processor for safe HTML
	 * modification.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block data including attrs.
	 * @return string Modified block content.
	 */
	public function inject_parallax_attributes( $block_content, $block ) {
		// Only process blocks with parallax enabled.
		if ( empty( $block['attrs']['dsgoParallaxEnabled'] ) ) {
			return $block_content;
		}

		// Skip if data attributes already exist in the HTML.
		if ( strpos( $block_content, 'data-dsgo-parallax-enabled' ) !== false ) {
			return $block_content;
		}

		// Skip empty content.
		if ( empty( $block_content ) ) {
			return $block_content;
		}

		$attrs = $block['attrs'];

		// Validate string values against whitelists.
		$allowed_directions = array( 'up', 'down', 'left', 'right' );
		$direction          = $attrs['dsgoParallaxDirection'] ?? 'up';
		$direction          = in_array( $direction, $allowed_directions, true ) ? $direction : 'up';

		$allowed_relative = array( 'viewport', 'page' );
		$relative_to      = $attrs['dsgoParallaxRelativeTo'] ?? 'viewport';
		$relative_to      = in_array( $relative_to, $allowed_relative, true ) ? $relative_to : 'viewport';

		// Clamp numeric values to valid ranges.
		$speed          = max( 0, min( 10, intval( $attrs['dsgoParallaxSpeed'] ?? 5 ) ) );
		$viewport_start = max( 0, min( 100, intval( $attrs['dsgoParallaxViewportStart'] ?? 0 ) ) );
		$viewport_end   = max( 0, min( 100, intval( $attrs['dsgoParallaxViewportEnd'] ?? 100 ) ) );

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag() ) {
			$processor->set_attribute( 'data-dsgo-parallax-enabled', 'true' );
			$processor->set_attribute( 'data-dsgo-parallax-direction', $direction );
			$processor->set_attribute( 'data-dsgo-parallax-speed', (string) $speed );
			$processor->set_attribute( 'data-dsgo-parallax-viewport-start', (string) $viewport_start );
			$processor->set_attribute( 'data-dsgo-parallax-viewport-end', (string) $viewport_end );
			$processor->set_attribute( 'data-dsgo-parallax-relative-to', $relative_to );
			$processor->set_attribute( 'data-dsgo-parallax-desktop', ( $attrs['dsgoParallaxDesktop'] ?? true ) ? 'true' : 'false' );
			$processor->set_attribute( 'data-dsgo-parallax-tablet', ( $attrs['dsgoParallaxTablet'] ?? true ) ? 'true' : 'false' );
			$processor->set_attribute( 'data-dsgo-parallax-mobile', ( $attrs['dsgoParallaxMobile'] ?? false ) ? 'true' : 'false' );
			$processor->add_class( 'dsgo-has-parallax' );

			$block_content = $processor->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Apply global default hover animation to Icon Button blocks.
	 *
	 * When an icon button has no explicit hover animation, this injects the
	 * default animation class at render time. Resolution priority:
	 * per-block > admin settings > theme.json > none.
	 * Blocks with explicit animations or "no animation" override are unchanged.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block data including attrs.
	 * @return string Modified block content.
	 */
	public function apply_default_icon_button_hover( $block_content, $block ) {
		if ( empty( $block_content ) ) {
			return $block_content;
		}

		// If block explicitly opted out of hover animation, leave unchanged.
		if ( strpos( $block_content, 'dsgo-icon-button--no-hover' ) !== false ) {
			return $block_content;
		}

		// If block already has an explicit animation class, leave unchanged.
		$pattern = '/dsgo-icon-button--(' . implode( '|', array_map( 'preg_quote', self::ALLOWED_HOVER_ANIMATIONS ) ) . ')/';
		if ( preg_match( $pattern, $block_content ) ) {
			return $block_content;
		}

		// No explicit animation — resolve default.
		// Priority: admin settings > theme.json > none.
		$settings      = \DesignSetGo\Admin\Settings::get_settings();
		$admin_default = isset( $settings['animations']['default_icon_button_hover'] )
			? sanitize_key( $settings['animations']['default_icon_button_hover'] )
			: 'none';

		$default = $admin_default;
		if ( 'none' === $default ) {
			// Fall back to theme.json custom setting.
			$theme_default = wp_get_global_settings( array( 'custom', 'designsetgo', 'defaultIconButtonHover' ) );
			if ( ! empty( $theme_default ) && is_string( $theme_default ) ) {
				$theme_default = sanitize_key( $theme_default );
				if ( 'none' !== $theme_default ) {
					$default = $theme_default;
				}
			}
		}

		// Validate against allowed animation names.
		if ( 'none' === $default || ! in_array( $default, self::ALLOWED_HOVER_ANIMATIONS, true ) ) {
			return $block_content;
		}

		// Use WP_HTML_Tag_Processor for safe class injection.
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag() ) {
			$processor->add_class( 'dsgo-icon-button--' . $default );
			$block_content = $processor->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Apply global default hover animation to Form Builder submit buttons.
	 *
	 * Uses the same site-wide default as icon buttons. Resolution priority:
	 * admin settings > theme.json > none.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block data including attrs.
	 * @return string Modified block content.
	 */
	public function apply_default_form_button_hover( $block_content, $block ) {
		if ( empty( $block_content ) ) {
			return $block_content;
		}

		// If submit button already has an explicit animation or no-hover class, leave unchanged.
		if ( strpos( $block_content, 'dsgo-form__submit--no-hover' ) !== false ) {
			return $block_content;
		}

		$pattern = '/dsgo-form__submit--(' . implode( '|', array_map( 'preg_quote', self::ALLOWED_HOVER_ANIMATIONS ) ) . ')/';
		if ( preg_match( $pattern, $block_content ) ) {
			return $block_content;
		}

		// Resolve default animation (same logic as icon button).
		$settings      = \DesignSetGo\Admin\Settings::get_settings();
		$admin_default = isset( $settings['animations']['default_icon_button_hover'] )
			? sanitize_key( $settings['animations']['default_icon_button_hover'] )
			: 'none';

		$default = $admin_default;
		if ( 'none' === $default ) {
			$theme_default = wp_get_global_settings( array( 'custom', 'designsetgo', 'defaultIconButtonHover' ) );
			if ( ! empty( $theme_default ) && is_string( $theme_default ) ) {
				$theme_default = sanitize_key( $theme_default );
				if ( 'none' !== $theme_default ) {
					$default = $theme_default;
				}
			}
		}

		if ( 'none' === $default || ! in_array( $default, self::ALLOWED_HOVER_ANIMATIONS, true ) ) {
			return $block_content;
		}

		// Find the submit button and add the animation class.
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		while ( $processor->next_tag(
			array(
				'tag_name'   => 'button',
				'class_name' => 'dsgo-form__submit',
			)
		) ) {
			$processor->add_class( 'dsgo-form__submit--' . $default );
			break;
		}

		return $processor->get_updated_html();
	}

	/**
	 * Add DesignSetGo CSS properties to WordPress's safe style allowlist.
	 *
	 * Ensures block inline styles survive wp_kses_post() sanitization
	 * on multisite, REST API, and other filtered contexts.
	 *
	 * @since 2.0.23
	 * @param string[] $styles Existing allowed CSS properties.
	 * @return string[] Extended list with DesignSetGo properties.
	 */
	public function allow_block_style_properties( $styles ) {
		return array_merge( $styles, self::SAFE_STYLE_PROPERTIES );
	}

	/**
	 * Allow CSS function values used by blocks through KSES validation.
	 *
	 * WordPress strips CSS values containing parentheses unless the function
	 * is in its internal allowlist (var, calc, repeat, etc.). Block save()
	 * output uses transform functions (rotate, scale) and filter functions
	 * (blur) that are not in that list. This callback mirrors WordPress's
	 * own approach: strip known-safe functions, then check for unsafe chars.
	 *
	 * @since 2.0.23
	 * @param bool   $allow_css       Whether the CSS value is considered safe.
	 * @param string $css_test_string The CSS declaration to test.
	 * @return bool True if the value is safe.
	 */
	public function allow_block_css_functions( $allow_css, $css_test_string ) {
		if ( $allow_css ) {
			return $allow_css;
		}

		// Reject overly long values to avoid regex backtracking on adversarial input.
		if ( strlen( $css_test_string ) > 1000 ) {
			return false;
		}

		// Strip known-safe CSS functions (same technique WordPress uses for var/calc/repeat).
		$cleaned = preg_replace( self::SAFE_CSS_FUNCTIONS_PATTERN, '', $css_test_string );

		// Fail-safe: reject if preg_replace hits a PCRE limit and returns null.
		if ( null === $cleaned ) {
			return false;
		}

		// If stripping our functions didn't change anything, this isn't our concern.
		if ( $cleaned === $css_test_string ) {
			return $allow_css;
		}

		// After removing safe functions, reject if unsafe characters remain.
		return ! preg_match( '%[\\\(&=}]|/\*%', $cleaned );
	}

	/**
	 * Add SVG and form elements to WordPress's allowed HTML tags for the 'post' context.
	 *
	 * Block save() output includes SVG elements for shape dividers, icons,
	 * comparison table marks, timeline markers, counter decorations, and
	 * accordion toggle indicators. Form blocks (form-builder, form-text-field,
	 * form-email-field, form-select-field, form-textarea-field) output
	 * form, input, select, and option elements that are not in the
	 * default wp_kses_post() allowlist.
	 *
	 * Without this filter, these elements are stripped on REST API content
	 * creation, multisite, and other contexts where wp_kses_post() runs.
	 *
	 * @since 2.0.23
	 * @param array[] $tags    Allowed HTML tags and their attributes.
	 * @param string  $context The KSES context (e.g. 'post').
	 * @return array[] Extended tags with SVG and form elements.
	 */
	public function allow_block_svg_elements( $tags, $context ) {
		if ( 'post' !== $context ) {
			return $tags;
		}

		// SVG elements for shape dividers, icons, etc.
		foreach ( self::SVG_ELEMENTS as $element ) {
			$tags[ $element ] = self::SVG_ATTRIBUTES;
		}

		// Form elements for form blocks.
		foreach ( self::FORM_ELEMENTS as $element => $element_attrs ) {
			$merged = array_merge( self::FORM_GLOBAL_ATTRIBUTES, $element_attrs );
			if ( isset( $tags[ $element ] ) ) {
				$tags[ $element ] = array_merge( $tags[ $element ], $merged );
			} else {
				$tags[ $element ] = $merged;
			}
		}

		// Extend already-allowed tags with missing attributes.
		foreach ( self::FORM_EXTRA_ATTRIBUTES as $element => $extra_attrs ) {
			if ( isset( $tags[ $element ] ) ) {
				$tags[ $element ] = array_merge( $tags[ $element ], $extra_attrs );
			}
		}

		return $tags;
	}
}
