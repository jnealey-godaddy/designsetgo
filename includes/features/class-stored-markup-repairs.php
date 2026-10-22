<?php
/**
 * Corrects markup that older versions of the plugin stored.
 *
 * Modal Trigger used to write spacing-preset padding raw
 * (`padding-top:var:preset|spacing|40`), which browsers drop, so the button
 * had no padding. Card used to give its badge `role="status"` and a default
 * `aria-label` of "Badge", so a "Sale" badge was announced as "Badge" and
 * acted as a live region. Both save() functions are fixed, and deprecations
 * migrate the markup the next time a post is saved. Six patterns used to put
 * their logo wordmarks in a no-wrap Row, which pushes the page sideways on
 * phones; the patterns now wrap, but pages built from them keep the old Row.
 * This repairs the stored markup at render time so existing pages are right
 * without a re-save.
 *
 * @package DesignSetGo
 * @since 2.11.1
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time repairs for markup older block versions stored.
 */
class Stored_Markup_Repairs {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/modal-trigger', array( $this, 'repair_preset_padding' ) );
		add_filter( 'render_block_designsetgo/card', array( $this, 'repair_card_badge' ) );
		add_filter( 'render_block_designsetgo/row', array( $this, 'repair_wordmark_row' ), 10, 2 );
	}

	/**
	 * Let a no-wrap Row of logo wordmarks wrap on phones and small tablets.
	 *
	 * Matches the shape those patterns stored rather than their text, so rows
	 * whose names were replaced with real ones are caught too: three or more
	 * children, every one a paragraph with a font-size preset and letter
	 * spacing, in a Row whose inner flex box has `flex-wrap:nowrap`. Other
	 * no-wrap Rows (icon + label, avatar + name) don't match. The added class
	 * wraps only below the tablet breakpoint (see row/style.scss); wider
	 * screens keep the row exactly as stored.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function repair_wordmark_row( $block_content, $block ) {
		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		if ( count( $inner ) < 3 || false === strpos( (string) $block_content, 'nowrap' ) ) {
			return $block_content;
		}

		foreach ( $inner as $child ) {
			$attrs = isset( $child['attrs'] ) && is_array( $child['attrs'] ) ? $child['attrs'] : array();
			if (
				'core/paragraph' !== ( $child['blockName'] ?? '' )
				|| empty( $attrs['fontSize'] )
				|| empty( $attrs['style']['typography']['letterSpacing'] )
			) {
				return $block_content;
			}
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() || ! $processor->has_class( 'dsgo-flex' ) ) {
			return $block_content;
		}
		$processor->set_bookmark( 'row' );

		if ( ! $processor->next_tag( array( 'class_name' => 'dsgo-flex__inner' ) ) ) {
			return $block_content;
		}
		$style = (string) $processor->get_attribute( 'style' );
		if ( ! preg_match( '/flex-wrap\s*:\s*nowrap/i', $style ) ) {
			return $block_content;
		}

		$processor->seek( 'row' );
		$processor->add_class( 'dsgo-flex--wrap-narrow' );

		return $processor->get_updated_html();
	}

	/**
	 * Turn raw `var:preset|spacing|40` values into `var(--wp--preset--spacing--40)`.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string
	 */
	public function repair_preset_padding( $block_content ) {
		if ( false === strpos( (string) $block_content, 'var:preset|' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		while ( $processor->next_tag() ) {
			$style = $processor->get_attribute( 'style' );
			if ( ! is_string( $style ) || false === strpos( $style, 'var:preset|' ) ) {
				continue;
			}
			$processor->set_attribute(
				'style',
				(string) preg_replace( '/var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)/i', 'var(--wp--preset--$1--$2)', $style )
			);
		}

		return $processor->get_updated_html();
	}

	/**
	 * Drop the stored badge's live-region role and its default label.
	 *
	 * A label other than the default "Badge" is kept: it was a deliberate name
	 * for the badge. It lives only in the markup (the attribute is sourced from
	 * it), so it is read from there.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string
	 */
	public function repair_card_badge( $block_content ) {
		if ( false === strpos( (string) $block_content, 'role="status"' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if (
			$processor->next_tag(
				array(
					'tag_name'   => 'span',
					'class_name' => 'dsgo-card__badge',
				)
			) && 'status' === $processor->get_attribute( 'role' )
		) {
			$processor->remove_attribute( 'role' );
			$label = (string) $processor->get_attribute( 'aria-label' );
			if ( in_array( $label, array( 'Badge', __( 'Badge', 'designsetgo' ) ), true ) ) {
				$processor->remove_attribute( 'aria-label' );
			}
		}

		return $processor->get_updated_html();
	}
}
