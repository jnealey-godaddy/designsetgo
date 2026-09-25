/**
 * Slider Inspector Tests
 *
 * Renders the Slider block's inspector (SliderInspector, composed of
 * SettingsPanel + StylePanel + the Arrow/Dot color groups) with mocked
 * WordPress packages, and asserts:
 *  - Both DsgoInspectorPanel surfaces (Settings, Style) render.
 *  - A representative control from each field group is present.
 *  - Controls gated behind a toggle (autoplay, scroll-driven, arrow/dot
 *    appearance, aspect ratio vs. min height) appear and disappear with
 *    that toggle.
 */

import { render, screen, within } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ────────────────────────────────────────────────

// hasValue() of every rendered ToolsPanelItem, by label. ToolsPanel offers a
// per-item "Reset" in its ⋮ menu exactly when hasValue() is true.
const mockItemHasValue = new Map();

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
	sprintf: (text, ...args) =>
		args.reduce((str, arg) => str.replace(/%[ds]/, arg), text),
}));

jest.mock('@wordpress/block-editor', () => ({
	InspectorControls: ({ children }) => <div>{children}</div>,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown: ({ title, settings }) => (
		<div role="group" aria-label={title}>
			{(settings || []).map((setting) => (
				<span key={setting.label}>{setting.label}</span>
			))}
		</div>
	),
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients: () => ({
		colors: [],
	}),
}));

jest.mock('@wordpress/components', () => {
	const SelectControl = ({ label, value, options, onChange }) => (
		<label>
			{label}
			<select
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			>
				{(options || []).map((opt) => (
					<option key={opt.value} value={opt.value}>
						{opt.label}
					</option>
				))}
			</select>
		</label>
	);

	const TextControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<input
				type="text"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
		</label>
	);

	const RangeControl = ({ label, value, onChange, disabled }) => (
		<label>
			{label}
			<input
				type="range"
				value={value}
				disabled={disabled}
				onChange={(e) => onChange(Number(e.target.value))}
				aria-label={label}
			/>
		</label>
	);

	const ToggleControl = ({ label, checked, onChange }) => (
		<label>
			{label}
			<input
				type="checkbox"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				aria-label={label}
			/>
		</label>
	);

	const UnitControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<input
				type="text"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
		</label>
	);

	const Notice = ({ children }) => <div role="alert">{children}</div>;

	// ToolsPanelItem: isShownByDefault=false items are hidden (like the real
	// WP component). Every DsgoInspectorPanel.Item in the Slider inspector
	// passes isShownByDefault, so this only matters if that ever regresses.
	const ToolsPanelItem = ({
		children,
		isShownByDefault,
		label,
		hasValue,
	}) => {
		mockItemHasValue.set(label, hasValue);
		return isShownByDefault !== false ? <>{children}</> : null;
	};

	const ToolsPanel = ({ children, label }) => (
		<fieldset aria-label={label}>{children}</fieldset>
	);

	return {
		SelectControl,
		TextControl,
		RangeControl,
		ToggleControl,
		Notice,
		// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
		__experimentalUnitControl: UnitControl,
		// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
		__experimentalToolsPanel: ToolsPanel,
		// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
		__experimentalToolsPanelItem: ToolsPanelItem,
	};
});

// ─── Component under test ──────────────────────────────────────────────────

import SliderInspector from '../../../../src/blocks/slider/components/inspector/SliderInspector';

// ─── Helpers ────────────────────────────────────────────────────────────────

// Mirrors src/blocks/slider/block.json defaults.
const DEFAULT_ATTRIBUTES = {
	slidesPerView: 1,
	slidesPerViewTablet: 1,
	slidesPerViewMobile: 1,
	height: '',
	aspectRatio: '16/9',
	useAspectRatio: false,
	gap: '20px',
	showArrows: true,
	showDots: true,
	arrowStyle: 'default',
	arrowPosition: 'sides',
	arrowVerticalPosition: 'center',
	arrowColor: '',
	arrowBackgroundColor: '',
	arrowSize: '24px',
	arrowPadding: '',
	dotStyle: 'default',
	dotPosition: 'inside',
	dotColor: '',
	effect: 'slide',
	transitionDuration: '0.5s',
	transitionEasing: 'ease-in-out',
	autoplay: false,
	autoplayInterval: 3000,
	pauseOnHover: true,
	pauseOnInteraction: true,
	loop: true,
	draggable: true,
	swipeable: true,
	freeMode: false,
	centeredSlides: false,
	mobileBreakpoint: 768,
	tabletBreakpoint: 1024,
	ariaLabel: '',
	scrollDriven: false,
	scrollDrivenSpeed: 1,
};

const SINGLE_SLIDE_NOTICE =
	'Fade and Zoom transitions show one slide per view. Switch back to the Slide effect to display multiple slides at once.';

function renderInspector(attributeOverrides = {}, propOverrides = {}) {
	const attributes = { ...DEFAULT_ATTRIBUTES, ...attributeOverrides };
	const setAttributes = jest.fn();
	const onEffectChange = jest.fn();
	const requiresSingleSlideEffect = ['fade', 'zoom'].includes(
		attributes.effect
	);

	const utils = render(
		<SliderInspector
			attributes={attributes}
			setAttributes={setAttributes}
			clientId="test-client"
			onEffectChange={onEffectChange}
			requiresSingleSlideEffect={requiresSingleSlideEffect}
			singleSlideNotice={SINGLE_SLIDE_NOTICE}
			{...propOverrides}
		/>
	);

	return { ...utils, setAttributes, onEffectChange };
}

// ─── Tests ──────────────────────────────────────────────────────────────────

describe('SliderInspector', () => {
	describe('panels', () => {
		it('renders the Settings and Style DsgoInspectorPanel surfaces', () => {
			renderInspector();

			expect(
				screen.getByRole('group', { name: 'Settings' })
			).toBeInTheDocument();
			expect(
				screen.getByRole('group', { name: 'Style' })
			).toBeInTheDocument();
		});

		it('never renders a legacy block-prefixed panel title', () => {
			renderInspector();

			expect(
				screen.queryByText(/Slider Settings/i)
			).not.toBeInTheDocument();
			expect(
				screen.queryByText(/Layout Settings/i)
			).not.toBeInTheDocument();
			expect(
				screen.queryByText(/Navigation Settings/i)
			).not.toBeInTheDocument();
		});
	});

	describe('Settings panel — representative controls', () => {
		it('renders layout, navigation, transition, autoplay, and behavior fields', () => {
			renderInspector();

			expect(
				screen.getByLabelText('Slides Per View (Desktop)')
			).toBeInTheDocument();
			expect(screen.getByLabelText('Show Arrows')).toBeInTheDocument();
			expect(screen.getByLabelText('Show Dots')).toBeInTheDocument();
			expect(
				screen.getByLabelText('Transition Effect')
			).toBeInTheDocument();
			expect(
				screen.getByLabelText('Enable Auto-play')
			).toBeInTheDocument();
			expect(screen.getByLabelText('Loop')).toBeInTheDocument();
			expect(
				screen.getByLabelText('Scroll-Driven Horizontal')
			).toBeInTheDocument();
			expect(screen.getByLabelText('ARIA Label')).toBeInTheDocument();
		});
	});

	describe('Style panel — representative controls', () => {
		it('renders sizing and appearance fields', () => {
			renderInspector();

			expect(
				screen.getByLabelText('Use Aspect Ratio')
			).toBeInTheDocument();
			expect(
				screen.getByLabelText('Gap Between Slides')
			).toBeInTheDocument();
		});
	});

	describe('conditional: autoplay', () => {
		it('hides interval/pause controls when autoplay is off', () => {
			renderInspector({ autoplay: false });

			expect(
				screen.queryByLabelText('Auto-play Interval (ms)')
			).not.toBeInTheDocument();
			expect(
				screen.queryByLabelText('Pause on Hover')
			).not.toBeInTheDocument();
			expect(
				screen.queryByLabelText('Pause on Interaction')
			).not.toBeInTheDocument();
		});

		it('shows interval/pause controls when autoplay is on', () => {
			renderInspector({ autoplay: true });

			expect(
				screen.getByLabelText('Auto-play Interval (ms)')
			).toBeInTheDocument();
			expect(screen.getByLabelText('Pause on Hover')).toBeInTheDocument();
			expect(
				screen.getByLabelText('Pause on Interaction')
			).toBeInTheDocument();
		});
	});

	describe('conditional: scroll-driven', () => {
		it('hides Scroll Speed when scroll-driven is off', () => {
			renderInspector({ scrollDriven: false });

			expect(
				screen.queryByLabelText('Scroll Speed')
			).not.toBeInTheDocument();
		});

		it('shows Scroll Speed when scroll-driven is on', () => {
			renderInspector({ scrollDriven: true });

			expect(screen.getByLabelText('Scroll Speed')).toBeInTheDocument();
		});
	});

	describe('conditional: arrow appearance + arrow colors', () => {
		it('hides arrow appearance fields and the Arrow Colors group when arrows are off', () => {
			renderInspector({ showArrows: false });

			expect(
				screen.queryByLabelText('Arrow Style')
			).not.toBeInTheDocument();
			expect(
				screen.queryByLabelText('Arrow Size')
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole('group', { name: 'Arrow Colors' })
			).not.toBeInTheDocument();
		});

		it('shows arrow appearance fields and the Arrow Colors group when arrows are on', () => {
			renderInspector({ showArrows: true });

			expect(screen.getByLabelText('Arrow Style')).toBeInTheDocument();
			expect(
				screen.getByLabelText('Arrow Position (Horizontal)')
			).toBeInTheDocument();
			expect(
				screen.getByLabelText('Arrow Position (Vertical)')
			).toBeInTheDocument();
			expect(screen.getByLabelText('Arrow Size')).toBeInTheDocument();
			expect(screen.getByLabelText('Arrow Padding')).toBeInTheDocument();
			expect(
				screen.getByRole('group', { name: 'Arrow Colors' })
			).toBeInTheDocument();
		});
	});

	describe('conditional: dot appearance + dot color', () => {
		it('hides dot appearance fields and the Dot Color group when dots are off', () => {
			renderInspector({ showDots: false });

			expect(
				screen.queryByLabelText('Dot Style')
			).not.toBeInTheDocument();
			expect(
				screen.queryByLabelText('Dot Position')
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole('group', { name: 'Dot Color' })
			).not.toBeInTheDocument();
		});

		it('shows dot appearance fields and the Dot Color group when dots are on', () => {
			renderInspector({ showDots: true });

			expect(screen.getByLabelText('Dot Style')).toBeInTheDocument();
			expect(screen.getByLabelText('Dot Position')).toBeInTheDocument();
			expect(
				screen.getByRole('group', { name: 'Dot Color' })
			).toBeInTheDocument();
		});
	});

	describe('conditional: aspect ratio vs. min height', () => {
		it('shows the Aspect Ratio select and hides Min Height when enabled', () => {
			renderInspector({ useAspectRatio: true });

			expect(screen.getByLabelText('Aspect Ratio')).toBeInTheDocument();
			expect(
				screen.queryByLabelText('Min Height')
			).not.toBeInTheDocument();
		});

		it('shows Min Height and hides the Aspect Ratio select when disabled', () => {
			renderInspector({ useAspectRatio: false });

			expect(screen.getByLabelText('Min Height')).toBeInTheDocument();
			expect(
				screen.queryByLabelText('Aspect Ratio')
			).not.toBeInTheDocument();
		});
	});

	describe('conditional: single-slide transition effect', () => {
		it('disables the slides-per-view controls and shows the notice for fade/zoom', () => {
			renderInspector({ effect: 'fade' });

			expect(
				screen.getByLabelText('Slides Per View (Desktop)')
			).toBeDisabled();
			expect(screen.getByText(SINGLE_SLIDE_NOTICE)).toBeInTheDocument();
		});

		it('leaves the slides-per-view controls enabled for the slide effect', () => {
			renderInspector({ effect: 'slide' });

			expect(
				screen.getByLabelText('Slides Per View (Desktop)')
			).not.toBeDisabled();
			expect(
				screen.queryByText(SINGLE_SLIDE_NOTICE)
			).not.toBeInTheDocument();
		});
	});

	describe('user interaction', () => {
		it('calls setAttributes when Show Arrows is toggled', () => {
			const { setAttributes } = renderInspector({ showArrows: true });

			screen.getByLabelText('Show Arrows').click();

			expect(setAttributes).toHaveBeenCalledWith({
				showArrows: false,
			});
		});

		it('routes Transition Effect changes through onEffectChange', () => {
			const { onEffectChange } = renderInspector({ effect: 'slide' });

			const select = screen.getByLabelText('Transition Effect');
			select.value = 'fade';
			select.dispatchEvent(new Event('change', { bubbles: true }));

			expect(onEffectChange).toHaveBeenCalledWith('fade');
		});
	});
	describe('reset menu', () => {
		beforeEach(() => mockItemHasValue.clear());

		it('offers no reset for the toggles scroll-driven mode locks', () => {
			renderInspector({
				scrollDriven: true,
				loop: false,
				swipeable: false,
				draggable: false,
			});
			['Loop', 'Swipeable (Touch)', 'Draggable (Mouse)'].forEach(
				(label) => expect(mockItemHasValue.get(label)()).toBe(false)
			);
		});

		it('offers the reset again once scroll-driven mode is off', () => {
			renderInspector({
				scrollDriven: false,
				loop: false,
				swipeable: false,
				draggable: false,
			});
			['Loop', 'Swipeable (Touch)', 'Draggable (Mouse)'].forEach(
				(label) => expect(mockItemHasValue.get(label)()).toBe(true)
			);
		});

		it('labels menu entries with the control labels, which are translated', () => {
			renderInspector({ autoplay: true });
			[
				'Mobile Breakpoint (px)',
				'Tablet Breakpoint (px)',
				'Auto-play Interval (ms)',
			].forEach((label) =>
				expect(mockItemHasValue.has(label)).toBe(true)
			);
			[
				'Mobile Breakpoint',
				'Tablet Breakpoint',
				'Auto-play Interval',
			].forEach((label) =>
				expect(mockItemHasValue.has(label)).toBe(false)
			);
		});
	});

	describe('Dynamic Query notices', () => {
		it('renders them first inside the Settings panel', () => {
			renderInspector({}, { notices: <p>Bound to a query</p> });
			const settings = screen.getByRole('group', { name: 'Settings' });
			const notice = within(settings).getByText('Bound to a query');
			expect(settings.firstElementChild).toContainElement(notice);
		});

		it('renders no notice wrapper outside query mode', () => {
			const { container } = renderInspector();
			expect(
				container.querySelector('.dsgo-slider-inspector__notices')
			).toBeNull();
		});
	});
});
