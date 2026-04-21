# Query Block — Template Picker Onboarding

**Date**: 2026-04-21
**Status**: Approved

## Problem

The query block registers 6 variations with `scope: ['inserter']`, producing 7 inserter tiles for a single block family (base block + 6 variants). This clutters the inserter and diverges from the onboarding pattern used by other compound blocks (tabs, slider, etc.).

## Decision

Option A: strip `scope: ['inserter']` from variations, add a `DsgoBlockPlaceholder`-based template picker — identical to the tabs pattern.

## Changes

### 1. `src/blocks/query/variations.js`
Remove `scope: ['inserter']` from all 6 variation entries. Variations remain registered for block transforms and programmatic use; they simply no longer appear as separate inserter tiles.

### 2. `src/blocks/query/templates.js` (new)
Thin adapter that maps each variation to the `DsgoBlockPlaceholder` template shape (`name`, `title`, `description`, `icon`, `attributes`, `innerBlocks`). Imports from `variations.js` to avoid data duplication.

### 3. `src/blocks/query/components/QueryPlaceholder.js` (new)
Mirrors `TabsPlaceholder` exactly:
- Wraps `DsgoBlockPlaceholder`
- Icon: `editor-table`
- Label: `__('Dynamic Query', 'designsetgo')`
- Instructions: `__('Pick a starting layout for your query.', 'designsetgo')`
- Templates: imported from `templates.js`

### 4. `src/blocks/query/edit.js`
Add the `innerBlocks.length === 0` early-return guard (same pattern as tabs) that renders `<QueryPlaceholder clientId={clientId} setAttributes={setAttributes} />` before the main edit UI.

## What Does NOT Change

- `block.json` — no schema changes
- Sibling blocks (`query-filter`, `query-pagination`, `query-no-results`, `query-group-header`) — untouched
- Variation data (attributes, innerBlocks) — same as today
- Deprecations — not needed (no attribute or HTML structure change)
