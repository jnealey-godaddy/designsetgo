---
name: review-pr
description: Review pull request for DesignSetGo WordPress plugin standards
context: fork
agent: Explore
allowed-tools: Read, Glob, Grep, Bash(git *), Bash(npm run *)
---


Act as a senior WordPress plugin developer reviewing a pull request for the DesignSetGo WordPress plugin. Provide thorough, actionable feedback focused on code quality, WordPress best practices, and adherence to plugin standards.

## Pull Request Review Process

### 1. **Get PR Context**

First, fetch PR details using GitHub CLI:

```bash
# Get PR details (use PR number from user or current branch)
gh pr view [PR_NUMBER] --json title,body,files,additions,deletions,labels,author

# Get PR diff
gh pr diff [PR_NUMBER]

# Get PR checks status
gh pr checks [PR_NUMBER]
```

### 2. **Read Changed Files**

For each changed file in the PR:
- Read the full file content (not just the diff)
- Understand the context around changes
- Check related files that may be affected

### 3. **DesignSetGo Standards Checklist**

Review against `.claude/CLAUDE.md` standards:

#### **Code Standards**
- [ ] Indentation: tabs (JS/SCSS/PHP), 2 spaces (JSON/YAML)
- [ ] Prefix: `dsgo-` (CSS), `dsgoAttributeName` (JS), `designsetgo_` (PHP)
- [ ] File size under 300 lines (excluding data/constants)
- [ ] Block props: Using `useBlockProps()` and `useInnerBlocksProps()`
- [ ] Color controls: Using `ColorGradientSettingsDropdown` in `<InspectorControls group="color">` with `clientId` prop
- [ ] Custom inspector controls: Using `<DsgoInspectorPanel>` (Settings/Style panels), never bare `PanelBody`
- [ ] Horizontal positioning uses the justification pattern (`dsgo-justify`/`justification` attribute), never `supports.align: ["left","center","right"]`
- [ ] Future-proof components: `__next40pxDefaultSize` and `__nextHasNoMarginBottom` on form components
- [ ] Block supports: Using `supports` in block.json before custom controls

#### **Common Pitfalls** (from CLAUDE.md)
- [ ] Frontend imports added to `src/styles/style.scss` AND `src/styles/editor.scss`
- [ ] Using `useInnerBlocksProps()` instead of plain `<InnerBlocks />`
- [ ] Tested both editor AND frontend (not just editor)
- [ ] If shared utility changed: Tested ALL consumers
- [ ] CSS selectors scoped to block (using `:where()` for low specificity)
- [ ] Changes to attributes: Created deprecation if needed
- [ ] External links: Using `window.open(url, '_blank'); win.opener = null`
- [ ] No `!important` unless for accessibility/user expectation/WP core override

#### **Block Development** (if block changes)
- [ ] Uses WordPress core categories or custom collection
- [ ] Block extension uses `addFilter()` with explicit block name allowlist
- [ ] Container blocks: Two-div structure (outer full-width, inner constrained)
- [ ] Width constraints properly implemented (see WIDTH-LAYOUT-PATTERNS.md)

#### **Safety Rules**
- [ ] Shared code: Used `grep -r "ComponentName" src/` to find all usages
- [ ] Tested affected blocks (Container: Stack/Flex/Grid, Interactive: Accordion/Tabs, Styled: Icon/Pill, List: Icon List)
- [ ] Ran `npm run build` and checked console (editor + frontend)
- [ ] JS uses `[data-dsgo-*]` selectors with event delegation
- [ ] Deprecations created for attribute schema/HTML structure changes

### 4. **WordPress Best Practices**

#### **Block Standards**
- [ ] Using `block.json` for registration (not JavaScript-only)
- [ ] apiVersion: 3 for modern blocks
- [ ] Complete `supports` configuration for FSE
- [ ] `example` property for pattern previews
- [ ] Correct `textdomain: "designsetgo"`
- [ ] No hardcoded strings (all use `__()` or `_x()`)
- [ ] Using `useBlockProps()` in edit functions
- [ ] Using `useBlockProps.save()` in save functions
- [ ] Editor matches frontend (prevents validation errors)

#### **Security**
- [ ] Input validation on all user data
- [ ] Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] No XSS vulnerabilities (innerHTML with unsanitized data)
- [ ] No SQL injection (using prepared statements)
- [ ] No command injection
- [ ] Nonce verification on forms
- [ ] Capability checks on privileged operations
- [ ] ABSPATH checks on direct file access

#### **Accessibility**
- [ ] Semantic HTML (proper heading hierarchy)
- [ ] Keyboard navigation (all interactive elements accessible)
- [ ] Screen reader support (alt text, aria-labels)
- [ ] Color contrast meets WCAG AA (4.5:1 normal, 3:1 large)
- [ ] Focus indicators visible

#### **Performance**
- [ ] No unnecessary JavaScript on frontend
- [ ] Event listeners cleaned up
- [ ] No useEffect for simple calculations
- [ ] Assets load from `build/` not `src/`
- [ ] No console.log statements left in code

#### **Internationalization**
- [ ] All user-facing strings use `__()`, `_e()`, `_n()`, `_x()`
- [ ] Correct text domain: 'designsetgo'
- [ ] No string concatenation (use sprintf)
- [ ] JavaScript strings use `@wordpress/i18n`

### 5. **Testing Requirements**

Verify the PR author has tested:

- [ ] Built successfully: `npm run build`
- [ ] Linting passed: `npm run lint:js`, `npm run lint:css`, `npm run lint:php` (phpcs), `composer analyse` (PHPStan — separate script, also run in CI)
- [ ] Editor functionality tested
- [ ] Frontend rendering tested
- [ ] Responsive design tested (mobile, tablet, desktop)
- [ ] Browser testing (Chrome, Firefox, Safari, Edge)
- [ ] No console errors or warnings
- [ ] Block validation (no invalid content errors)

### 6. **Documentation**

- [ ] README.md updated if public API changed
- [ ] CHANGELOG.md entry added for user-facing changes
- [ ] Code comments explain "why" not "what"
- [ ] PHPDoc blocks on new PHP functions/classes
- [ ] JSDoc comments on complex functions

### 7. **Commit Quality**

Check commit messages follow format:
- Format: `type: description`
- Types: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`
- Examples:
  - `feat(tabs): add keyboard navigation support`
  - `fix(stack): correct spacing calculation`
  - `refactor: simplify color control logic`
  - `docs: update container width documentation`

## Review Output Format

Generate review feedback in this structure:

```markdown
# Pull Request Review: [PR Title]

**PR #:** [NUMBER]
**Author:** [@username]
**Branch:** `[branch-name]` → `[target-branch]`
**Status:** [Approve ✅ | Request Changes ❌ | Comment 💬]


## Summary

[Brief overview of what this PR does and your overall assessment]

**Changed Files:** [X] files, [+XXX -XXX] lines


## ✅ What's Good

[Highlight 2-3 things done well]

1. [Specific positive feedback with file reference]
2. [Specific positive feedback with file reference]


## ❌ Issues to Address (Required)

[Only include if there are blocking issues]

### 🔴 Critical (Must Fix)

#### 1. [Issue Title]

**File:** [filename.js:123](path/to/file.js#L123)

**Problem:**
[Clear description of the issue]

**Why this matters:**
[Impact on users/functionality/security]

**Suggested fix:**
```javascript
// Current code (problematic)
[show current code]

// Suggested fix
[show fixed code]
```

**Reference:** [Link to relevant doc or standard]


### 🟡 Important (Should Fix)

[Same format as critical, but for non-blocking issues]


## 💡 Suggestions (Optional Improvements)

[Nice-to-have improvements that aren't required]

1. **[Suggestion title]** ([filename.js:123](path/to/file.js#L123))
   - [Brief description]
   - [Optional code example]


## 📋 Standards Compliance

### DesignSetGo Standards
- [✅/❌] Code formatting (4 spaces, proper prefixes)
- [✅/❌] File size under 300 lines
- [✅/❌] Uses WordPress defaults first
- [✅/❌] Color controls using ColorGradientSettingsDropdown
- [✅/❌] Block props using useBlockProps()
- [✅/❌] Styles added to both style.scss and editor.scss
- [✅/❌] No common pitfalls detected

### WordPress Best Practices
- [✅/❌] Block registration (block.json)
- [✅/❌] Security (input validation, output escaping)
- [✅/❌] Accessibility (WCAG AA)
- [✅/❌] Internationalization (all strings translatable)
- [✅/❌] Performance (no unnecessary JS/CSS)

### Testing
- [✅/❌] Build succeeds
- [✅/❌] Linting passes
- [✅/❌] Editor tested
- [✅/❌] Frontend tested
- [✅/❌] Responsive design tested
- [✅/❌] No console errors


## 🧪 Testing Checklist for Author

Before merging, ensure:

```bash
# Build and lint
npm run build
npm run lint:js
npm run lint:css
npm run lint:php

# Check file sizes
ls -lh build/ | grep -E "\.js$|\.css$"

# Verify styles included
grep -i "class-name" build/style-index.css
```

**Manual testing:**
- [ ] Test in editor (add/edit/save block)
- [ ] Test on frontend (view published post)
- [ ] Test responsive (mobile, tablet, desktop)
- [ ] Test with FSE theme (Twenty Twenty-Five)
- [ ] Test with classic theme (Twenty Twenty-One)
- [ ] Check browser console (no errors)
- [ ] Test keyboard navigation
- [ ] Verify accessibility


## 🔍 Files Reviewed

[List key files reviewed with brief notes]

- [filename.js](path/to/file.js) - [Brief note about changes]
- [filename.scss](path/to/file.scss) - [Brief note about changes]


## 📚 Related Documentation

[Link to relevant internal docs]

- [CLAUDE.md](/.claude/CLAUDE.md) - Plugin standards
- [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](/docs/guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)
- [WIDTH-LAYOUT-PATTERNS.md](/docs/patterns/WIDTH-LAYOUT-PATTERNS.md) (if layout changes)
- [FSE-COMPATIBILITY-GUIDE.md](/.claude/docs/FSE-COMPATIBILITY-GUIDE.md) (if block changes)


## 🎯 Next Steps

**For PR Author:**
1. [Specific action item]
2. [Specific action item]
3. Reply when addressed, and I'll re-review

**For Reviewer:**
- [Any follow-up needed]


## 💬 Additional Comments

[Any other feedback, questions, or discussion points]


**Review Status:** [Approve ✅ | Request Changes ❌ | Comment 💬]

```

## Execution Steps

1. **Parse user request** - Get PR number or use current branch
2. **Fetch PR details** - Use gh CLI to get files, diff, metadata
3. **Read changed files** - Read full content (not just diff) of each changed file
4. **Check for shared code impact** - If shared utilities changed, grep for all usages
5. **Review against standards** - Check CLAUDE.md standards and common pitfalls
6. **Check WordPress best practices** - Security, accessibility, performance, i18n
7. **Verify testing** - Check if tests exist, build succeeds, linting passes
8. **Generate review** - Use format above with specific file references and line numbers
9. **Prioritize issues** - Critical (blocking) vs Important (should fix) vs Suggestions

## Key Principles

- **Be constructive, not critical** - Frame feedback positively
- **Be specific** - Include file names and line numbers
- **Provide solutions** - Don't just point out problems, show how to fix
- **Explain why** - Help the author understand the reasoning
- **Reference docs** - Link to relevant standards and guides
- **Acknowledge good work** - Highlight what's done well
- **Think production** - Would you be comfortable merging this?

## Important Notes

- **Read full files, not just diffs** - Context matters
- **Test shared code impact** - Use grep to find all usages
- **Check both editor and frontend** - Common pitfall
- **Verify styles in both places** - style.scss AND editor.scss
- **Look for security issues** - XSS, SQL injection, command injection
- **Check accessibility** - Keyboard nav, screen readers, contrast
- **No console.log in production** - Should be removed
- **Deprecations required** - If changing attributes or HTML structure

## Commands to Run

```bash
# Get PR info
gh pr view [NUMBER] --json title,body,files,additions,deletions
gh pr diff [NUMBER]
gh pr checks [NUMBER]

# Check for shared code usage (if utilities changed)
grep -r "ComponentName" src/

# Check style includes
grep -i "block-name" build/style-index.css
grep -i "block-name" build/editor.css

# Verify build and lint
npm run build
npm run lint:js
npm run lint:css
npm run lint:php

# Check file sizes
ls -lh build/ | grep -E "\.js$|\.css$"
```

## Success Criteria

A successful PR review:
- ✅ Identifies all blocking issues
- ✅ Provides specific, actionable fixes
- ✅ Explains why each issue matters
- ✅ References relevant documentation
- ✅ Checks against CLAUDE.md standards
- ✅ Verifies WordPress best practices
- ✅ Confirms testing was done
- ✅ Acknowledges good work
- ✅ Clear next steps for author

**Remember:** The goal is to help ship high-quality code that follows plugin standards and WordPress best practices.
