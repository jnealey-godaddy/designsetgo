<?php
/**
 * Breadcrumbs Helper Functions
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_trail' ) ) {
	/**
	 * Get breadcrumb trail for current post/page
	 *
	 * Hierarchical post types (pages, and any hierarchical custom post type)
	 * list their ancestors. Every other post type lists its archive, when it
	 * has one, and then the first term of its trail taxonomy with that term's
	 * ancestors — `category` for posts, `product_cat` for WooCommerce
	 * products, otherwise the first public hierarchical taxonomy.
	 *
	 * @param WP_Block $block Block instance with context.
	 * @param array    $attributes Block attributes.
	 * @return array Breadcrumb items with title and url.
	 */
	function designsetgo_get_breadcrumb_trail( $block, $attributes ) {
		$trail = array();

		// Get post from block context.
		$post_id = null;
		if ( isset( $block->context['postId'] ) ) {
			$post_id = $block->context['postId'];
		}

		// Outside singular views the page itself is the subject: archives,
		// search, 404 and the posts index have no post to describe.
		if ( designsetgo_breadcrumb_is_page_level_view( $block ) ) {
			$home = designsetgo_get_breadcrumb_home_item( $attributes );
			if ( $home ) {
				$trail[] = $home;
			}

			$trail = array_merge( $trail, designsetgo_get_breadcrumb_context_items( $attributes ) );

			/** This filter is documented below. */
			return apply_filters( 'designsetgo_breadcrumbs_trail', $trail, null, $attributes );
		}

		if ( ! $post_id ) {
			return $trail;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $trail;
		}

		// Add home link if enabled.
		$home = designsetgo_get_breadcrumb_home_item( $attributes );
		if ( $home ) {
			$trail[] = $home;
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$parent_ids = array_reverse( get_post_ancestors( $post->ID ) );
			foreach ( $parent_ids as $parent_id ) {
				// A published child of a draft/private parent must not
				// leak the parent's title or URL to visitors who can't read it.
				if ( ! designsetgo_breadcrumb_can_show_post( $parent_id ) ) {
					continue;
				}

				$trail[] = array(
					'title' => get_the_title( $parent_id ),
					'url'   => get_permalink( $parent_id ),
				);
			}
		} else {
			$trail = array_merge(
				$trail,
				designsetgo_get_breadcrumb_archive_items( $post ),
				designsetgo_get_breadcrumb_term_items( $post )
			);
		}

		// Add current page if enabled.
		if ( ! empty( $attributes['showCurrent'] ) ) {
			$current_item = array(
				'title'      => get_the_title( $post->ID ),
				'url'        => get_permalink( $post->ID ),
				'is_current' => true,
			);
			$trail[]      = $current_item;
		}

		// Allow filtering of breadcrumb trail.
		return apply_filters( 'designsetgo_breadcrumbs_trail', $trail, $post, $attributes );
	}
}

if ( ! function_exists( 'designsetgo_breadcrumb_can_show_post' ) ) {
	/**
	 * Whether the current visitor may see a post's title and URL in a trail.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function designsetgo_breadcrumb_can_show_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return current_user_can( 'read_post', $post->ID );
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_home_item' ) ) {
	/**
	 * The Home crumb, or an empty array when it is turned off.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Breadcrumb item, or array() when hidden.
	 */
	function designsetgo_get_breadcrumb_home_item( $attributes ) {
		if ( empty( $attributes['showHome'] ) ) {
			return array();
		}

		return array(
			'title' => ! empty( $attributes['homeText'] ) ? sanitize_text_field( $attributes['homeText'] ) : __( 'Home', 'designsetgo' ),
			'url'   => home_url( '/' ),
		);
	}
}

if ( ! function_exists( 'designsetgo_breadcrumb_is_page_level_view' ) ) {
	/**
	 * Whether the trail should describe the requested page instead of a post.
	 *
	 * True on archives, search, 404 and the posts index, unless the block sits
	 * inside a query loop (where it describes its item).
	 *
	 * @param object $block Block instance with context.
	 * @return bool
	 */
	function designsetgo_breadcrumb_is_page_level_view( $block ) {
		$context = isset( $block->context ) && is_array( $block->context ) ? $block->context : array();

		foreach ( array( 'queryId', 'designsetgo/queryId', 'designsetgo/currentItemId' ) as $key ) {
			if ( ! empty( $context[ $key ] ) ) {
				return false;
			}
		}

		if ( is_singular() ) {
			return false;
		}

		return is_archive() || is_search() || is_404() || is_home();
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_context_items' ) ) {
	/**
	 * Crumbs after Home for an archive, search, 404 or posts-index view.
	 *
	 * Ancestors of a taxonomy term link; the requested page is the current
	 * crumb and only appears when `showCurrent` is on.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Breadcrumb items.
	 */
	function designsetgo_get_breadcrumb_context_items( $attributes ) {
		$items   = array();
		$current = null;

		if ( is_search() ) {
			$current = array(
				'title' => sprintf(
					/* translators: %s: search query. */
					__( 'Search results for “%s”', 'designsetgo' ),
					get_search_query( false )
				),
				'url'   => get_search_link(),
			);
		} elseif ( is_404() ) {
			$current = array(
				'title' => __( 'Page not found', 'designsetgo' ),
				'url'   => '',
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$ancestor_ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
				foreach ( $ancestor_ids as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					$link     = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : false;
					if ( $ancestor instanceof WP_Term && is_string( $link ) ) {
						$items[] = array(
							'title' => $ancestor->name,
							'url'   => $link,
						);
					}
				}

				$link    = get_term_link( $term );
				$current = array(
					'title' => $term->name,
					'url'   => is_string( $link ) ? $link : '',
				);
			}
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$link      = $post_type ? get_post_type_archive_link( $post_type ) : false;
			$current   = array(
				'title' => post_type_archive_title( '', false ),
				'url'   => $link ? $link : '',
			);
		} elseif ( is_author() ) {
			$author_id = (int) get_queried_object_id();
			$current   = array(
				'title' => get_the_author_meta( 'display_name', $author_id ),
				'url'   => get_author_posts_url( $author_id ),
			);
		} elseif ( is_date() ) {
			if ( is_day() ) {
				$link = get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
			} elseif ( is_month() ) {
				$link = get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
			} else {
				$link = get_year_link( (int) get_query_var( 'year' ) );
			}

			// Drop the archive label prefix so only the date remains.
			add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
			$title = get_the_archive_title();
			remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

			$current = array(
				'title' => $title,
				'url'   => $link,
			);
		} elseif ( is_home() && ! is_front_page() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			$current = array(
				'title' => single_post_title( '', false ),
				'url'   => $page_id ? get_permalink( $page_id ) : '',
			);
		}

		if ( $current && ! empty( $attributes['showCurrent'] ) ) {
			$current['title']      = wp_strip_all_tags( (string) $current['title'] );
			$current['is_current'] = true;
			$items[]               = $current;
		}

		return $items;
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_archive_items' ) ) {
	/**
	 * Post type archive crumb for a custom post type (e.g. the Woo shop page).
	 *
	 * Posts are skipped: their archive is the blog index, which is usually the
	 * home link already.
	 *
	 * @param WP_Post $post Post.
	 * @return array Zero or one breadcrumb item.
	 */
	function designsetgo_get_breadcrumb_archive_items( $post ) {
		if ( 'post' === $post->post_type ) {
			return array();
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || empty( $post_type->has_archive ) ) {
			return array();
		}

		$url = get_post_type_archive_link( $post->post_type );
		if ( ! $url ) {
			return array();
		}

		$title = $post_type->labels->name;

		// Woo's product archive is a real page the shop owner named.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_page_id' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$title = get_the_title( $shop_id );
			}
		}

		return array(
			array(
				'title' => $title,
				'url'   => $url,
			),
		);
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_taxonomy' ) ) {
	/**
	 * Which taxonomy a post type's trail passes through.
	 *
	 * @param string $post_type Post type name.
	 * @return string Taxonomy name, or '' for none.
	 */
	function designsetgo_get_breadcrumb_taxonomy( $post_type ) {
		$defaults = array(
			'post'    => 'category',
			'product' => 'product_cat',
		);

		$taxonomy = '';
		if ( isset( $defaults[ $post_type ] ) ) {
			$taxonomy = $defaults[ $post_type ];
		} else {
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $candidate ) {
				if ( $candidate->hierarchical && $candidate->public ) {
					$taxonomy = $candidate->name;
					break;
				}
			}
		}

		/**
		 * Filter the taxonomy a post type's breadcrumb trail passes through.
		 *
		 * Return '' to show no term crumb for that post type.
		 *
		 * @param string $taxonomy  Taxonomy name, or ''.
		 * @param string $post_type Post type name.
		 */
		$taxonomy = apply_filters( 'designsetgo_breadcrumbs_taxonomy', $taxonomy, $post_type );

		return is_string( $taxonomy ) && taxonomy_exists( $taxonomy ) ? $taxonomy : '';
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_term_items' ) ) {
	/**
	 * Crumbs for a post's lowest-ID term in its trail taxonomy, ancestors first.
	 *
	 * @param WP_Post $post Post.
	 * @return array Breadcrumb items.
	 */
	function designsetgo_get_breadcrumb_term_items( $post ) {
		$taxonomy = designsetgo_get_breadcrumb_taxonomy( $post->post_type );
		if ( '' === $taxonomy ) {
			return array();
		}

		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return array();
		}

		// Deterministic choice: the lowest term ID. get_the_terms() orders by
		// name, which would change the trail when a term is renamed. The block
		// editor preview applies the same rule.
		$first_id = min( wp_list_pluck( $terms, 'term_id' ) );

		$term_ids   = array_reverse( get_ancestors( $first_id, $taxonomy, 'taxonomy' ) );
		$term_ids[] = $first_id;
		$items      = array();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}

			$items[] = array(
				'title' => $term->name,
				'url'   => $url,
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_schema' ) ) {
	/**
	 * Build a Schema.org BreadcrumbList node for a trail.
	 *
	 * @param array $trail Breadcrumb items (title, url).
	 * @return array BreadcrumbList, or an empty array when the trail is empty.
	 */
	function designsetgo_get_breadcrumb_schema( array $trail ) {
		// Titles and term names arrive HTML-encoded (`It&#8217;s`, `A &amp; B`)
		// and JSON-LD never decodes them, so search results would show the
		// codes. The schema builders' normaliser strips tags, then decodes.
		require_once DESIGNSETGO_PATH . 'includes/features/schema-builders.php';

		$elements = array();

		foreach ( array_values( $trail ) as $index => $item ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => designsetgo_schema_normalize_text( (string) $item['title'] ),
			);

			if ( ! empty( $item['url'] ) ) {
				$element['item'] = esc_url_raw( $item['url'] );
			}

			$elements[] = $element;
		}

		if ( empty( $elements ) ) {
			return array();
		}

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $elements,
		);
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_separator' ) ) {
	/**
	 * Get separator character based on attributes
	 *
	 * @param array $attributes Block attributes.
	 * @return string Separator character.
	 */
	function designsetgo_get_breadcrumb_separator( $attributes ) {
		$separator = ! empty( $attributes['separator'] ) ? $attributes['separator'] : 'slash';

		$separators = array(
			'slash'       => '/',
			'chevron'     => '›',
			'greater'     => '>',
			'bullet'      => '•',
			'arrow-right' => '→',
		);

		$glyph = isset( $separators[ $separator ] ) ? $separators[ $separator ] : '/';

		// Directional glyphs must point along the reading direction.
		if ( is_rtl() ) {
			$mirrored = array(
				'›' => '‹',
				'>' => '<',
				'→' => '←',
			);
			$glyph    = isset( $mirrored[ $glyph ] ) ? $mirrored[ $glyph ] : $glyph;
		}

		return $glyph;
	}
}
