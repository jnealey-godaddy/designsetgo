# Visual Effects Cluster Implementation Plan

> For Codex: REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add close-Elementor-parity Hotspot and Text Path blocks, and extend Advanced Heading with rotating and highlighted headline modes, with accessible static fallbacks.

**Architecture:** Hotspot is a parent/child block pair with percentage-positioned children and a delegated frontend controller. Text Path renders safe normalized data into inline SVG; a narrow REST route parses custom SVG and returns only a viewBox and first valid path. Animated Headline extends Advanced Heading and Heading Segment, using Text Reveal's splitter only where it applies.

**Tech Stack:** Gutenberg apiVersion 3, React, WordPress REST/PHP DOM parsing, inline SVG, CSS custom properties, Jest, PHPUnit, Playwright, wp-env.

---

## Constraints

- Read the current Text Reveal, SVG Patterns, and Interaction Layers frontend sources before changing their public contracts.
- Use DsgoInspectorPanel in Settings, Style, Advanced order. Native block supports own standard typography, color, spacing, border, and link styling.
- Keep Text Path static. Saved content contains only SVG path data, never raw user SVG.
- Do not add a global SVG upload MIME exception. The route below parses but never stores uploads.
- Add KSES allowances only for text, textpath, and required presentation attributes. Do not allow script, foreignObject, animation elements, event attributes, use, or arbitrary SVG markup.

### Task 1: Build a shared Text Path data contract

**Files:**

- Create: src/utils/svg-paths.js
- Create: tests/unit/svg-paths.test.js
- Modify: src/extensions/svg-patterns/patterns.js

**Step 1: Write failing tests**

Test stable preset data for wave, arc, circle, line, oval, and spiral. Test normaliseTextPathData accepts only a four-number viewBox and permitted SVG path commands, returns exactly viewBox/d, and rejects empty, oversized, and unsafe data. Test extractTextPathFromSvg rejects parser errors, DOCTYPE, ENTITY, script, foreignObject, non-SVG roots, and missing paths; it returns only the first usable path.

**Step 2: Verify failure**

Run: npx wp-scripts test-unit-js tests/unit/svg-paths.test.js

Expected: FAIL because the module does not exist.

**Step 3: Implement minimal safe utility**

Export preset records, normaliseTextPathData(candidate), isSafePathData(d), and extractTextPathFromSvg(svg). Apply the 12 KB length limit before the path grammar regex. Parse with DOMParser, reject disallowed elements, and normalize the selected data. Refactor SVG Patterns only where a non-rendering helper removes duplication; do not change its background data-URI output.

**Step 4: Verify and commit**

Run: npx wp-scripts test-unit-js tests/unit/svg-paths.test.js tests/unit/svg-patterns.test.js

Expected: PASS.

    git add src/utils/svg-paths.js src/extensions/svg-patterns/patterns.js tests/unit/svg-paths.test.js
    git commit -m "feat: add safe text path utilities"

### Task 2: Add custom SVG extraction

**Files:**

- Create: includes/blocks/text-path/class-text-path-controller.php
- Create: tests/phpunit/text-path-controller-test.php
- Modify: includes/class-plugin.php

**Step 1: Write failing tests**

Test parse_svg_path returns only a viewBox/path array for a simple SVG. Reject DOCTYPE, ENTITY, script, foreignObject, non-SVG roots, missing paths, malformed XML, and input over 12 KB. Test the REST permission callback denies a user without upload_files.

**Step 2: Verify failure**

Run: vendor/bin/phpunit tests/phpunit/text-path-controller-test.php

Expected: FAIL because the controller does not exist.

**Step 3: Implement the narrow endpoint**

Add DesignSetGo Blocks Text_Path Controller and POST /designsetgo/v1/text-path/extract. Require upload_files; cap raw input at 12 KB; reject DOCTYPE and ENTITY before parsing; use LIBXML_NONET, LIBXML_NOERROR, and LIBXML_NOWARNING; require an SVG root; and return only normalized first-path data. Do not write files, add upload_mimes, return raw XML, or make the route public. Register it beside the existing query route.

**Step 4: Verify and commit**

Run: vendor/bin/phpunit tests/phpunit/text-path-controller-test.php

Expected: PASS.

    git add includes/blocks/text-path/class-text-path-controller.php includes/class-plugin.php tests/phpunit/text-path-controller-test.php
    git commit -m "feat: add safe text path SVG extraction"

### Task 3: Build Text Path

**Files:**

- Create: src/blocks/text-path/block.json
- Create: src/blocks/text-path/index.js
- Create: src/blocks/text-path/edit.js
- Create: src/blocks/text-path/save.js
- Create: src/blocks/text-path/style.scss
- Create: src/blocks/text-path/editor.scss
- Create: src/blocks/text-path/components/TextPathControls.js
- Create: src/blocks/text-path/components/CustomPathUpload.js
- Create: src/blocks/text-path/utils.js
- Create: src/blocks/text-path/test/save.test.js
- Modify: includes/class-plugin.php

**Step 1: Write failing serialization tests**

Assert every preset saves a stable path id, textPath href, start offset, direction, and a link only when configured. Assert hidden-path mode emits no visible path. Assert malformed custom data falls back to wave and never serializes raw SVG.

**Step 2: Verify failure**

Run: npx wp-scripts test-unit-js src/blocks/text-path/test/save.test.js

Expected: FAIL because the block does not exist.

**Step 3: Implement metadata, save, and editor**

Use apiVersion 3, useBlockProps.save, useUniqueBlockId, and attributes for text, path type, custom path, show path, size, rotation, start offset, word spacing, direction, URL, target, and rel. Use native color/typography/spacing/border/anchor supports. Save one SVG containing defs/path, optional visible path, and text/textPath; wrap only valid URLs in an anchor. Clear the id on duplicate.

Extend the existing KSES list only for required tags/attributes. The editor Settings panel owns preset/text/custom SVG/link/direction/path visibility. Style owns size/rotation/start/word spacing/text colors/path stroke. The custom upload posts body text to Task 2's route and updates attributes only after a valid response. Canvas markup equals save markup.

**Step 4: Verify and commit**

Run:

    npx wp-scripts test-unit-js src/blocks/text-path/test/save.test.js tests/unit/svg-paths.test.js
    npm run build
    grep -c "wp-block-designsetgo-text-path" build/blocks/text-path/style-index.css

Expected: tests/build pass; grep is at least 1.

    git add src/blocks/text-path includes/class-plugin.php
    git commit -m "feat: add text path block"

### Task 4: Build Hotspot parent and child blocks

**Files:**

- Create: src/blocks/hotspot/block.json
- Create: src/blocks/hotspot/index.js
- Create: src/blocks/hotspot/edit.js
- Create: src/blocks/hotspot/save.js
- Create: src/blocks/hotspot/style.scss
- Create: src/blocks/hotspot/editor.scss
- Create: src/blocks/hotspot/components/HotspotCanvas.js
- Create: src/blocks/hotspot/components/HotspotInspector.js
- Create: src/blocks/hotspot-item/block.json
- Create: src/blocks/hotspot-item/index.js
- Create: src/blocks/hotspot-item/edit.js
- Create: src/blocks/hotspot-item/save.js
- Create: src/blocks/hotspot-item/style.scss
- Create: src/blocks/hotspot-item/editor.scss
- Create: src/blocks/hotspot-item/components/HotspotItemInspector.js
- Create: src/blocks/hotspot/test/save.test.js
- Create: src/blocks/hotspot-item/test/save.test.js

**Step 1: Write failing tests**

Assert parent allows only items, provides shared defaults, and saves media image/alt text. Assert child clamps x/y to 0–100, saves CSS variables and stable marker/tooltip ids, saves an anchor if URL exists and button otherwise, and carries click-mode aria-expanded/aria-controls. Assert duplication blanks the child id.

**Step 2: Verify failure**

Run: npx wp-scripts test-unit-js src/blocks/hotspot/test/save.test.js src/blocks/hotspot-item/test/save.test.js

Expected: FAIL because neither block is registered.

**Step 3: Implement parent and child**

The parent owns media, default trigger/placement/width/animation/sequence duration, and shared styles. Use useInnerBlocksProps with two starter children and DsgoChildToolbar. Context supplies defaults. The image wrapper is the coordinate system.

The child uses useUniqueBlockId. Attributes cover x/y, origins, label, icon, URL, tooltip RichText, tooltip position/width, trigger override, animation, sequence order. Render a native button without URL and anchor with one; use RichText.Content only.

**Step 4: Implement editor measurement**

HotspotCanvas calculates pointer coordinates from the image rectangle, clamps them, and updates only the selected dragged item. Provide numeric fallback controls. Arrow keys move selected items 1 percent; Shift plus arrows moves 10 percent. An editor-only live region announces new coordinates.

**Step 5: Verify and commit**

Run:

    npx wp-scripts test-unit-js src/blocks/hotspot/test/save.test.js src/blocks/hotspot-item/test/save.test.js
    npm run build

Expected: PASS.

    git add src/blocks/hotspot src/blocks/hotspot-item
    git commit -m "feat: add hotspot blocks"

### Task 5: Add delegated Hotspot behavior

**Files:**

- Create: src/blocks/hotspot/view.js
- Create: src/blocks/hotspot/test/view.test.js
- Modify: src/blocks/hotspot/block.json
- Modify: src/blocks/hotspot/style.scss

**Step 1: Write failing DOM tests**

Test click toggles only its tooltip and synchronizes ARIA state. Test Escape/outside-click close. Test hover also opens on focus and does not close while focus/pointer remains in marker or tooltip. Test links preserve navigation. Test two parent blocks attach one document listener per event type.

**Step 2: Verify failure**

Run: npx wp-scripts test-unit-js src/blocks/hotspot/test/view.test.js

Expected: FAIL because view.js does not exist.

**Step 3: Implement controller and styles**

Follow Interaction Layers' single delegated-listener pattern; do not reparse generic interaction action arrays. Click mode allows one open child per parent. Guard duplicate initialization. Add pulse/scale/fade/sequence/tooltip transitions with selectors scoped by :where below the block roots. Under prefers-reduced-motion, force final static state and remove transitions/animation.

**Step 4: Verify and commit**

Run:

    npx wp-scripts test-unit-js src/blocks/hotspot/test/view.test.js tests/unit/extensions/interactions-frontend.test.js
    npm run build

Expected: PASS.

    git add src/blocks/hotspot
    git commit -m "feat: add accessible hotspot interactions"

### Task 6: Enhance Heading Segment

**Files:**

- Modify: src/blocks/heading-segment/block.json
- Modify: src/blocks/heading-segment/edit.js
- Modify: src/blocks/heading-segment/save.js
- Create: src/blocks/heading-segment/components/AnimatedWordsControl.js
- Create: src/blocks/heading-segment/test/animated-segment.test.js

**Step 1: Write failing tests**

Prove normal output is byte-identical. Test animated role saves first valid word as static fallback, stores ordered non-empty words, and refuses animation data when role is normal.

**Step 2: Implement and verify**

Add normal-default headlineRole and animatedWords array. The word-list control adds, edits, removes, reorders, and never stores empty entries. Animated segments save a dedicated wrapper/data payload; normal markup stays unchanged.

Run: npx wp-scripts test-unit-js src/blocks/heading-segment/test/animated-segment.test.js

Expected: PASS.

    git add src/blocks/heading-segment
    git commit -m "feat: add animated heading segments"

### Task 7: Extend Advanced Heading

**Files:**

- Modify: src/blocks/advanced-heading/block.json
- Modify: src/blocks/advanced-heading/edit.js
- Modify: src/blocks/advanced-heading/save.js
- Modify: src/blocks/advanced-heading/style.scss
- Modify: src/blocks/advanced-heading/editor.scss
- Create: src/blocks/advanced-heading/components/AnimatedHeadlinePanel.js
- Create: src/blocks/advanced-heading/test/animated-headline.test.js

**Step 1: Write failing tests**

Verify default output remains unchanged. Verify rotating mode saves only allowed effect, bounded timing, loop data, and one animated child reference. Verify highlighted mode emits an allowed shape and decorative SVG.

**Step 2: Implement**

Add nullable animatedHeadline data: mode, effect, shape, duration, delay, loop, URL, target, rel. Show controls only with one animated segment; otherwise show a concise notice. Selecting a segment clears the previous animated role. Keep existing heading level and segment editing; do not create a standalone block.

Add allowlisted rotating effects: typing, clip, flip, swirl, blinds, drop-in, wave, slide, slide-down. Add static local highlight shapes: circle, curly, underline, double, double underline, zigzag, diagonal, strikethrough, X. Use custom properties.

**Step 3: Verify and commit**

Run:

    npx wp-scripts test-unit-js src/blocks/advanced-heading/test/animated-headline.test.js src/blocks/heading-segment/test/animated-segment.test.js
    npm run build

Expected: PASS.

    git add src/blocks/advanced-heading src/blocks/heading-segment
    git commit -m "feat: add animated headline modes"

### Task 8: Add Animated Headline runtime

**Files:**

- Create: src/blocks/advanced-heading/view.js
- Create: src/blocks/advanced-heading/test/view.test.js
- Modify: src/blocks/advanced-heading/block.json
- Modify: src/blocks/advanced-heading/style.scss
- Modify: src/extensions/text-reveal/frontend.js
- Modify: tests/unit/extensions/text-reveal-split.test.js

**Step 1: Write failing tests**

Test one readable active word, final word on non-looping behavior, first static word/no timer under reduced motion, and no duplicate timer on repeated initialization. Add a Text Reveal regression proving existing wrapTextNodes is used only for compatible effects and stays idempotent.

**Step 2: Implement and verify**

Read only allowlisted data, revalidate timing/effects, store timers in a WeakMap, pause while document hidden, and never create a timer under reduced motion. Reuse exported wrapTextNodes for compatible effects; do not create a second splitter.

Run:

    npx wp-scripts test-unit-js src/blocks/advanced-heading/test/view.test.js tests/unit/extensions/text-reveal-split.test.js
    npm run build

Expected: PASS.

    git add src/blocks/advanced-heading src/extensions/text-reveal tests/unit/extensions/text-reveal-split.test.js
    git commit -m "feat: animate advanced headings"

### Task 9: Run browser, accessibility, and final gates

**Files:**

- Create: tests/e2e/visual-effects-cluster.spec.js

**Step 1: Add end-to-end cases**

Verify marker drag/save/reload, keyboard tooltip close, normal link navigation, Text Path presets/custom rejection/save/reload, and both headline modes. Emulate reduced motion and assert every feature is visible/static with no console errors.

**Step 2: Run gates**

    npm run build
    npm run lint:js
    npm run lint:css
    npm run lint:php
    npm run test:unit
    npm run test:php
    npm run test:e2e -- tests/e2e/visual-effects-cluster.spec.js
    git diff main...HEAD --check

Expected: all pass; final command reports no whitespace errors.

**Step 3: Manual audit and commit**

At desktop/mobile widths, tab through markers and confirm focus, reading order, Escape close, and no hover-only content. Inspect Text Path LTR/RTL. Enable reduced motion and confirm every effect is visible/static.

    git add tests/e2e/visual-effects-cluster.spec.js
    git commit -m "test: cover visual effects cluster"

Request review focused on SVG parsing, KSES serialization, keyboard popovers, reduced-motion fallback, and Text Reveal reuse.
