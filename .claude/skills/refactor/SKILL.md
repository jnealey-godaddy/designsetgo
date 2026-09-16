---
name: refactor
description: Use when cleaning up an existing DesignSetGo block or extension - removing anti-patterns (useEffect styling, plain InnerBlocks, hardcoded values, dead code) and bringing it in line with .claude/claude.md conventions
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(git *), Bash(npm run *)
---


## Refactoring Process

### 1. Analyze Current Code

**Read the target files and identify:**

**Anti-Patterns to Remove:**
- [ ] `useEffect()` for styling
- [ ] `querySelector()` or direct DOM access
- [ ] Plain `<InnerBlocks />` without `useInnerBlocksProps`
- [ ] Hardcoded spacing values (use theme tokens)
- [ ] Missing FSE supports
- [ ] No block example
- [ ] Unescaped PHP output
- [ ] Non-internationalized strings
- [ ] Bare `PanelBody` for custom inspector controls (use `<DsgoInspectorPanel>` — see Theme 3 IA in `.claude/claude.md`)
- [ ] `supports.align: ["left","center","right"]` used for horizontal positioning (use the justification pattern instead — see "Horizontal positioning" in `.claude/claude.md`)

**Code Smells:**
- [ ] Versioned files (file-v2.js, file-old.scss)
- [ ] Unused imports
- [ ] Commented-out code blocks
- [ ] console.log statements
- [ ] Duplicate WordPress functionality
- [ ] Complex features that don't work reliably

**Check Documentation:**
- Read `.claude/CLAUDE.md` for project-specific patterns
- Reference `docs/guides/BEST-PRACTICES-SUMMARY.md` for quick patterns
- Reference `docs/guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md` for deep dives

### 2. Plan Refactor

**Create todo list with specific tasks:**
- List files that need changes
- Note breaking changes (attribute migrations needed?)
- Identify opportunities to simplify
- Plan deprecation strategy if needed
- Estimate bundle size impact

### 3. Execute Refactor

**Make changes incrementally:**

**For Blocks with InnerBlocks:**
1. Replace `<InnerBlocks />` with `useInnerBlocksProps` pattern
2. Move styles from `useEffect` to declarative style objects
3. Remove DOM queries and manipulation
4. Update save.js to match edit.js exactly
5. Provide deprecation for old save format

**For All Blocks:**
1. Add/update FSE supports in block.json
2. Add block example for pattern library
3. Replace hardcoded values with theme tokens
4. Add ARIA labels to custom controls
5. Escape all PHP output
6. Internationalize all strings
7. Remove unused imports and dead code

**For Extensions:**
1. Verify they're truly simple (≤3 controls)
2. If complex, consider converting to custom block
3. Ensure they work WITH WordPress, not against it

**Update Related Files:**
- Import new styles in `src/style.scss` (frontend — NOT `src/styles/style.scss`, which is dead code nothing imports)
- Import new styles in `src/styles/editor.scss` (editor)
- Update PHP includes if needed
- Update frontend.js/view.js (remove layout code if moving to declarative styles)

### 4. Verify Build

```bash
# Build
npx wp-scripts build

# Check output
ls -lh build/
cat build/style-index.css | grep "your-class-name"

# Verify bundle sizes are within budgets
```

### 5. Test Thoroughly

**Editor Testing:**
- [ ] Block loads without errors
- [ ] Controls work as expected
- [ ] Styles apply immediately (no delay)
- [ ] WordPress native controls still work
- [ ] Block inserter works
- [ ] No console errors

**Frontend Testing:**
- [ ] Frontend matches editor exactly
- [ ] Styles work without JavaScript
- [ ] Responsive behavior correct
- [ ] No flash of unstyled content (FOUC)
- [ ] Interactive elements work (buttons, links)

**FSE Testing:**
- [ ] Block appears in Site Editor
- [ ] Preview shows in pattern library
- [ ] Global styles can be applied
- [ ] Theme spacing tokens work
- [ ] Works with Twenty Twenty-Five theme

**Accessibility Testing:**
- [ ] Keyboard navigation works
- [ ] Screen reader announces correctly
- [ ] Contrast meets WCAG 2.1 AA
- [ ] Focus indicators visible

### 6. Validate Performance

**Check bundle sizes:**
```bash
ls -lh build/*.js build/*.css
```

**If over budget:**
- Code split per block
- Remove unused dependencies
- Tree-shake imports
- Lazy load heavy features


## Common Refactoring Scenarios

### Scenario 1: Block Uses useEffect for Styling

**Symptoms:**
- Styles don't apply in editor or have delays
- Editor doesn't match frontend
- Race conditions, timing issues

**Fix:**
1. Remove `useEffect` hooks
2. Calculate styles declaratively from attributes
3. Use `useInnerBlocksProps` with style object
4. Update save.js to match
5. Test immediately in editor and frontend

### Scenario 2: Plain InnerBlocks Breaking Layouts

**Symptoms:**
- Grid/flexbox layouts don't work
- Content width constraints ignored
- Extra wrapper divs in markup

**Fix:**
```javascript
// ❌ BEFORE
<div style={{ maxWidth: '800px' }}>
  <InnerBlocks />
</div>

// ✅ AFTER
const innerBlocksProps = useInnerBlocksProps({
  style: { maxWidth: '800px' }
});
return <div {...innerBlocksProps} />;
```

### Scenario 3: Complex Feature That Doesn't Work

**Symptoms:**
- CSS variables not applying
- Dynamic styles failing
- User feedback: "This isn't working"

**Fix:**
1. Simplify to fixed, well-designed options
2. Replace color pickers with preset choices
3. Use CSS classes instead of dynamic inline styles
4. User says "Let's just have a toggle" → Do it!

### Scenario 4: Missing FSE Support

**Symptoms:**
- Block doesn't appear in Site Editor
- No preview in pattern library
- Can't apply global styles

**Fix:**
1. Add comprehensive supports to block.json
2. Add example with innerBlocks
3. Use theme spacing/color tokens
4. Test with Twenty Twenty-Five

### Scenario 5: Frontend Styles Missing

**Symptoms:**
- Works in editor
- Broken on frontend

**Fix:**
1. Import styles in `src/style.scss` (not `src/styles/style.scss` — dead code)
2. Rebuild: `npx wp-scripts build`
3. Verify: `grep "your-class" build/style-index.css`

### Scenario 6: Versioned Files and Dead Code

**Symptoms:**
- Multiple versions of same file
- Commented-out code blocks
- Unused imports

**Fix:**
1. Delete all versioned files (Git is version control)
2. Remove commented code
3. Remove unused imports
4. Clean sweep after architectural pivots

### Scenario 7: Accessibility Issues

**Symptoms:**
- Overlay makes text unreadable
- No keyboard navigation
- Poor contrast

**Fix:**
1. Force white text on dark overlays with `!important`
2. Add ARIA labels to all custom controls
3. Test with keyboard only
4. Run axe DevTools

### Scenario 8: Security Vulnerabilities

**Symptoms:**
- Unescaped output in PHP
- No input validation
- XSS vulnerabilities

**Fix:**
1. Escape all PHP output: `esc_html()`, `esc_url()`, `wp_kses_post()`
2. Validate all user input in JavaScript
3. Sanitize before saving attributes

### Scenario 9: Performance Issues

**Symptoms:**
- Large bundle sizes
- Slow editor
- Heavy frontend JS

**Fix:**
1. Remove DOM manipulation (use declarative styles)
2. Remove layout code from frontend.js (use inline styles)
3. Tree-shake imports
4. Code split per block
5. Measure before/after with `ls -lh build/`

### Scenario 10: Poor Code Maintainability

**Symptoms:**
- Hard to understand or modify code
- Duplicate code across files
- Large, monolithic files
- No documentation or comments
- Inconsistent naming
- Hard-coded magic numbers

**Fix:**
1. **Extract Shared Logic:**
   ```javascript
   // ❌ BEFORE - Duplicated in 3 blocks
   const columns = device === 'mobile' ? mobileColumns :
                   device === 'tablet' ? tabletColumns :
                   desktopColumns;

   // ✅ AFTER - Shared utility
   // src/utils/responsive.js
   export const getResponsiveValue = (device, values) => {
     return values[device] || values.desktop;
   };
   ```

2. **Split Large Files:**
   - Extract controls to separate components
   - Move style calculations to utility functions
   - Separate concerns (data, presentation, logic)

3. **Add Documentation:**
   ```javascript
   /**
    * Calculates inner container styles based on layout type.
    * Supports grid, flex, and stack layouts with responsive columns.
    *
    * @param {Object} attributes - Block attributes
    * @param {string} attributes.layoutType - Layout type (grid/flex/stack)
    * @param {number} attributes.columns - Number of columns
    * @return {Object} React style object for inner container
    */
   export const calculateInnerStyles = (attributes) => {
     // Implementation
   };
   ```

4. **Use Named Constants:**
   ```javascript
   // ❌ BEFORE
   if (columns > 6) { /* ... */ }

   // ✅ AFTER
   const MAX_GRID_COLUMNS = 6;
   if (columns > MAX_GRID_COLUMNS) { /* ... */ }
   ```

5. **Consistent Naming:**
   - Attributes: `layoutType`, `gridColumns`, `enableOverlay`
   - CSS classes: `dsgo-container`, `dsgo-container__inner`, `dsgo-container--hero`
   - Functions: `calculateStyles`, `handleLayoutChange`, `getResponsiveColumns`

6. **Add Inline Comments for Complex Logic:**
   ```javascript
   // Calculate aspect ratio for video background to maintain 16:9
   // even when container is resized or on different screen sizes
   const aspectRatio = (height / width) * 100;
   ```


## Migration Checklist (For Breaking Changes)

If refactoring changes save format or attributes, see the full "Deprecations" section of `.claude/claude.md` before writing the entry — it documents three traps that have bitten this codebase before:

1. **Every entry must redeclare `apiVersion`** (stripped otherwise, so `useBlockProps.save()` produces different markup and the entry silently never validates).
2. **The third `migrate`/`isEligible` argument is `{ blockNode, block }`** — there is no `innerHTML` key; use `blockNode.innerHTML` or `block.originalContent`.
3. **`isEligible` cannot rescue an invalid block** and must never be keyed on an attribute's absence (WordPress omits default-valued attributes from the block comment, so that's just as true of new content).

```javascript
// deprecated.js (imported as `deprecated` and passed into registerBlockType)
const v1 = {
	apiVersion: 3, // REQUIRED — omit only if this version really predates apiVersion
	attributes: { /* the FULL attribute schema this version had */ },
	supports: { /* the FULL support set this version had, incl. __experimental* typography keys */ },
	save( { attributes } ) { /* reproduce the old output byte-for-byte */ },
	migrate( attributes ) {
		return { ...attributes, newAttribute: 'default' };
	},
	// isEligible ONLY for a migration on an otherwise-VALID block; key it on
	// markup, never on an attribute being undefined.
};
export default [ v1 ];
```

**Test:**
1. Create block with old version
2. Update plugin
3. Edit block (should auto-migrate, or reproduce the old markup with no "Attempt Recovery")
4. Round-trip `createBlock → serialize → parse` and confirm no attribute is lost (see `tests/unit/deprecations-isEligible.test.js` for the pattern this repo pins)


## After Refactoring Checklist

**Build & Code Quality:**
- [ ] `npx wp-scripts build` completes without errors
- [ ] No Sass deprecation warnings (or noted for future)
- [ ] No console warnings/errors
- [ ] Bundle sizes within budgets
- [ ] No versioned files
- [ ] Imports up to date
- [ ] Comments reflect current implementation

**Functionality:**
- [ ] Works in editor (block editor)
- [ ] Works on frontend (published post)
- [ ] Works in Site Editor (FSE)
- [ ] Responsive behavior intact
- [ ] No duplicate WordPress controls
- [ ] WordPress toolbar still works
- [ ] Interactive elements work (buttons, links)

**WordPress Patterns:**
- [ ] Uses `useBlockProps()` correctly
- [ ] Uses `useInnerBlocksProps()` for nested blocks (not plain `<InnerBlocks />`)
- [ ] No `useEffect()` for styling
- [ ] No DOM manipulation
- [ ] Edit.js matches save.js markup exactly
- [ ] Uses theme spacing/color tokens

**FSE Compatibility:**
- [ ] Block appears in Site Editor inserter
- [ ] Preview shows in pattern library
- [ ] Global styles can be applied
- [ ] Theme.json integration works
- [ ] Example provided in block.json

**Accessibility:**
- [ ] WCAG 2.1 AA contrast (4.5:1)
- [ ] Keyboard navigation works
- [ ] ARIA labels on custom controls
- [ ] Screen reader tested
- [ ] Focus indicators visible
- [ ] Semantic HTML

**Security:**
- [ ] All PHP output escaped
- [ ] All user input validated
- [ ] External links have `noopener noreferrer`
- [ ] No XSS vulnerabilities

**Internationalization:**
- [ ] All user-facing strings use `__()`
- [ ] Text domain is 'designsetgo'
- [ ] Translator comments added for context

**Performance:**
- [ ] Frontend JS < 5 KB (or lazy loaded)
- [ ] CSS < 3 KB per block
- [ ] No unnecessary dependencies
- [ ] Layouts work without JavaScript

**Documentation:**
- [ ] Update `.claude/CLAUDE.md` with new learnings
- [ ] Document breaking changes
- [ ] Update block README if applicable

**Maintainability:**
- [ ] No files > 300 lines
- [ ] No functions > 50 lines
- [ ] No duplicate code across multiple files
- [ ] Named constants instead of magic numbers
- [ ] JSDoc comments on exported functions
- [ ] Consistent naming conventions (BEM for CSS, camelCase for JS)
- [ ] Shared utilities extracted to `src/utils/`
- [ ] Complex logic has explanatory comments
- [ ] No nested ternaries > 2 levels
- [ ] Imports grouped and organized


## Output Format

Provide a comprehensive summary:

### Refactoring Summary

**Files Changed:**
- List all modified files with brief description of changes

**Anti-Patterns Removed:**
- useEffect for styling → Declarative styles
- Plain InnerBlocks → useInnerBlocksProps
- DOM manipulation → React props
- Etc.

**Improvements Made:**
- Added FSE supports
- Improved accessibility (ARIA labels, contrast)
- Reduced bundle size by X KB
- Added internationalization
- Fixed security issues
- Improved maintainability (extracted utilities, added docs, consistent naming)
- Etc.

**Performance Impact:**
- Before: X KB → After: Y KB
- Percentage change: -Z%

**Maintainability Improvements:**
- Extracted shared utilities: List utilities created
- Reduced file sizes: List files that were split or reduced
- Added documentation: JSDoc added to X functions
- Consistent naming: Renamed X attributes/classes/functions
- Removed duplicate code: X instances of duplication eliminated
- Added constants: Replaced X magic numbers with named constants

**Breaking Changes:**
- List any attribute changes
- Migration strategy provided: Yes/No
- Deprecation added: Yes/No

**Testing Results:**
- [ ] Editor: Pass/Fail
- [ ] Frontend: Pass/Fail
- [ ] FSE: Pass/Fail
- [ ] Accessibility: Pass/Fail
- [ ] Performance: Pass/Fail

**Code Quality Metrics:**
- Lines of code: Before X → After Y
- Number of files: Before X → After Y
- Largest file size: Before X lines → After Y lines
- Cyclomatic complexity: Reduced/Same/Increased
- Test coverage: X% (if applicable)
- Linter warnings: Before X → After Y

**Recommendations:**
- Future improvements
- Technical debt to address
- New features to consider
- Remaining maintainability issues to address


## Reference Documentation

**Quick Reference:**
- `docs/guides/BEST-PRACTICES-SUMMARY.md` - Copy-paste ready patterns

**Comprehensive Guide:**
- `docs/guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md` - Deep dives

**Project Learnings:**
- `.claude/CLAUDE.md` - DesignSetGo-specific patterns

**WordPress Docs:**
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [useBlockProps](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops)
- [useInnerBlocksProps](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useinnerblocksprops)


Now, what would you like to refactor?
