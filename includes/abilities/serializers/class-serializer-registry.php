<?php
/**
 * Maps a block name to the class that serializes it.
 *
 * This replaces a 3,100-line `switch` in Block_Inserter. The mapping is not
 * just tidier: it is ENUMERABLE. get_serialization_gap() used to answer "can
 * this block be inserted?" by calling the switch and checking for null, so the
 * set of serializable blocks could only be discovered one block at a time. A
 * test can now assert the mapping covers every registered non-dynamic block,
 * and that no entry names a block that no longer exists.
 *
 * Several names may share a serializer where the switch had fall-through
 * labels - the flip-card faces and the query containers do.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializer registry.
 */
class Serializer_Registry {

	/**
	 * Block name => serializer class.
	 *
	 * @var array<string, class-string>
	 */
	private const SERIALIZERS = array(
		'designsetgo/section' => Section_Serializer::class,
		'designsetgo/hotspot' => Hotspot_Serializer::class,
		'designsetgo/text-path' => TextPath_Serializer::class,
		'designsetgo/comparison-table' => ComparisonTable_Serializer::class,
		'designsetgo/timeline-item' => TimelineItem_Serializer::class,
		'designsetgo/hotspot-item' => HotspotItem_Serializer::class,
		'designsetgo/advanced-heading' => AdvancedHeading_Serializer::class,
		'designsetgo/blobs' => Blobs_Serializer::class,
		'designsetgo/heading-segment' => HeadingSegment_Serializer::class,
		'designsetgo/timeline' => Timeline_Serializer::class,
		'designsetgo/query' => Query_Serializer::class,
		'designsetgo/query-results' => Query_Serializer::class,
		'designsetgo/query-no-results' => QueryNoResults_Serializer::class,
		'designsetgo/scroll-slide' => ScrollSlide_Serializer::class,
		'designsetgo/scroll-slides' => ScrollSlides_Serializer::class,
		'designsetgo/sticky-sections' => StickySections_Serializer::class,
		'designsetgo/section-divider' => SectionDivider_Serializer::class,
		'designsetgo/fifty-fifty' => FiftyFifty_Serializer::class,
		'designsetgo/row' => Row_Serializer::class,
		'designsetgo/grid' => Grid_Serializer::class,
		'designsetgo/counter-group' => CounterGroup_Serializer::class,
		'designsetgo/counter' => Counter_Serializer::class,
		'designsetgo/flip-card' => FlipCard_Serializer::class,
		'designsetgo/flip-card-face' => FlipCardFace_Serializer::class,
		'designsetgo/flip-card-front' => FlipCardFront_Serializer::class,
		'designsetgo/flip-card-back' => FlipCardBack_Serializer::class,
		'designsetgo/icon' => Icon_Serializer::class,
		'designsetgo/accordion' => Accordion_Serializer::class,
		'designsetgo/accordion-item' => AccordionItem_Serializer::class,
		'designsetgo/divider' => Divider_Serializer::class,
		'designsetgo/countdown-timer' => CountdownTimer_Serializer::class,
		'designsetgo/progress-bar' => ProgressBar_Serializer::class,
		'designsetgo/pill' => Pill_Serializer::class,
		'designsetgo/map' => Map_Serializer::class,
		'designsetgo/card' => Card_Serializer::class,
		'designsetgo/icon-list' => IconList_Serializer::class,
		'designsetgo/icon-list-item' => IconListItem_Serializer::class,
		'designsetgo/icon-button' => IconButton_Serializer::class,
		'designsetgo/modal' => Modal_Serializer::class,
		'designsetgo/modal-trigger' => ModalTrigger_Serializer::class,
		'designsetgo/table-of-contents' => TableOfContents_Serializer::class,
		'designsetgo/image-accordion' => ImageAccordion_Serializer::class,
		'designsetgo/image-accordion-item' => ImageAccordionItem_Serializer::class,
		'designsetgo/scroll-accordion' => ScrollAccordion_Serializer::class,
		'designsetgo/scroll-accordion-item' => ScrollAccordionItem_Serializer::class,
		'designsetgo/slider' => Slider_Serializer::class,
		'designsetgo/slide' => Slide_Serializer::class,
		'designsetgo/scroll-marquee' => ScrollMarquee_Serializer::class,
		'designsetgo/tabs' => Tabs_Serializer::class,
		'designsetgo/tab' => Tab_Serializer::class,
		'designsetgo/form-builder' => FormBuilder_Serializer::class,
	);

	/**
	 * Whether a block has a serializer.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	public static function has( string $block_name ): bool {
		return isset( self::SERIALIZERS[ $block_name ] );
	}

	/**
	 * Every block name with a serializer.
	 *
	 * @return array<int, string>
	 */
	public static function block_names(): array {
		return array_keys( self::SERIALIZERS );
	}

	/**
	 * Build a block's wrapper markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null
	 *                                    when no serializer handles the block.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$serializer = self::SERIALIZERS[ $block_name ] ?? null;

		if ( null === $serializer ) {
			return null;
		}

		return $serializer::wrapper( $block_name, $attributes );
	}
}
