---
name: plugin-review
description: Comprehensive WordPress plugin audit by experienced developer
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(npm run *), Bash(find *), Bash(grep *), Bash(ls *)
---


Act as a senior WordPress plugin developer with 10+ years of experience building production-ready plugins. Review the DesignSetGo WordPress plugin comprehensively, evaluating code quality, architecture, WordPress best practices, and production readiness.

## Review Scope

This is a **COMPREHENSIVE PLUGIN AUDIT** covering all aspects of plugin development. Focus on practical, actionable feedback that improves code quality, maintainability, and user experience.

## Core Review Areas

### 1. **Plugin Architecture & Code Organization** (Critical)

**Structure Assessment:**
- [ ] File/folder organization follows WordPress standards
- [ ] Proper autoloading or class loading mechanism
- [ ] Separation of concerns (admin, public, blocks, extensions)
- [ ] Singleton pattern correctly implemented (if used)
- [ ] No circular dependencies
- [ ] Clear entry points (main plugin file, class-plugin.php)

**Code Quality:**
- [ ] Files under 300 lines (per REFACTORING-GUIDE.md)
- [ ] Functions/methods have single responsibility
- [ ] No code duplication (DRY principle)
- [ ] Consistent naming conventions
- [ ] No unused/commented-out code
- [ ] Proper error handling throughout

**Check these files:**
- `designsetgo.php` - Main plugin file
- `includes/class-plugin.php` - Core plugin class
- `includes/blocks/class-loader.php` - Block registration
- All block `index.js` files for registration patterns

### 2. **WordPress Block Development Best Practices** (Critical)

**Block Registration:**
- [ ] All blocks use `block.json` (no JavaScript-only registration)
- [ ] Proper `apiVersion` set (should be 3 for modern blocks)
- [ ] Complete `supports` configuration for FSE compatibility
- [ ] `example` property for pattern previews
- [ ] Correct `textdomain` on all blocks
- [ ] No hardcoded strings (all use `__()` or `_x()`)

**Block Implementation:**
- [ ] Using `useBlockProps()` in edit functions
- [ ] Using `useInnerBlocksProps()` instead of plain `<InnerBlocks />`
- [ ] Using `useBlockProps.save()` in save functions
- [ ] Declarative styling (no `useEffect` for styles)
- [ ] Editor matches frontend (prevents validation errors)
- [ ] Proper attribute typing and defaults
- [ ] Custom inspector controls use `<DsgoInspectorPanel>` (Settings/Style, in that order), never bare `PanelBody`
- [ ] Horizontal positioning uses the justification pattern, never `supports.align` for left/center/right

**Check these files:**
- All `src/blocks/*/block.json` files
- All `src/blocks/*/index.js` files
- All `src/blocks/*/edit.js` files
- All `src/blocks/*/save.js` files

**Reference Documents:**
- `docs/guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md`
- `docs/guides/BEST-PRACTICES-SUMMARY.md`
- `.claude/docs/EDITOR-STYLING-GUIDE.md`

### 3. **Full Site Editing (FSE) Compatibility** (High Priority)

**Block.json Configuration:**
- [ ] Comprehensive `supports` (color, spacing, typography, layout)
- [ ] Uses WordPress presets (no hardcoded colors/spacing)
- [ ] `__experimentalBorder` support where appropriate
- [ ] `interactivity` API support (if using block bindings)
- [ ] Proper `providesContext` and `usesContext` for parent-child blocks

**Theme.json Integration:**
- [ ] Blocks work with Twenty Twenty-Five theme
- [ ] Respects theme color palette
- [ ] Respects theme spacing scale
- [ ] Respects theme typography settings
- [ ] No conflicts with theme layout settings

**Testing:**
- [ ] Test with FSE theme (Twenty Twenty-Five)
- [ ] Test with classic theme (Twenty Twenty-One)
- [ ] Verify blocks appear in pattern library
- [ ] Check block inserter categories

**Reference:** `.claude/docs/FSE-COMPATIBILITY-GUIDE.md`

### 4. **Accessibility (WCAG 2.1 AA Compliance)** (Critical)

**Semantic HTML:**
- [ ] Proper heading hierarchy (h1-h6)
- [ ] Semantic landmarks (nav, main, aside, footer)
- [ ] Lists use `<ul>`/`<ol>` appropriately
- [ ] Buttons are `<button>` not `<div onclick>`
- [ ] Links use `<a href>` with proper URLs

**Keyboard Navigation:**
- [ ] All interactive elements keyboard accessible
- [ ] Proper tab order (no `tabindex > 0`)
- [ ] Focus indicators visible
- [ ] Skip links where appropriate
- [ ] No keyboard traps

**Screen Reader Support:**
- [ ] Images have `alt` text
- [ ] Icons have `aria-label` or `aria-hidden="true"`
- [ ] Form inputs have labels
- [ ] ARIA attributes used correctly (not overdone)
- [ ] `visually-hidden` class for screen-reader-only text

**Color & Contrast:**
- [ ] Text contrast meets WCAG AA (4.5:1 for normal, 3:1 for large)
- [ ] Interactive elements have 3:1 contrast
- [ ] No color-only information (use icons/text too)
- [ ] Works with Windows High Contrast mode

**Check all interactive blocks:**
- Icon Button
- Tabs
- Accordion
- Form fields
- Links in blocks

### 5. **Internationalization (i18n) & Localization** (High Priority)

**Translation Functions:**
- [ ] All user-facing strings use `__()`, `_e()`, `_n()`, `_x()`
- [ ] Correct text domain ('designsetgo') everywhere
- [ ] No string concatenation (use sprintf/printf)
- [ ] Translator comments for context
- [ ] JavaScript strings use `wp.i18n.__()` or `@wordpress/i18n`

**Implementation:**
- [ ] `load_plugin_textdomain()` called correctly
- [ ] `.pot` file generation working
- [ ] RTL stylesheet exists (style-rtl.css)
- [ ] Numbers, dates use WordPress formatting functions
- [ ] No hardcoded currencies or measurements

**Check:**
```bash
# Search for hardcoded English strings
grep -r "console.log\|alert\|'[A-Z][a-z]" src/ --include="*.js" | grep -v "__("

# Verify text domains
grep -r "__(" includes/ src/ | grep -v "designsetgo"
```

### 6. **Performance & Optimization** (High Priority)

**Asset Loading:**
- [ ] Conditional asset loading (only when blocks used)
- [ ] Dependencies declared correctly
- [ ] No duplicate script/style loading
- [ ] Assets load from `build/` not `src/`
- [ ] Minification enabled in production
- [ ] Source maps disabled in production

**Bundle Sizes:**
- [ ] Individual block JS < 10KB gzipped
- [ ] Individual block CSS < 5KB gzipped
- [ ] Total plugin assets < 100KB gzipped
- [ ] Code splitting where appropriate
- [ ] Tree shaking working correctly

**Frontend Performance:**
- [ ] No unnecessary JavaScript on frontend
- [ ] Event listeners cleaned up
- [ ] No memory leaks (setInterval cleared)
- [ ] Images optimized and lazy-loaded
- [ ] Videos use `loading="lazy"`

**React Performance:**
- [ ] No `useEffect` for simple calculations
- [ ] Memoization where appropriate (`useMemo`, `useCallback`)
- [ ] No unnecessary re-renders
- [ ] Large lists use windowing (if applicable)

**Database:**
- [ ] No queries on every page load
- [ ] Transients used for caching
- [ ] Options not autoloaded unless necessary
- [ ] Cleanup on plugin uninstall

**Check:**
```bash
# Bundle sizes
ls -lh build/ | grep -E "\.js$|\.css$"

# Frontend JavaScript
grep -r "view\.js\|frontend\.js" src/
```

### 7. **User Experience (UX) & Design** (Medium Priority)

**Block Inspector (Sidebar) Controls:**
- [ ] Logical grouping in panels
- [ ] Clear, descriptive labels
- [ ] Help text for complex options
- [ ] No overwhelming number of options
- [ ] Settings organized by frequency of use
- [ ] Follows WordPress design patterns

**Block Toolbar:**
- [ ] Essential controls in toolbar
- [ ] Icons clear and recognizable
- [ ] Tooltips on all toolbar buttons
- [ ] No toolbar clutter

**Block Variations:**
- [ ] Meaningful, distinct variations
- [ ] Clear preview icons
- [ ] Descriptive titles
- [ ] Not too many variations (< 5 recommended)

**Responsive Design:**
- [ ] Blocks work on mobile devices
- [ ] Controls accessible on small screens
- [ ] Preview matches actual rendering
- [ ] No horizontal scrolling

**Error Handling:**
- [ ] Graceful degradation if settings invalid
- [ ] User-friendly error messages
- [ ] No white screens of death
- [ ] Console errors are actionable

### 8. **Documentation Quality** (Medium Priority)

**Code Documentation:**
- [ ] PHPDoc blocks on all classes/methods
- [ ] JSDoc comments on complex functions
- [ ] Inline comments explain "why" not "what"
- [ ] README.md is comprehensive
- [ ] CHANGELOG.md tracks versions

**User Documentation:**
- [ ] Block descriptions in block.json
- [ ] Example patterns provided
- [ ] Common use cases documented
- [ ] Troubleshooting guide

**Developer Documentation:**
- [ ] Hooks/filters documented
- [ ] Extension points clear
- [ ] Build process documented
- [ ] Contributing guidelines

**Check:**
- `README.md`
- `docs/` folder
- PHPDoc coverage in `includes/`

### 9. **Security & Data Validation** (Critical)

**Input Validation:**
- [ ] All user input validated
- [ ] Type checking on attributes
- [ ] Range validation on numbers
- [ ] URL validation (no `javascript:`, `data:`)
- [ ] Color validation (hex, rgb, rgba)

**Output Escaping:**
- [ ] `esc_html()`, `esc_attr()`, `esc_url()` used correctly
- [ ] `wp_kses()` for allowed HTML
- [ ] No `innerHTML` with unsanitized data
- [ ] JSON output uses `wp_json_encode()`

**WordPress Security:**
- [ ] Nonce verification on forms
- [ ] Capability checks on privileged operations
- [ ] ABSPATH checks on direct file access
- [ ] No `eval()` or `create_function()`
- [ ] File uploads properly validated

**Refer to:** `/security-audit` command for deep security review

### 10. **Testing Strategy** (Medium Priority)

**Unit Tests:**
- [ ] PHP unit tests exist (PHPUnit)
- [ ] JavaScript unit tests exist (Jest)
- [ ] Critical functions covered
- [ ] Edge cases tested
- [ ] Mock WordPress functions

**E2E Tests:**
- [ ] Block insertion tested
- [ ] Block settings tested
- [ ] Frontend rendering tested
- [ ] Multi-block interactions tested

**Manual Testing Checklist:**
- [ ] WordPress 6.4+ compatibility
- [ ] PHP 7.4+ compatibility
- [ ] Multisite compatible
- [ ] No conflicts with popular plugins
- [ ] Works with popular themes

**Check:**
- `tests/` folder
- `phpunit.xml` configuration
- `package.json` test scripts

### 11. **Build Process & Development Workflow** (Medium Priority)

**Build Configuration:**
- [ ] Webpack config optimized
- [ ] Babel config correct for target browsers
- [ ] PostCSS/Autoprefixer configured
- [ ] Development vs production builds
- [ ] Hot module replacement working

**Scripts:**
- [ ] `npm run build` - Production build
- [ ] `npm run start` - Development watch
- [ ] `npm run lint:js` - JavaScript linting
- [ ] `npm run lint:css` - CSS linting
- [ ] `npm run lint:php` - PHP linting

**Code Quality Tools:**
- [ ] ESLint configured (@wordpress/eslint-plugin)
- [ ] Stylelint configured
- [ ] PHP_CodeSniffer (WordPress standards)
- [ ] Prettier for formatting
- [ ] Pre-commit hooks

**Check:**
- `webpack.config.js`
- `.eslintrc.js`
- `.stylelintrc.json`
- `phpcs.xml`
- `package.json` scripts

### 12. **WordPress Coding Standards** (Medium Priority)

**PHP Standards:**
- [ ] WordPress PHP Coding Standards (WPCS)
- [ ] Yoda conditions (`if ( 'value' === $var )`)
- [ ] Single quotes for strings (unless interpolation needed)
- [ ] Array syntax: `array()` or `[]` consistently
- [ ] Brace style (K&R variant)
- [ ] Proper indentation (tabs not spaces)

**JavaScript Standards:**
- [ ] WordPress JavaScript Coding Standards
- [ ] ESLint passing with @wordpress/eslint-plugin
- [ ] No `var` (use `const`/`let`)
- [ ] Arrow functions where appropriate
- [ ] Template literals for string interpolation
- [ ] Destructuring for props/attributes

**CSS/SCSS Standards:**
- [ ] BEM methodology or similar
- [ ] Consistent naming conventions
- [ ] No `!important` (except accessibility overrides)
- [ ] Selectors not too specific
- [ ] Mobile-first media queries

**Run checks:**
```bash
npm run lint:js
npm run lint:css
npm run lint:php   # phpcs only
composer analyse   # PHPStan — separate script, also run in CI
```

### 13. **WordPress.org Plugin Directory Compliance** (If Publishing)

**Requirements:**
- [ ] GPL-compatible license
- [ ] No "phone home" functionality
- [ ] No external service dependencies (or disclosed)
- [ ] No hardcoded API keys/credentials
- [ ] Proper versioning (semver)
- [ ] Unique plugin slug

**readme.txt:**
- [ ] Valid readme.txt format
- [ ] Tested up to: current WordPress version
- [ ] Requires at least: minimum version
- [ ] Stable tag matches main plugin version
- [ ] Screenshots and descriptions
- [ ] FAQ section
- [ ] Changelog

**Assets:**
- [ ] Plugin icon (256x256, 128x128)
- [ ] Plugin banner (1544x500)
- [ ] Screenshots with captions
- [ ] All assets GPL-compatible

### 14. **Extensibility & Hooks** (Low Priority)

**Custom Hooks:**
- [ ] Filters for modifying output
- [ ] Actions for extending functionality
- [ ] Documented hook parameters
- [ ] Backwards compatibility considered
- [ ] Prefixed with plugin name

**Examples:**
```php
// Good extensibility
$output = apply_filters( 'designsetgo_block_output', $output, $attributes, $block );
do_action( 'designsetgo_before_block_render', $block_name, $attributes );
```

**JavaScript Extensibility:**
- [ ] Filters for block attributes
- [ ] Filters for block settings
- [ ] Published functions available via `window.DesignSetGo`

### 15. **Upgrade & Backwards Compatibility** (Low Priority)

**Version Management:**
- [ ] Database version stored
- [ ] Upgrade routine for major changes
- [ ] Deprecated functions marked
- [ ] Block deprecations for schema changes
- [ ] Migration path from old versions

**Deprecation Strategy:**
- [ ] Deprecated code remains one major version
- [ ] Clear migration instructions
- [ ] Warnings in development mode
- [ ] No breaking changes in minor versions

## Output Requirements

Generate a comprehensive **PLUGIN-REVIEW.md** file with the following structure:

### Document Format

```markdown
# DesignSetGo WordPress Plugin - Comprehensive Developer Audit

**Review Date:** YYYY-MM-DD
**Plugin Version:** X.X.X
**WordPress Version Tested:** X.X
**Reviewer Role:** Senior WordPress Plugin Developer


## Executive Summary

### Overall Assessment
[Letter grade: A+, A, B+, B, C+, C, D, F]

### Production Readiness
[Ready for Production | Needs Minor Fixes | Needs Major Fixes | Not Ready]

### Key Strengths (Top 3)
1. [What you're doing exceptionally well]
2. [What you're doing exceptionally well]
3. [What you're doing exceptionally well]

### Critical Issues (Must Fix Before Production)
[Number and brief description]

### Statistics
- Total Files Reviewed: XX
- Critical Issues: XX
- High Priority: XX
- Medium Priority: XX
- Low Priority: XX
- Suggestions: XX


## 🔴 CRITICAL ISSUES (Must Fix Before Production)

### 1. [Issue Title]

**File:** `path/to/file.php:123`

**Issue:**
[Clear description of the problem]

**Why This Matters:**
[Impact on users, security, stability]

**Current Code:**
```php
// Problematic code
```

**Fixed Code:**
```php
// Corrected code with explanation
```

**Effort:** [15 minutes | 1 hour | 4 hours | 1 day]


## 🟡 HIGH PRIORITY ISSUES (Fix Before 1.0)

[Same format as critical issues]


## 🟢 MEDIUM PRIORITY (Quality Improvements)

[Grouped by category: Performance, UX, Documentation, etc.]


## 🔵 LOW PRIORITY (Nice to Have)

[Code quality, refactoring suggestions, future enhancements]


## 📊 Code Quality Metrics

### File Size Analysis
```
Files over 300 lines (should refactor):
- src/blocks/example/index.js (450 lines)
```

### Bundle Size Analysis
```
Block Name          | JS Size | CSS Size | Total
--------------------|---------|----------|-------
flex                | 8.2 KB  | 2.1 KB   | 10.3 KB ✅
grid                | 12.5 KB | 3.2 KB   | 15.7 KB ⚠️
```

### Test Coverage
```
PHP: XX% coverage
JavaScript: XX% coverage
```


## ✅ WHAT YOU'RE DOING WELL

### Architecture
- [Specific things done right]

### WordPress Best Practices
- [Specific things done right]

### Code Quality
- [Specific things done right]

### User Experience
- [Specific things done right]


## 🎯 RECOMMENDED PRIORITIES

### Week 1: Critical Fixes
- [ ] [Issue #1] - File.php:123
- [ ] [Issue #2] - File.js:456

### Week 2: High Priority
- [ ] [Issue #3]
- [ ] [Issue #4]

### Week 3: Quality Improvements
- [ ] [Issue #5]
- [ ] [Issue #6]

### Ongoing: Maintenance
- [ ] Documentation updates
- [ ] Test coverage
- [ ] Performance monitoring


## 📚 BEST PRACTICES REFERENCE

### Resources to Review
- [Link to internal docs that apply]
- [WordPress documentation references]
- [Industry best practices]

### Suggested Reading
- [Specific documentation based on issues found]


## 🏁 PRODUCTION READINESS CHECKLIST

**Before deploying to production, ensure:**

- [ ] All critical issues resolved
- [ ] Security audit passed (run `/security-audit`)
- [ ] Accessibility tested (WCAG 2.1 AA)
- [ ] Performance benchmarked
- [ ] Cross-browser tested (Chrome, Firefox, Safari, Edge)
- [ ] Mobile responsive tested
- [ ] WordPress 6.4+ tested
- [ ] No console errors
- [ ] Translation ready
- [ ] Documentation complete


## 🔄 NEXT STEPS

1. **Immediate Actions:**
   - [Specific next steps]

2. **Schedule Review:**
   - Re-audit after fixing critical issues
   - Plan for quarterly reviews

3. **Continuous Improvement:**
   - Set up automated linting
   - Add pre-commit hooks
   - Establish code review process


**End of Review**
```

## Execution Strategy

### 1. **Initial Assessment (10 minutes)**
- Read main plugin file
- Review folder structure
- Check build process
- Identify plugin scope

### 2. **Code Review (60-90 minutes)**
- Review all PHP files in `includes/`
- Review all block `index.js`, `edit.js`, `save.js` files
- Review extensions
- Check build configuration

### 3. **Security Review (30-45 minutes)**
- **Run `/security-audit` command** for comprehensive security scan
- Review security audit findings
- Prioritize critical security issues
- Note high-priority vulnerabilities for immediate attention

### 4. **Standards Compliance (30 minutes)**
- Run linting tools
- Check coding standards
- Review accessibility
- Verify i18n

### 5. **Best Practices Check (30 minutes)**
- Compare against internal docs
- Check FSE compatibility
- Review block.json files
- Test responsive design

### 6. **Documentation Review (15 minutes)**
- README quality
- Code comments
- User documentation
- Developer guides

### 7. **Generate Report (30 minutes)**
- Compile findings (including security audit results)
- Prioritize issues (security first, then critical, high, medium, low)
- Write fixes
- Create action plan

## Analysis Commands

```bash
# Find all blocks
find src/blocks -name "block.json"

# Check for hardcoded strings
grep -r "__(" src/ includes/ | grep -v "designsetgo"

# Find large files
find src/ includes/ -name "*.js" -o -name "*.php" | xargs wc -l | sort -rn | head -20

# Bundle sizes
ls -lh build/ | grep -E "\.js$|\.css$"

# Tests (there is no test:coverage or test:a11y script — these don't exist)
npm run test:unit
npm run test:php
npm run test:e2e

# Linting
npm run lint:js
npm run lint:css
npm run lint:php   # phpcs only
composer analyse   # PHPStan
```

## Important Reminders

- **Be constructive, not critical** - Frame feedback positively
- **Provide complete fixes** - Not just "fix this", but HOW to fix it
- **Reference internal docs** - Point to .claude/CLAUDE.md and docs/
- **Prioritize ruthlessly** - Not everything needs to be fixed now
- **Celebrate good work** - Acknowledge what's done well
- **Give time estimates** - Help with planning
- **Think production** - Would you deploy this to 100,000 sites?

## Success Criteria

A successful review should:
- ✅ Identify ALL critical issues that prevent production deployment
- ✅ Provide actionable, copy-paste fixes for every issue
- ✅ Explain WHY each issue matters (not just what)
- ✅ Prioritize issues realistically
- ✅ Give specific time estimates
- ✅ Reference relevant documentation
- ✅ End with clear next steps
- ✅ Balance criticism with recognition of good work

**DELIVER VALUE:** The review should make the developer better, not just point out problems.
