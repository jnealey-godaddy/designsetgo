<?php
/**
 * Dynamic Tags — central registry.
 *
 * Stores metadata (label, group, return types, arg schema, discovery
 * callback, capability) for every Dynamic Tag source. The source's
 * value callback itself is registered with WP Core's Block Bindings
 * API via designsetgo_register_bindings_source(); this registry sits
 * alongside the core registry and provides the information the REST
 * endpoints and the editor picker need but core doesn't store.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * In-memory registry for Dynamic Tag sources.
 */
class Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered sources keyed by slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $sources = array();

	/**
	 * Registered groups keyed by slug.
	 *
	 * @var array<string, array{slug:string,label:string,order:int}>
	 */
	private $groups = array();

	/**
	 * Returns the singleton instance.
	 *
	 * @return Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_default_groups();
		}
		return self::$instance;
	}

	/**
	 * Seed the default group list so sources can reference them.
	 */
	private function register_default_groups() {
		$this->register_group( 'post', __( 'Post', 'designsetgo' ), 10 );
		$this->register_group( 'site', __( 'Site', 'designsetgo' ), 20 );
		$this->register_group( 'archive', __( 'Archive', 'designsetgo' ), 30 );
		$this->register_group( 'user', __( 'User', 'designsetgo' ), 40 );
		$this->register_group( 'custom-fields', __( 'Custom Fields', 'designsetgo' ), 50 );
	}

	/**
	 * Registers (or re-orders) a source group.
	 *
	 * @param string $slug  Group slug, e.g. 'post'.
	 * @param string $label Display label.
	 * @param int    $order Sort order (lower first).
	 */
	public function register_group( $slug, $label, $order = 100 ) {
		$this->groups[ $slug ] = array(
			'slug'  => $slug,
			'label' => $label,
			'order' => (int) $order,
		);
	}

	/**
	 * Registers a Dynamic Tag source.
	 *
	 * @param string $slug Source slug (must match the slug passed to
	 *                     designsetgo_register_bindings_source()).
	 * @param array  $meta {
	 *     Source metadata.
	 *
	 *     @type string   $label        Human-readable label.
	 *     @type string   $group        Group slug (must be registered).
	 *     @type string[] $returns      Return types: text|image|url|number|date.
	 *     @type array    $args         Arg schema keyed by arg name.
	 *     @type callable $discovery_cb Optional callback returning
	 *                                  an array of fields for field discovery.
	 *                                  Signature: function( array $context ): array.
	 *     @type callable $resolver     Optional direct resolver. When present,
	 *                                  the image resolver prefers this over
	 *                                  round-tripping the core Bindings API.
	 *                                  Signature: function( array $args, int $post_id ): mixed.
	 *     @type string   $capability   Optional capability required to read.
	 * }
	 */
	public function register_source( $slug, array $meta ) {
		$defaults = array(
			'label'        => $slug,
			'group'        => 'post',
			'returns'      => array( 'text' ),
			'args'         => array(),
			'discovery_cb' => null,
			'resolver'     => null,
			'capability'   => '',
		);
		$this->sources[ $slug ] = array_merge( $defaults, $meta, array( 'slug' => $slug ) );
	}

	/**
	 * Returns all registered sources as a list.
	 *
	 * @param array $filters Optional filters: 'returns' (string|string[]),
	 *                       'group' (string).
	 * @return array<int, array<string, mixed>>
	 */
	public function all_sources( array $filters = array() ) {
		$sources = array_values( $this->sources );

		if ( isset( $filters['group'] ) && '' !== $filters['group'] ) {
			$group   = (string) $filters['group'];
			$sources = array_values(
				array_filter(
					$sources,
					static function ( $source ) use ( $group ) {
						return $source['group'] === $group;
					}
				)
			);
		}

		if ( isset( $filters['returns'] ) ) {
			$wanted  = (array) $filters['returns'];
			$sources = array_values(
				array_filter(
					$sources,
					static function ( $source ) use ( $wanted ) {
						$returns = (array) ( $source['returns'] ?? array() );
						foreach ( $wanted as $type ) {
							if ( in_array( $type, $returns, true ) ) {
								return true;
							}
						}
						return false;
					}
				)
			);
		}

		return $sources;
	}

	/**
	 * Returns a single source by slug or null.
	 *
	 * @param string $slug Source slug.
	 * @return array<string, mixed>|null
	 */
	public function get_source( $slug ) {
		return $this->sources[ $slug ] ?? null;
	}

	/**
	 * Returns the group list sorted by order.
	 *
	 * @return array<int, array{slug:string,label:string,order:int}>
	 */
	public function all_groups() {
		$groups = array_values( $this->groups );
		usort(
			$groups,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		return $groups;
	}

	/**
	 * Runs a source's field-discovery callback if registered.
	 *
	 * @param string $slug    Source slug.
	 * @param array  $context Context array (post_type, post_id, returns).
	 * @return array<int, array<string, mixed>> Fields discovered; empty on failure.
	 */
	public function discover_fields( $slug, array $context = array() ) {
		$source = $this->get_source( $slug );
		if ( null === $source || ! is_callable( $source['discovery_cb'] ?? null ) ) {
			return array();
		}
		$result = call_user_func( $source['discovery_cb'], $context );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Resolves a source value for a given post context.
	 *
	 * Prefers a direct resolver when registered, otherwise falls back to
	 * the core Bindings source's get_value_callback (which is the wrapped
	 * closure from designsetgo_register_bindings_source() and therefore
	 * applies the shared password / viewable / protected-meta gates).
	 *
	 * NOTE ON DIRECT RESOLVERS: when a source registers its own
	 * $source['resolver'], this method invokes it without the shared
	 * gates. Callers registering a direct resolver MUST apply their own
	 * password / viewability / protected-meta checks — or, more commonly,
	 * omit the resolver and rely on the Bindings callback path. Callers
	 * of this method (e.g. RestController::get_preview) must also apply
	 * the password / viewable gate before invoking resolve().
	 *
	 * @param string $slug    Source slug.
	 * @param array  $args    Source args.
	 * @param int    $post_id Post ID context.
	 * @return mixed|null Resolved value or null if unresolvable.
	 */
	public function resolve( $slug, array $args, $post_id = 0 ) {
		$source = $this->get_source( $slug );
		if ( null === $source ) {
			return null;
		}

		// Capability gate for sensitive sources.
		if ( ! empty( $source['capability'] ) && ! current_user_can( (string) $source['capability'] ) ) {
			return null;
		}

		if ( is_callable( $source['resolver'] ?? null ) ) {
			return call_user_func( $source['resolver'], $args, (int) $post_id );
		}

		// Fall back to the core Bindings API value callback.
		if ( function_exists( 'get_block_bindings_source' ) ) {
			$binding = get_block_bindings_source( $slug );
			if ( $binding && isset( $binding->get_value_callback ) && is_callable( $binding->get_value_callback ) ) {
				$args['__dsgo_post_id'] = (int) $post_id;
				return call_user_func( $binding->get_value_callback, $args, null, 'content' );
			}
		}

		return null;
	}
}
