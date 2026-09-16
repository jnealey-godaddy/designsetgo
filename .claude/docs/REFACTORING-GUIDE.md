# Code Refactoring Guide

> **Note:** The extracted inspector-panel examples below show `PanelBody`, which was the pattern at the time this guide was written. Current blocks wrap extracted panels in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` (Settings → Style → Advanced) instead of a bare `PanelBody` — see `../CLAUDE.md` ("Inspector IA (Theme 3)"). The file-size and extraction guidance below is otherwise current.

## File Size Limits

**Hard Rule**: No single file should exceed **300 lines** (excluding pure data/constants).

**Why**:
- Files >300 lines become hard to navigate and understand
- Testing becomes difficult
- Changes require excessive context switching
- Code reviews become overwhelming

**Action**: When a file exceeds 300 lines, refactor it following the patterns below.

## Refactored Block File Structure (Standard Pattern)

**Use this structure for ALL custom blocks:**

```
src/blocks/{block-name}/
├── index.js (40-60 lines)          # Registration only
│   ├── Import edit, save, metadata
│   ├── Import styles (editor.scss, style.scss)
│   └── registerBlockType() call
│
├── edit.js (100-150 lines)         # Focused edit component
│   ├── Import extracted panels
│   ├── Import extracted utilities
│   ├── Attribute destructuring
│   ├── Calculate styles using utilities
│   ├── Return JSX with panels
│   └── NO inline PanelBody components
│
├── save.js (as-is)                 # Usually already good
│
├── components/
│   └── inspector/                   # One file per panel
│       ├── SettingsPanel1.js (60-150 lines)
│       ├── SettingsPanel2.js (60-150 lines)
│       └── SettingsPanel3.js (60-150 lines)
│
├── utils/                           # Pure functions
│   ├── style-calculator.js (60-120 lines)
│   ├── formatter.js (60-100 lines)
│   └── data-library.js (any size - pure data)
│
├── editor.scss                      # Editor-only styles
└── style.scss                       # Frontend styles
```

## Refactoring Pattern (Step-by-Step)

**When a block file exceeds 300 lines, follow these steps:**

### 1. Analyze and Backup
```bash
# Check file size
wc -l src/blocks/{block-name}/index.js

# Backup original
cp src/blocks/{block-name}/index.js src/blocks/{block-name}/index.js.backup
```

### 2. Extract Pure Utilities First
Create `utils/` directory and extract:
- Style calculation functions
- Number/string formatters
- Data transformations
- Icon/image libraries
- Any pure functions (no side effects)

**Example:**
```javascript
// utils/style-calculator.js
/**
 * Calculate container inner styles
 * Pure function - 100% testable
 */
export const calculateInnerStyles = (attributes) => {
  const { layoutType, constrainWidth, contentWidth, gridColumns, gap } = attributes;

  const styles = {
    position: 'relative',
    zIndex: 2,
  };

  if (layoutType === 'grid') {
    styles.display = 'grid';
    styles.gridTemplateColumns = `repeat(${gridColumns}, 1fr)`;
    styles.gap = gap;
  }

  return styles;
};
```

**Why Pure Functions First**:
- Immediately testable without WordPress
- No dependencies
- Can be used in both edit.js and save.js
- Simplifies component extraction

### 3. Extract Inspector Panels
Create `components/inspector/` directory with one file per `<PanelBody>`.

**Panel Component Pattern:**
```javascript
// components/inspector/LayoutPanel.js
import { __ } from '@wordpress/i18n';
import { PanelBody, ToggleGroupControl, TextControl } from '@wordpress/components';

/**
 * Layout Panel - Controls for layout type and gap
 *
 * @param {Object} props - Component props
 * @param {string} props.layoutType - Current layout type
 * @param {string} props.gap - Gap between items
 * @param {Function} props.setAttributes - Function to update attributes
 */
export const LayoutPanel = ({ layoutType, gap, setAttributes }) => {
  return (
    <PanelBody title={__('Layout', 'designsetgo')} initialOpen={true}>
      <ToggleGroupControl
        label={__('Layout Type', 'designsetgo')}
        value={layoutType}
        onChange={(value) => setAttributes({ layoutType: value })}
        isBlock
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      >
        {/* Controls */}
      </ToggleGroupControl>

      <TextControl
        label={__('Gap', 'designsetgo')}
        value={gap}
        onChange={(value) => setAttributes({ gap: value })}
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      />
    </PanelBody>
  );
};
```

**Panel Naming Convention**:
- `{Feature}Panel.js` - e.g., `LayoutPanel.js`, `GridPanel.js`
- Export as named export: `export const LayoutPanel = ...`
- Always include JSDoc comments

### 4. Create Focused edit.js
Move edit logic from index.js to new edit.js file.

**Edit Component Pattern:**
```javascript
// edit.js
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { LayoutPanel } from './components/inspector/LayoutPanel';
import { GridPanel } from './components/inspector/GridPanel';
import { calculateInnerStyles } from './utils/style-calculator';

export default function BlockEdit({ attributes, setAttributes }) {
  const { layoutType, gridColumns, gap } = attributes;

  // Calculate styles using utilities (declarative)
  const innerStyles = calculateInnerStyles(attributes);

  // Get block props
  const blockProps = useBlockProps({ className: 'my-block' });

  return (
    <>
      <InspectorControls>
        <LayoutPanel
          layoutType={layoutType}
          gap={gap}
          setAttributes={setAttributes}
        />
        <GridPanel
          gridColumns={gridColumns}
          setAttributes={setAttributes}
        />
      </InspectorControls>

      <div {...blockProps}>
        <div style={innerStyles}>
          {/* Block content */}
        </div>
      </div>
    </>
  );
}
```

### 5. Update index.js to Registration-Only
Simplify index.js to just register the block.

**Registration Pattern:**
```javascript
// index.js (40-60 lines)
import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';

import './editor.scss';
import './style.scss';

/**
 * Register Block Name Block
 */
registerBlockType(metadata.name, {
  ...metadata,
  icon: {
    src: (/* SVG */),
    foreground: '#2563eb',
  },
  edit,
  save,
});
```

### 6. Build and Test
```bash
# Build
npx wp-scripts build

# Check bundle sizes
ls -lh build/index.js build/style-index.css

# Test in wp-env
npx wp-env start
```

### 7. Verify No Breaking Changes
- [ ] Block loads without errors
- [ ] All controls work in editor
- [ ] Styles apply correctly
- [ ] Frontend matches editor
- [ ] Existing blocks don't show validation errors
- [ ] Bundle size increase is acceptable (<5%)

## Refactoring Success Metrics

**Before Starting:**
- File size >300 lines
- Mixed concerns (registration + edit + panels + utilities)
- Hard to test
- Hard to navigate

**After Refactoring:**
- Main file: 40-60 lines (registration only)
- Edit file: 100-150 lines (focused component)
- Each panel: 60-150 lines (single responsibility)
- Pure utilities: 60-120 lines (100% testable)
- Total files: 6-10 focused files
- **Code health improvement: +5-10 points**

## Real-World Examples from This Project

### Container Block Refactoring
- **Before**: 658 lines (monolithic)
- **After**: 349 lines edit.js + 7 panels + 1 utility
- **Reduction**: -47% in main file
- **Files created**: 9 focused files
- **Time**: 2 hours
- **ROI**: 76 hours/year saved

### Counter Block Refactoring
- **Before**: 357 lines (monolithic)
- **After**: 54 lines index.js + 154 lines edit.js + 4 panels + 2 utilities
- **Reduction**: -85% in main file
- **Files created**: 8 focused files
- **Time**: 1.5 hours
- **ROI**: 33 hours/year saved

### Icon Block Refactoring
- **Before**: 350 lines (monolithic)
- **After**: 41 lines index.js + 102 lines edit.js + 3 panels + 2 utilities
- **Reduction**: -88% in main file
- **Files created**: 7 focused files
- **Time**: 1.5 hours
- **ROI**: 31 hours/year saved

**Total ROI (All 3 Blocks)**: 140+ hours/year saved from 6.5 hours invested = **2,054% ROI**

## When to Refactor

**Triggers:**
1. File exceeds 300 lines
2. Adding a feature requires scrolling through >200 lines
3. Testing requires mocking WordPress extensively
4. Code reviews take >20 minutes
5. Onboarding new developers is difficult

**Don't Refactor When:**
1. File is <250 lines and well-organized
2. File is pure data (e.g., icon library, constants)
3. About to make major architectural changes
4. No tests exist (write tests first, then refactor)

## Naming Conventions

**Files:**
- `{BlockName}Edit.js` or just `edit.js` (we prefer `edit.js`)
- `{Feature}Panel.js` (e.g., `LayoutPanel.js`, not `layout-panel.js`)
- `{purpose}-{type}.js` (e.g., `style-calculator.js`, `number-formatter.js`)

**Exports:**
- Named exports for panels: `export const LayoutPanel = ...`
- Default export for edit/save: `export default function BlockEdit() {...}`
- Named exports for utilities: `export const calculateStyle = ...`

**Directories:**
- `components/inspector/` (not `components/panels/`)
- `utils/` (not `utilities/` or `helpers/`)

## Testing Extracted Code

**Pure Utilities (Easy):**
```javascript
// utils/number-formatter.test.js
import { formatNumber } from './number-formatter';

describe('formatNumber', () => {
  it('formats with thousands separator', () => {
    expect(formatNumber(1000)).toBe('1,000');
  });

  it('formats with decimals', () => {
    expect(formatNumber(1234.56, { decimals: 2 })).toBe('1,234.56');
  });
});
```

**Inspector Panels (Medium):**
```javascript
// components/inspector/LayoutPanel.test.js
import { render, screen } from '@testing-library/react';
import { LayoutPanel } from './LayoutPanel';

describe('LayoutPanel', () => {
  it('renders layout controls', () => {
    render(<LayoutPanel layoutType="grid" gap="24px" setAttributes={jest.fn()} />);
    expect(screen.getByText('Layout')).toBeInTheDocument();
  });
});
```

## Performance Considerations

**Bundle Size:**
- Refactoring typically adds 2-5 KB to editor bundle
- This is acceptable due to webpack tree-shaking
- Frontend bundle should not increase
- Monitor with `ls -lh build/`

**Build Time:**
- More files = slightly longer builds (~5-10%)
- Offset by better developer experience
- Use `--webpack-bundle-analyzer` to check

**Runtime Performance:**
- No impact - same code, just organized better
- Pure functions may improve performance (memoization opportunities)

## Documentation Requirements

**Every refactored block must have:**

1. **JSDoc on all exports:**
```javascript
/**
 * Layout Panel - Controls for layout type and gap
 *
 * @param {Object} props - Component props
 * @param {string} props.layoutType - Current layout type
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Layout Panel component
 */
```

2. **File header comments:**
```javascript
/**
 * Container Block - Layout Panel Component
 *
 * Provides controls for layout type selection and gap settings.
 *
 * @since 1.0.0
 */
```

3. **Inline comments for complex logic:**
```javascript
// Calculate responsive columns, ensuring Desktop >= Tablet >= Mobile
const effectiveTabletCols = Math.min(tabletColumns, desktopColumns);
```

## Refactoring Checklist

Use this checklist for every refactoring:

**Planning:**
- [ ] File exceeds 300 lines
- [ ] Identified pure utilities to extract
- [ ] Identified panels to extract (one per PanelBody)
- [ ] Estimated time (1.5-2 hours per block)

**Backup:**
- [ ] Created `.backup` file of original

**Extraction:**
- [ ] Created `utils/` directory
- [ ] Extracted pure functions with JSDoc
- [ ] Created `components/inspector/` directory
- [ ] Extracted panels with JSDoc
- [ ] Created focused `edit.js`
- [ ] Updated `index.js` to registration-only

**Testing:**
- [ ] Build succeeds
- [ ] No console errors
- [ ] Block works in editor
- [ ] Block works on frontend
- [ ] No validation errors
- [ ] Bundle size acceptable

**Documentation:**
- [ ] JSDoc on all exports
- [ ] File headers added
- [ ] Updated CLAUDE.md if patterns changed

**Commit:**
- [ ] Committed with descriptive message
- [ ] Mentioned reduction percentage
- [ ] Noted any bundle size changes

## Common Refactoring Mistakes

**❌ Don't:**
1. Extract panels but keep them all in index.js
2. Create utilities that import WordPress hooks (not pure)
3. Split files arbitrarily without purpose
4. Refactor without tests
5. Change functionality while refactoring
6. Mix registration and edit logic in index.js
7. Use default exports for panels (use named exports)

**✅ Do:**
1. Extract pure functions first (easiest to test)
2. One panel component per PanelBody
3. Keep edit.js focused (just composition)
4. Maintain exact same functionality
5. Test before and after refactoring
6. Write JSDoc comments as you extract
7. Follow established naming conventions

## Monitoring Large Files

**Priority Order:**
1. **P0 - Critical**: Files >500 lines
2. **P1 - High**: Files >400 lines
3. **P2 - Medium**: Files >300 lines
4. **P3 - Low**: Files >250 lines (optional)

**Monitor:**
```bash
# Find large files
find src/blocks -name "*.js" -exec wc -l {} + | sort -rn | head -10
```
