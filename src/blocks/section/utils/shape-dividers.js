/**
 * Shape Divider SVG Library
 *
 * Modern SVG shapes for section dividers.
 * All shapes use viewBox="0 0 1200 120" for consistent proportions.
 * Shapes fill width and scale height based on user settings.
 *
 * @since 1.4.2
 */

import { __ } from '@wordpress/i18n';

/**
 * Shape divider definitions
 * Each shape returns an SVG path element
 */
export const SHAPE_DIVIDERS = {
	// Smooth single wave
	wave: <path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z" />,

	// Gentle double wave
	'wave-double': (
		<path d="M0,40 C150,100 350,0 600,40 C850,80 1050,0 1200,60 L1200,120 L0,120 Z" />
	),

	// Triple layered waves
	'wave-layered': (
		<>
			<path
				d="M0,60 C300,20 600,100 900,40 C1050,10 1150,30 1200,50 L1200,120 L0,120 Z"
				opacity="0.5"
			/>
			<path
				d="M0,80 C200,40 400,100 600,60 C800,20 1000,80 1200,60 L1200,120 L0,120 Z"
				opacity="0.7"
			/>
			<path d="M0,100 C150,70 350,110 600,80 C850,50 1000,90 1200,70 L1200,120 L0,120 Z" />
		</>
	),

	// Asymmetric wave
	'wave-asymmetric': (
		<path d="M0,20 C400,120 800,0 1200,60 L1200,120 L0,120 Z" />
	),

	// Simple diagonal tilt
	tilt: <path d="M0,0 L1200,120 L1200,120 L0,120 Z" />,

	// Opposite diagonal
	'tilt-reverse': <path d="M0,120 L1200,0 L1200,120 L0,120 Z" />,

	// Smooth curved arc
	curve: <path d="M0,120 Q600,0 1200,120 L1200,120 L0,120 Z" />,

	// Inverted curve (bulge up)
	'curve-asymmetric': (
		<path d="M0,80 Q300,0 600,60 Q900,120 1200,40 L1200,120 L0,120 Z" />
	),

	// Center triangle/arrow pointing up
	triangle: <path d="M0,120 L600,0 L1200,120 L0,120 Z" />,

	// Asymmetric triangle
	'triangle-asymmetric': <path d="M0,120 L400,0 L1200,120 L0,120 Z" />,

	// Center arrow pointing down into section
	arrow: <path d="M0,0 L500,0 L600,80 L700,0 L1200,0 L1200,120 L0,120 Z" />,

	// Wide arrow
	'arrow-wide': (
		<path d="M0,0 L400,0 L600,100 L800,0 L1200,0 L1200,120 L0,120 Z" />
	),

	// Mountain peaks
	peaks: (
		<path d="M0,120 L200,40 L400,100 L600,20 L800,80 L1000,10 L1200,90 L1200,120 Z" />
	),

	// Soft mountain peaks
	'peaks-soft': (
		<path d="M0,120 Q100,40 200,80 Q300,20 400,60 Q500,10 600,50 Q700,0 800,40 Q900,20 1000,60 Q1100,40 1200,80 L1200,120 Z" />
	),

	// Zigzag pattern
	zigzag: (
		<path d="M0,120 L100,60 L200,120 L300,60 L400,120 L500,60 L600,120 L700,60 L800,120 L900,60 L1000,120 L1100,60 L1200,120 Z" />
	),

	// Book page effect
	book: (
		<path d="M0,120 Q300,100 600,40 Q900,100 1200,120 L1200,120 L0,120 Z" />
	),

	// Fluffy clouds
	clouds: (
		<path d="M0,120 L0,80 Q100,40 200,80 Q300,20 450,70 Q550,30 650,80 Q750,20 850,70 Q950,40 1050,80 Q1150,50 1200,80 L1200,120 Z" />
	),

	// Rounded drops/bubbles rising from the base
	drops: (
		<path d="M0,120 L0,100 L20,100 A100,95 0 0 1 220,100 L260,100 A100,95 0 0 1 460,100 L500,100 A100,95 0 0 1 700,100 L740,100 A100,95 0 0 1 940,100 L980,100 A100,95 0 0 1 1180,100 L1200,100 L1200,120 Z" />
	),

	// Split center
	split: (
		<path d="M0,0 L550,0 L550,80 L0,80 Z M650,0 L1200,0 L1200,80 L650,80 Z M0,80 L0,120 L1200,120 L1200,80 L650,80 L650,40 L600,80 L550,40 L550,80 L0,80 Z" />
	),

	// Fan/rays — layered translucent rays converging on a bottom-center apex.
	// Rendered as a tonal band-colour overlay (see .is-shape-fan in
	// _shape-divider.scss), so the opacity layers read as graduated rays.
	fan: (
		<>
			<path d="M600,120 L0,0 L1200,0 Z" opacity="0.16" />
			<path d="M600,120 L150,0 L1050,0 Z" opacity="0.3" />
			<path d="M600,120 L300,0 L900,0 Z" opacity="0.44" />
			<path d="M600,120 L430,0 L770,0 Z" opacity="0.6" />
			<path d="M600,120 L520,0 L680,0 Z" opacity="0.8" />
			<path d="M600,120 L575,0 L625,0 Z" />
		</>
	),

	// Stepped pyramid
	steps: (
		<path d="M0,120 L0,100 L120,100 L120,75 L240,75 L240,50 L360,50 L360,25 L480,25 L480,0 L720,0 L720,25 L840,25 L840,50 L960,50 L960,75 L1080,75 L1080,100 L1200,100 L1200,120 Z" />
	),

	// Paper tear effect
	torn: (
		<path d="M0,60 L40,80 L80,50 L140,90 L180,55 L240,85 L300,45 L360,75 L420,55 L480,95 L540,50 L600,80 L660,40 L720,70 L780,50 L840,90 L900,55 L960,85 L1020,45 L1080,75 L1140,60 L1200,80 L1200,120 L0,120 Z" />
	),

	// Slime/drip effect — irregular gooey drips of varying width and length
	slime: (
		<path d="M0,120 L0,40 L67,40 Q57,94 110,94 Q163,94 153,40 L253,40 Q248,110 275,110 Q302,110 297,40 L414,40 Q405,80 450,80 Q495,80 486,40 L614,40 Q608,108 640,108 Q672,108 666,40 L788,40 Q777,90 835,90 Q893,90 882,40 L994,40 Q989,110 1015,110 Q1041,110 1036,40 L1119,40 Q1112,84 1150,84 Q1188,84 1181,40 L1200,40 L1200,120 Z" />
	),

	// Triangle layered — solid triangle rising from the bottom with a reversed
	// (transparent) triangle notch at the top-center; two tonal layers. Rendered
	// as a band-colour overlay (see .is-shape-triangle-layered in
	// _shape-divider.scss), so the transparent notch shows the section background.
	'triangle-layered': (
		<>
			<path
				d="M0,112 L600,50 L0,0 Z M1200,112 L600,50 L1200,0 Z"
				opacity="0.5"
			/>
			<path d="M0,120 H1200 V112 L600,50 L0,112 Z" />
		</>
	),

	// Triangle layered extra — same as triangle-layered but the side wings carry
	// three tonal layers (solid / less-solid / less-less-solid).
	'triangle-layered-extra': (
		<>
			<path
				d="M0,50 L600,50 L0,0 Z M1200,50 L600,50 L1200,0 Z"
				opacity="0.3"
			/>
			<path
				d="M0,112 L600,50 L0,50 Z M1200,112 L600,50 L1200,50 Z"
				opacity="0.6"
			/>
			<path d="M0,120 H1200 V112 L600,50 L0,112 Z" />
		</>
	),

	// Curvy triangle layered — triangle-layered with the edge between the
	// transparent notch and each half-solid wing curved up (as if a circle
	// sits below it).
	'curvy-triangle-layered': (
		<>
			<path
				d="M0,120 L0,0 C300,0 550,60 600,120 Z M1200,120 L1200,0 C900,0 650,60 600,120 Z"
				opacity="0.5"
			/>
			<path d="M0,120 H1200 V107 C950,95 720,65 600,5 C480,65 250,95 0,107 Z" />
		</>
	),

	// Symmetric waves layered — three tonal scalloped-wave layers.
	'symmetric-waves-layered': (
		<>
			<path
				d="M0,120 H1200 V55 C1050,55 1050,15 900,15 C750,15 750,55 600,55 C450,55 450,15 300,15 C150,15 150,55 0,55 Z"
				opacity="0.3"
			/>
			<path
				d="M0,120 H1200 V30 C1050,30 1050,70 900,70 C750,70 750,30 600,30 C450,30 450,70 300,70 C150,70 150,30 0,30 Z"
				opacity="0.5"
			/>
			<path d="M0,120 H1200 V82 C1050,82 1050,42 900,42 C750,42 750,82 600,82 C450,82 450,42 300,42 C150,42 150,82 0,82 Z" />
		</>
	),

	// Side triangle layered — a solid triangle plus a half-tone wedge, a two-tone
	// tonal band (see .is-shape-side-triangle-layered in _shape-divider.scss).
	// Both paths feed the band mask (--dsgo-shape--side-triangle-layered).
	'side-triangle-layered': (
		<>
			<path d="M1200,0 L0,60 L0,120 Z" opacity="0.5" />
			<path d="M1200,0 L1200,120 L0,120 Z" />
		</>
	),

	// Side triangle layered extra — the same band with an added quarter-tone
	// wedge for a deeper three-tone step
	// (see .is-shape-side-triangle-layered-extra in _shape-divider.scss).
	'side-triangle-layered-extra': (
		<>
			<path d="M1200,0 L0,0 L0,60 Z" opacity="0.25" />
			<path d="M1200,0 L0,60 L0,120 Z" opacity="0.5" />
			<path d="M1200,0 L1200,120 L0,120 Z" />
		</>
	),
};

/**
 * Get shape divider options for SelectControl
 *
 * @return {Array} Array of shape options with translated labels
 */
export function getShapeDividerOptions() {
	return [
		{ label: __('None', 'designsetgo'), value: '' },
		{ label: __('Wave', 'designsetgo'), value: 'wave' },
		{ label: __('Wave Double', 'designsetgo'), value: 'wave-double' },
		{ label: __('Wave Layered', 'designsetgo'), value: 'wave-layered' },
		{
			label: __('Wave Asymmetric', 'designsetgo'),
			value: 'wave-asymmetric',
		},
		{ label: __('Tilt', 'designsetgo'), value: 'tilt' },
		{ label: __('Tilt Reverse', 'designsetgo'), value: 'tilt-reverse' },
		{ label: __('Curve', 'designsetgo'), value: 'curve' },
		{
			label: __('Curve Asymmetric', 'designsetgo'),
			value: 'curve-asymmetric',
		},
		{ label: __('Triangle', 'designsetgo'), value: 'triangle' },
		{
			label: __('Triangle Asymmetric', 'designsetgo'),
			value: 'triangle-asymmetric',
		},
		{ label: __('Arrow', 'designsetgo'), value: 'arrow' },
		{ label: __('Arrow Wide', 'designsetgo'), value: 'arrow-wide' },
		{ label: __('Peaks', 'designsetgo'), value: 'peaks' },
		{ label: __('Peaks Soft', 'designsetgo'), value: 'peaks-soft' },
		{ label: __('Zigzag', 'designsetgo'), value: 'zigzag' },
		{ label: __('Book', 'designsetgo'), value: 'book' },
		{ label: __('Clouds', 'designsetgo'), value: 'clouds' },
		{ label: __('Drops', 'designsetgo'), value: 'drops' },
		{ label: __('Split', 'designsetgo'), value: 'split' },
		{ label: __('Fan', 'designsetgo'), value: 'fan' },
		{ label: __('Steps', 'designsetgo'), value: 'steps' },
		{ label: __('Torn Paper', 'designsetgo'), value: 'torn' },
		{ label: __('Slime', 'designsetgo'), value: 'slime' },
		{
			label: __('Triangle Layered', 'designsetgo'),
			value: 'triangle-layered',
		},
		{
			label: __('Triangle Layered Extra', 'designsetgo'),
			value: 'triangle-layered-extra',
		},
		{
			label: __('Curvy Triangle Layered', 'designsetgo'),
			value: 'curvy-triangle-layered',
		},
		{
			label: __('Symmetric Waves Layered', 'designsetgo'),
			value: 'symmetric-waves-layered',
		},
		{
			label: __('Side Triangle Layered', 'designsetgo'),
			value: 'side-triangle-layered',
		},
		{
			label: __('Side Triangle Layered Extra', 'designsetgo'),
			value: 'side-triangle-layered-extra',
		},
	];
}

/**
 * Get a shape divider SVG element
 *
 * @param {string} shapeName - Name of the shape
 * @return {JSX.Element|null} SVG element or null if not found
 */
export function getShapeDivider(shapeName) {
	return SHAPE_DIVIDERS[shapeName] || null;
}

/**
 * Get all shape divider names
 *
 * @return {string[]} Array of shape names
 */
export function getShapeDividerNames() {
	return Object.keys(SHAPE_DIVIDERS);
}
