# Block Editor UI/UX Audit

**Date:** April 16, 2026  
**Scope:** DesignSetGo custom WordPress blocks  
**Method:** Code inspection of live block implementations plus review of existing QA screenshots  

## Overview

This audit focuses on editor usability, ease of use, onboarding, discoverability, and authoring flow for each custom block in the plugin.

It is based on:

- `block.json` metadata and block supports
- `edit.js` implementations
- placeholder and template chooser flows
- nested block constraints and inserter behavior
- inspector control structure and toolbar usage
- existing QA screenshots for representative frontend states

This is not a frontend design critique in isolation. The emphasis is editor experience: how easy it is for a content author to insert, configure, understand, and repeat each block.

## Highest-Priority Findings

### 1. Child insertion discoverability is inconsistent

Several composite blocks hide or weaken the normal Gutenberg affordance for adding child content.

- `Slider` disables the default appender without a clear replacement in the current editor surface.
- `Advanced Heading` disables the segment appender and does not surface an obvious "Add Segment" action.
- `Reveal` hides its appender entirely.

These three are the clearest usability regressions because they block the author's next step.

Relevant files:

- [slider/edit.js](src/blocks/slider/edit.js#L240)
- [slider/edit.js](src/blocks/slider/edit.js#L363)
- [advanced-heading/edit.js](src/blocks/advanced-heading/edit.js#L62)
- [reveal/edit.js](src/blocks/reveal/edit.js#L26)

### 2. `Section` and `Row` auto-transform too aggressively

`Section` silently converts to `Row` when orientation becomes horizontal, and `Row` silently converts to `Section` when orientation becomes vertical.

That is technically clever, but it is a confusing authoring model. The selected block can effectively change identity without the user explicitly choosing a transform.

This should become:

- an explicit transform action
- a contextual notice
- an easy undo path

Relevant files:

- [section/edit.js](src/blocks/section/edit.js#L163)
- [row/edit.js](src/blocks/row/edit.js#L121)

### 3. The plugin already contains good onboarding patterns, but only for some blocks

The strongest onboarding flows currently appear in:

- `Modal`
- `Sticky Sections`
- `Scroll Slides`
- `Product Categories Grid`
- `Product Showcase Hero`

These blocks use template choosers, placeholders, or setup states well. That pattern should be standardized for all complex, composite, or data-driven blocks.

Relevant files:

- [ModalPlaceholder.js](src/blocks/modal/components/ModalPlaceholder.js#L35)
- [StickySectionsPlaceholder.js](src/blocks/sticky-sections/components/StickySectionsPlaceholder.js#L30)
- [ScrollSlidesPlaceholder.js](src/blocks/scroll-slides/components/ScrollSlidesPlaceholder.js#L30)
- [product-categories-grid/edit.js](src/blocks/product-categories-grid/edit.js#L253)
- [product-showcase-hero/edit.js](src/blocks/product-showcase-hero/edit.js#L258)

### 4. Some of the most powerful blocks are too sidebar-driven

These blocks expose a lot of capability, but ask the author to understand too many options before getting a strong initial result:

- `Slider`
- `Form Builder`
- `Comparison Table`
- `Modal`
- `Card`

These should move toward:

- variation picker on insert
- strong starter templates
- progressive disclosure of advanced controls
- more inline editing for common tasks

Relevant files:

- [slider/edit.js](src/blocks/slider/edit.js#L367)
- [form-builder/edit.js](src/blocks/form-builder/edit.js#L200)
- [comparison-table/edit.js](src/blocks/comparison-table/edit.js#L364)
- [modal/edit.js](src/blocks/modal/edit.js#L139)
- [card/edit.js](src/blocks/card/edit.js#L72)

## Cross-Cutting Recommendations

### Standardize empty-state UX

For any block that is:

- composite
- interactive
- content-structured
- data-driven
- visually opinionated

default to one of these on first insert:

- template chooser
- variation picker
- setup placeholder
- guided starter state

### Move high-frequency actions into toolbars or canvas UI

Authors should not have to open the sidebar for their most common next step.

Examples:

- add tab
- add slide
- duplicate item
- reorder item
- flip media side
- rename nav item
- connect modal trigger to modal

### Standardize child navigators

Several blocks would benefit from the same reusable editor pattern:

- item list
- current selection
- add button
- duplicate button
- reorder controls
- quick settings

This would apply to:

- `Tabs`
- `Slider`
- `Accordion`
- `Timeline`
- `Scroll Slides`
- `Sticky Sections`
- `Form Builder`

### Reduce numeric-only controls where the editor already knows the items

Avoid author-facing controls like "Default Expanded Item = 3" when the editor can show actual child names.

Prefer:

- item pickers
- inline chips
- dropdowns populated from real child blocks

### Prefer preset-first workflows for expressive blocks

For visually rich blocks, do not start with a blank configuration problem.

Start with:

- a few good presets
- a visible preview
- a simple first-choice decision

Then allow deeper customization.

### Keep inspector information architecture consistent

Use:

- `Settings` for behavior and structure
- `Styles` and style subsections for appearance
- toolbar for quick actions
- canvas controls for direct-manipulation actions

The repo already documents this direction and should continue applying it consistently.

## Block-by-Block Audit

### Layout Containers

#### `Section`

Current strengths:

- good width constraint controls
- strong shape divider capability
- good use of WordPress supports

Usability issues:

- silent conversion to `Row`
- no guided empty-state starters
- shape divider setup is powerful but not visually guided

Recommendations:

- replace silent orientation swap with explicit transform
- add starter layouts for common section structures
- add visual shape-divider presets with thumbnails
- show nested-section behavior more clearly when padding is auto-cleared

Relevant files:

- [section/edit.js](src/blocks/section/edit.js#L163)
- [section/edit.js](src/blocks/section/edit.js#L328)

#### `Row`

Current strengths:

- mobile stacking control is clear
- width constraint control is solid

Usability issues:

- silent conversion to `Section`
- lacks starter patterns for common horizontal layouts

Recommendations:

- make transforms explicit
- add quick layout starters for 2, 3, and 4 child columns
- add row reversal control
- show a mobile preview hint next to `Stack on Mobile`

Relevant files:

- [row/edit.js](src/blocks/row/edit.js#L121)
- [row/edit.js](src/blocks/row/edit.js#L268)

#### `Grid`

Current strengths:

- responsive controls are well covered
- width and gap settings are clear

Usability issues:

- configuration is capable but abstract
- no first-insert layout presets

Recommendations:

- add starter presets such as `features`, `cards`, `logos`, `gallery`
- add device preview toggles beside responsive column controls
- expose common span/reorder workflows more directly for children

Relevant files:

- [grid/edit.js](src/blocks/grid/edit.js#L325)

#### `Fifty Fifty`

Current strengths:

- toolbar flip action is excellent
- media selection is easy
- one of the better insert-to-success flows in the repo

Usability issues:

- no variation picker on insert
- missing strong alt-text guidance

Recommendations:

- add `image left`, `image right`, `copy-heavy`, and `media-heavy` variations
- warn when image exists without alt text
- offer preset content structures

Relevant files:

- [fifty-fifty/edit.js](src/blocks/fifty-fifty/edit.js#L156)
- [fifty-fifty/edit.js](src/blocks/fifty-fifty/edit.js#L243)

#### `Blobs`

Current strengths:

- expressive decorative capability

Usability issues:

- likely too parameterized for a decorative block

Recommendations:

- move to preset-first setup
- reduce reliance on manual tuning
- add decorative intensity presets

### Interactive / Composite Blocks

#### `Accordion`

Current strengths:

- good parent-child structure
- clear item template
- item-level open state is understandable

Usability issues:

- authors must manage initial state item by item
- no quick batch actions

Recommendations:

- add parent actions for `Add item`, `Open first`, `Expand all`, `Collapse all`
- surface item count and current default-open items in parent UI

Relevant files:

- [accordion/edit.js](src/blocks/accordion/edit.js#L103)

#### `Accordion Item`

Current strengths:

- in-canvas title editing is good
- item open/close behavior is visible

Usability issues:

- no quick duplicate or reorder in header
- default-open state is easy to miss in larger accordions

Recommendations:

- add duplicate and reorder affordances in the item header
- show an "opens by default" chip when enabled

Relevant files:

- [accordion-item/edit.js](src/blocks/accordion-item/edit.js#L173)

#### `Tabs`

Current strengths:

- active tab selection also selects child block
- keyboard navigation is handled
- parent-child relationship is strong

Usability issues:

- tab management is still too sidebar-dependent
- no obvious in-canvas add/duplicate/reorder action

Recommendations:

- enable inline tab renaming in the tab strip
- add `+ Add Tab`
- add duplicate and drag reorder for tabs
- make active/inactive editing feel more direct

Relevant files:

- [tabs/edit.js](src/blocks/tabs/edit.js#L87)
- [tabs/edit.js](src/blocks/tabs/edit.js#L515)

#### `Tab`

Current strengths:

- inactive-tab notice is helpful

Usability issues:

- title, icon, and anchor editing are still isolated to inspector

Recommendations:

- move tab label editing into the navigation UI
- add inline icon picker entry point from nav

Relevant files:

- [tab/edit.js](src/blocks/tab/edit.js#L77)

#### `Slider`

Current strengths:

- very capable feature set
- editor-only arrows and dots help preview interaction

Usability issues:

- disabled appender is a major discoverability issue
- configuration burden is high before first success
- lacks slide navigator / thumbnail workflow

Recommendations:

- restore a clear add-slide path immediately
- add slide navigator with thumbnail or title chips
- add duplicate/remove/reorder slide actions
- introduce slider presets such as `hero`, `gallery`, `testimonials`, `logos`
- collapse advanced options behind presets

Relevant files:

- [slider/edit.js](src/blocks/slider/edit.js#L240)
- [slider/edit.js](src/blocks/slider/edit.js#L363)
- [slider/edit.js](src/blocks/slider/edit.js#L1091)

#### `Slide`

Current strengths:

- strong background/media controls
- sensible starter content template

Usability issues:

- repetitive slide authoring could be faster

Recommendations:

- add slide templates
- add duplicate previous slide action
- expose common background/content/alignment controls in a compact inline panel

Relevant files:

- [slide/edit.js](src/blocks/slide/edit.js#L107)
- [slide/edit.js](src/blocks/slide/edit.js#L186)

#### `Flip Card`

Current strengths:

- seeded front/back structure is good
- guards against duplicate faces

Usability issues:

- front and back are conceptually strong but not visually guided enough in editor

Recommendations:

- add clear in-canvas face labels
- add preview toggle for front/back while editing
- add starter variations by use case

Relevant files:

- [flip-card/edit.js](src/blocks/flip-card/edit.js#L67)

#### `Flip Card Front`

Recommendations:

- add stronger face labeling in canvas
- offer starter content variants

Relevant files:

- [flip-card-front/edit.js](src/blocks/flip-card-front/edit.js#L15)

#### `Flip Card Back`

Recommendations:

- same as front
- make back-side editing feel intentionally distinct

Relevant files:

- [flip-card-back/edit.js](src/blocks/flip-card-back/edit.js#L15)

#### `Image Accordion`

Current strengths:

- clear overall control groups
- good core concept

Usability issues:

- `Default Expanded Item` is numeric instead of semantic
- likely too dark / heavy in default visual expression

Recommendations:

- replace numeric item selector with actual child-item picker
- add starter visual themes
- improve mobile-state explanation

Relevant files:

- [image-accordion/edit.js](src/blocks/image-accordion/edit.js#L207)

#### `Image Accordion Item`

Current strengths:

- content alignment controls are simple

Usability issues:

- background/media setup is not direct enough for a media-led block

Recommendations:

- add inline media dropzone
- add contrast warning for overlay plus text
- improve visibility of per-item identity in editor

Relevant files:

- [image-accordion-item/edit.js](src/blocks/image-accordion-item/edit.js#L66)

#### `Scroll Accordion`

Current strengths:

- strong starter content template
- simple top-level alignment toolbar

Usability issues:

- parent block is under-explained for a visually specialized experience
- lacks richer parent controls and navigator

Recommendations:

- add inspector guidance for pinning, progression, and spacing
- add item navigator and reorder controls

Relevant files:

- [scroll-accordion/edit.js](src/blocks/scroll-accordion/edit.js#L45)
- [scroll-accordion/edit.js](src/blocks/scroll-accordion/edit.js#L220)

#### `Scroll Accordion Item`

Recommendations:

- add inline label or summary in parent navigator
- expose item state more clearly in-canvas

#### `Scroll Slides`

Current strengths:

- strong setup chooser
- inline nav heading editing is very good
- active-slide mental model is clear

Usability issues:

- would benefit from richer slide management

Recommendations:

- add thumbnails
- add drag reorder
- add slide count and active-state strip

Relevant files:

- [scroll-slides/edit.js](src/blocks/scroll-slides/edit.js#L176)
- [scroll-slides/edit.js](src/blocks/scroll-slides/edit.js#L206)

#### `Scroll Slide`

Recommendations:

- add duplicate previous slide action
- add quick style transfer between slides

#### `Sticky Sections`

Current strengths:

- good template chooser
- simple settings

Usability issues:

- authors need better visibility into section order and sticky offset impact

Recommendations:

- add section navigator
- add reorder affordances
- add sticky-offset preview ruler

Relevant files:

- [sticky-sections/edit.js](src/blocks/sticky-sections/edit.js#L47)

#### `Timeline`

Current strengths:

- clear settings split
- strong starter template

Usability issues:

- timeline authoring is still list-like rather than journey-like

Recommendations:

- add variations such as `history`, `roadmap`, `process`
- add parent-level duplicate/reorder tools
- show a stronger overview of all items

Relevant files:

- [timeline/edit.js](src/blocks/timeline/edit.js#L112)

#### `Timeline Item`

Current strengths:

- date/title editing is straightforward
- image and link support are useful

Usability issues:

- link editing is still overly inspector-based
- item identification could be stronger in long timelines

Recommendations:

- move link editing into a toolbar action
- add clearer item chips or summaries in the editor

Relevant files:

- [timeline-item/edit.js](src/blocks/timeline-item/edit.js#L203)

#### `Scroll Marquee`

Current strengths:

- row-based editing is fairly direct
- performance warning is a good guardrail

Usability issues:

- row management lacks drag reorder
- heavy-image use is still easy to overdo

Recommendations:

- add row reordering
- add marquee starter presets
- improve image budget messaging and optimization guidance

Relevant files:

- [scroll-marquee/edit.js](src/blocks/scroll-marquee/edit.js#L37)
- [scroll-marquee/edit.js](src/blocks/scroll-marquee/edit.js#L201)

### Content / Presentation Blocks

#### `Card`

Current strengths:

- highly flexible
- supports multiple visual and layout modes

Usability issues:

- too many decisions before getting a good result
- image and CTA workflows are not as guided as they should be

Recommendations:

- move to preset-first onboarding
- add clearer CTA editing mode
- add whole-card click option
- add stronger alt-text warning

Relevant files:

- [card/edit.js](src/blocks/card/edit.js#L72)
- [card/edit.js](src/blocks/card/edit.js#L223)

#### `Advanced Heading`

Current strengths:

- strong concept
- heading-level toolbar is good

Usability issues:

- add-segment flow is not discoverable

Recommendations:

- add visible `Add Segment` action
- add segment chips / navigator
- provide quick heading style variations

Relevant files:

- [advanced-heading/edit.js](src/blocks/advanced-heading/edit.js#L62)

#### `Heading Segment`

Recommendations:

- make duplication and reorder easier
- surface segment editing affordances more clearly

#### `Icon`

Current strengths:

- good accessibility section

Usability issues:

- icon selection could be faster for repeated use

Recommendations:

- add recent/favorite icons
- add stronger required-label guidance when not decorative
- add quicker inline picker entry point

Relevant files:

- [icon/edit.js](src/blocks/icon/edit.js#L145)

#### `Icon Button`

Current strengths:

- inline link popover follows good core button patterns

Usability issues:

- common icon and variant actions are still too hidden

Recommendations:

- add quick icon and variant toolbar controls
- improve modal/action integration patterns

Relevant files:

- [icon-button/edit.js](src/blocks/icon-button/edit.js#L216)

#### `Icon List`

Current strengths:

- good shared parent settings model

Usability issues:

- batch authoring can be faster

Recommendations:

- support bulk paste from newline-separated text
- add global icon sync action for children

Relevant files:

- [icon-list/edit.js](src/blocks/icon-list/edit.js#L100)

#### `Icon List Item`

Recommendations:

- improve duplicate / reorder speed
- surface per-item editing in a more list-native way

#### `Pill`

Current strengths:

- simple editing model

Usability issues:

- no preset visual vocabulary

Recommendations:

- add style presets such as `badge`, `status`, `eyebrow`, `label`

Relevant files:

- [pill/edit.js](src/blocks/pill/edit.js#L55)

#### `Divider`

Current strengths:

- simple and compact control set

Usability issues:

- style names are less effective than visual previews

Recommendations:

- convert divider style options into visual thumbnails

Relevant files:

- [divider/edit.js](src/blocks/divider/edit.js#L47)

#### `Counter Group`

Current strengths:

- good parent-child structure
- strong defaults

Usability issues:

- formatting logic is hard to visualize from sidebar alone

Recommendations:

- add layout presets
- add live number formatting preview

Relevant files:

- [counter-group/index.js](src/blocks/counter-group/index.js#L153)

#### `Counter`

Current strengths:

- clear child-level override model

Usability issues:

- repeated counter authoring could be faster

Recommendations:

- add prefix/suffix presets
- improve duplicate-styled-counter flow

Relevant files:

- [counter/edit.js](src/blocks/counter/edit.js#L162)

#### `Progress Bar`

Current strengths:

- clear control grouping
- preview is understandable

Usability issues:

- percentage is numeric only
- style selection is still abstract

Recommendations:

- allow drag-to-set directly on the bar
- add style presets

Relevant files:

- [progress-bar/edit.js](src/blocks/progress-bar/edit.js#L162)

#### `Countdown Timer`

Current strengths:

- live editor preview
- clear completion handling

Usability issues:

- too much repeated inspector setup code
- date entry could be faster for common use cases

Recommendations:

- add relative-date shortcuts
- add explicit expired-state preview toggle
- simplify author mental model around completion states

Relevant files:

- [countdown-timer/edit.js](src/blocks/countdown-timer/edit.js#L79)
- [countdown-timer/edit.js](src/blocks/countdown-timer/edit.js#L429)

#### `Comparison Table`

Current strengths:

- inline cell editing is strong
- row actions are already present

Usability issues:

- column management is still sidebar-heavy
- importing / batch editing is missing

Recommendations:

- add CSV or table paste import
- add duplicate column action
- move CTA link editing closer to column headers

Relevant files:

- [comparison-table/edit.js](src/blocks/comparison-table/edit.js#L431)
- [comparison-table/edit.js](src/blocks/comparison-table/edit.js#L782)

### Form Blocks

#### `Form Builder`

Current strengths:

- strong base template
- broad capability

Usability issues:

- too much setup burden for common cases
- some mappings use raw text where structured selection would be better

Recommendations:

- add a setup wizard
- add form presets such as `contact`, `newsletter`, `event registration`, `lead capture`
- replace free-text mapping fields like reply-to source with dropdowns populated from actual form fields
- add form summary panel showing required fields, email target, spam settings

Relevant files:

- [form-builder/edit.js](src/blocks/form-builder/edit.js#L148)
- [form-builder/edit.js](src/blocks/form-builder/edit.js#L714)

#### `Form Text Field`

Recommendations:

- add inline label/placeholder editing
- reuse a shared field settings panel pattern

#### `Form Email Field`

Recommendations:

- add stronger email-source guidance for notifications
- provide common email presets

#### `Form Textarea Field`

Recommendations:

- add message presets and quick size options

#### `Form URL Field`

Recommendations:

- add example validation hints and common placeholder presets

#### `Form Number Field`

Recommendations:

- add use-case presets such as `quantity`, `budget`, `team size`

#### `Form Phone Field`

Current strengths:

- reasonably clear formatting controls

Usability issues:

- format and country setup can still feel abstract

Recommendations:

- show a live example preview when format or country changes
- auto-update placeholder based on chosen format and country

Relevant files:

- [form-phone-field/edit.js](src/blocks/form-phone-field/edit.js#L82)
- [form-phone-field/edit.js](src/blocks/form-phone-field/edit.js#L192)

#### `Form Date Field`

Recommendations:

- add relative min/max presets
- add more user-friendly scheduling presets

#### `Form Time Field`

Recommendations:

- add common slot presets like business hours and 15-minute increments

#### `Form Select Field`

Recommendations:

- support bulk option paste and drag reorder

#### `Form Checkbox Field`

Recommendations:

- add consent templates with optional linked policy text

#### `Form Hidden Field`

Recommendations:

- present hidden values as metadata chips in canvas
- add quick sources like URL param, page title, post ID, current date

### Data / Utility Blocks

#### `Modal`

Current strengths:

- template chooser is strong
- overall block architecture is good

Usability issues:

- modal/trigger pairing is still manual
- open-state preview could be clearer

Recommendations:

- allow creating a trigger directly from a modal
- allow jumping from a trigger to its target modal
- add open-state and focus-order preview in editor

Relevant files:

- [modal/edit.js](src/blocks/modal/edit.js#L124)
- [modal/edit.js](src/blocks/modal/edit.js#L149)

#### `Modal Trigger`

Current strengths:

- scans page for available modals

Usability issues:

- warning-only state when no modal exists

Recommendations:

- offer `Create Modal` directly
- show connection status chip
- add jump-to-target action

Relevant files:

- [modal-trigger/edit.js](src/blocks/modal-trigger/edit.js#L64)
- [modal-trigger/edit.js](src/blocks/modal-trigger/edit.js#L152)

#### `Table of Contents`

Current strengths:

- helpful empty and warning states
- scanning hook model is good

Usability issues:

- source headings are not strongly surfaced to the author

Recommendations:

- add source-heading list in inspector
- allow clicking from TOC entry to originating heading block
- allow per-heading exclusion

Relevant files:

- [table-of-contents/edit.js](src/blocks/table-of-contents/edit.js#L52)

#### `Breadcrumbs`

Current strengths:

- preview is contextual

Usability issues:

- preview states could be more actionable

Recommendations:

- show clearer context badges
- improve loading / empty-state guidance

Relevant files:

- [breadcrumbs/edit.js](src/blocks/breadcrumbs/edit.js#L201)

#### `Map`

Current strengths:

- editor preview communicates configuration state

Usability issues:

- still feels like a placeholder rather than a map authoring tool

Recommendations:

- add geocode search
- show provider/config status
- add lightweight live-preview mode when feasible

Relevant files:

- [map/edit.js](src/blocks/map/edit.js#L97)

#### `Product Categories Grid`

Current strengths:

- setup and empty states are good
- manual vs all-categories mode is understandable

Usability issues:

- manual curation could be faster

Recommendations:

- add drag reorder in manual mode
- add visual emphasis controls for featured categories
- add style presets

Relevant files:

- [product-categories-grid/edit.js](src/blocks/product-categories-grid/edit.js#L105)

#### `Product Showcase Hero`

Current strengths:

- current-product vs manual-product flow is clear
- good setup and sample-preview messaging

Usability issues:

- authors still need to compose too much manually for common hero patterns

Recommendations:

- add hero composition presets
- add stronger CTA / content display presets

Relevant files:

- [product-showcase-hero/edit.js](src/blocks/product-showcase-hero/edit.js#L128)
- [product-showcase-hero/edit.js](src/blocks/product-showcase-hero/edit.js#L209)

## Suggested Roadmap

### Quick Wins

- restore missing or hidden child inserter flows
- replace silent block transforms with explicit transforms
- add inline add/duplicate/reorder actions to composite blocks
- replace numeric child selectors with item pickers

### Shared Editor Infrastructure

- reusable template chooser / setup shell
- reusable child navigator
- reusable preset-first variation picker
- reusable summary panel for complex blocks

### Block-Specific Follow-Up Work

- `Slider` navigator and presets
- `Form Builder` wizard and structured mappings
- `Tabs` nav editing
- `Comparison Table` import and duplicate column flow
- `Modal` / `Modal Trigger` pairing workflow

## Summary

The codebase already has many strong building blocks for better editor UX:

- strong use of templates
- good block supports usage
- some excellent setup placeholders
- several good toolbar patterns

The main gap is consistency.

The blocks that feel best today are the ones that:

- start with a guided state
- provide useful defaults
- keep common actions in the canvas or toolbar
- avoid making the author think in implementation details

The next phase should focus less on adding raw capability and more on making the existing capability easier to discover, easier to repeat, and easier to trust.
