# DesignSetGo Documentation

Complete reference documentation for developing blocks in the DesignSetGo WordPress plugin.

> **Note on this index:** Several top-level folders under `docs/` (`blocks/`, `extensions/`, `api/`, `patterns/`, `planning/`, `plans/`, `audits/`, `reviews/`) are maintained separately and change frequently. This index links to those **folders**, not individual files inside them — open the folder for the current file list rather than trusting a hardcoded list here.

## 🚀 Quick Start

### For New Contributors

**Never contributed before?** Start with these guides in order:

1. **[GETTING-STARTED.md](./GETTING-STARTED.md)** ⭐ **Start here!**
   - Complete setup walkthrough
   - Prerequisites and installation
   - Making your first change
   - Common workflows

2. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Understand the codebase
   - Project structure
   - How blocks work
   - Build system
   - Data flow

3. **[../CONTRIBUTING.md](../CONTRIBUTING.md)** - Contribution workflow
   - Code standards
   - Testing requirements
   - Pull request process

### For Experienced WordPress Developers

1. Read [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md) for quick patterns
2. Review [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md) for deep understanding
3. Check [BLOCK-CONTROLS-ORGANIZATION.md](./guides/BLOCK-CONTROLS-ORGANIZATION.md) for inspector-panel conventions when creating new blocks

> Both of the guides above predate the Theme 3 Inspector IA rollout and still show raw `PanelBody` / `PanelColorSettings` examples. The concepts (Settings vs. Style categorization, decision trees) are still valid; for the current implementation pattern use `<DsgoInspectorPanel>` (see `../.claude/CLAUDE.md`) instead of a bare `PanelBody`.

### For AI-Assisted Development

**This plugin was built with heavy AI assistance.**

1. **[AI-ASSISTED-DEVELOPMENT.md](./guides/AI-ASSISTED-DEVELOPMENT.md)** ⭐ **Complete AI development guide!**
   - How this plugin is built with Claude Code
   - Available slash commands and skills
   - Best practices for AI-assisted development
   - Common workflows and examples

2. **[../.claude/CLAUDE.md](../.claude/CLAUDE.md)** - Development patterns and context
   - Critical patterns AI follows
   - WordPress best practices
   - Project-specific conventions

3. **[BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md)** - Quick reference
4. **[BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js)** - Block template to copy

## 📁 Folder Organization

### Core Documentation (Root Level)

- **[README.md](./README.md)** - This file, your navigation hub
- **[GETTING-STARTED.md](./GETTING-STARTED.md)** - Complete setup and onboarding guide
- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Deep dive into project architecture

### Maintained elsewhere — see the folder, not this index

- **[api/](./api/)** — API references (Abilities API, Block Bindings, REST API, WP-CLI, etc.)
- **[blocks/](./blocks/)** — Per-block user-facing documentation
- **[extensions/](./extensions/)** — Per-extension documentation
- **[patterns/](./patterns/)** — Design/architecture pattern write-ups
- **[planning/](./planning/)** — Roadmaps and strategy docs
- **[plans/](./plans/)** — Dated implementation plans
- **[audits/](./audits/)** — Block and codebase audits
- **[reviews/](./reviews/)** — Dated review notes

### [guides/](./guides/)

Development guides and best practices:

- [AI-ASSISTED-DEVELOPMENT.md](./guides/AI-ASSISTED-DEVELOPMENT.md) - AI-assisted development guide
- [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md) - Quick reference guide
- [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md) - Comprehensive guide
- [BLOCK-CONTROLS-ORGANIZATION.md](./guides/BLOCK-CONTROLS-ORGANIZATION.md) - Inspector controls decision tree (Settings vs. Style)
- [BLOCK-EXCLUSION-GUIDE.md](./guides/BLOCK-EXCLUSION-GUIDE.md) - Excluding third-party blocks from DSGo extensions
- [DEPLOYING-TO-WORDPRESS-ORG.md](./guides/DEPLOYING-TO-WORDPRESS-ORG.md) - Release process to WordPress.org
- [DESIGN-SYSTEM.md](./guides/DESIGN-SYSTEM.md) - Design system and theme.json tokens

### [compliance/](./compliance/)

Accessibility and compliance documentation:

- [ACCESSIBILITY-COLOR-CONTRAST-GUIDE.md](./compliance/ACCESSIBILITY-COLOR-CONTRAST-GUIDE.md) - Color contrast standards
- [GDPR-COMPLIANCE.md](./compliance/GDPR-COMPLIANCE.md) - GDPR compliance guide

### [formats/](./formats/)

RichText format documentation:

- [TEXT-STYLE.md](./formats/TEXT-STYLE.md) - Inline text style format (color, highlight, size)

### [templates/](./templates/)

Code templates and boilerplate:

- [BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js) - Block edit.js template

### [testing/](./testing/)

Testing documentation and strategies:

- [TESTING.md](./testing/TESTING.md) - E2E (Playwright) testing guide
- [TESTING-ABILITIES-API.md](./testing/TESTING-ABILITIES-API.md) - Abilities API manual testing walkthrough
- [E2E-LIFECYCLE.md](./testing/E2E-LIFECYCLE.md) - Playwright/browser lifecycle regression notes
- [FORM-SELECT-I18N.md](./testing/FORM-SELECT-I18N.md) - Form select i18n regression coverage

### [troubleshooting/](./troubleshooting/)

Debugging and problem-solving guides:

- [TROUBLESHOOTING.md](./troubleshooting/TROUBLESHOOTING.md) - Canonical troubleshooting guide (build, wp-env, dependencies, CI)
- [HANDLING-LINT-ERRORS.md](./troubleshooting/HANDLING-LINT-ERRORS.md) - Handling ESLint "unused import" false positives

## 📚 Documentation Structure

### Getting Started (New Contributors)

#### [GETTING-STARTED.md](./GETTING-STARTED.md)

Complete step-by-step guide for new contributors: prerequisites, stack overview, step-by-step setup, your first change, development tools, common workflows, and troubleshooting pointers.

**When to use**: First time setting up the project or helping someone else get started.

#### [ARCHITECTURE.md](./ARCHITECTURE.md)

Deep dive into project architecture and code organization: directory structure, block anatomy, build system, data flow, extension system, PHP backend, testing infrastructure, AI integration (Abilities API), and the Theme 1–6 editor UX foundations.

**When to use**: Understanding how the codebase works, onboarding to the project, or making architectural decisions.

#### [../CONTRIBUTING.md](../CONTRIBUTING.md)

Complete contribution guide and workflow: setup, architecture overview, development workflow, code standards, testing requirements, and the PR process.

**When to use**: Ready to contribute code or submitting a pull request.

#### [AI-ASSISTED-DEVELOPMENT.md](./guides/AI-ASSISTED-DEVELOPMENT.md)

Guide to AI-assisted development with Claude Code: available skills/commands, best practices, and common workflows.

**When to use**: Using AI tools to contribute, or curious how this plugin is built with AI assistance.

---

## 🎯 Quick Reference Links

### Critical Patterns (Read First!)

#### Color Controls — MOST IMPORTANT ⚠️

**Location**: [../.claude/CLAUDE.md](../.claude/CLAUDE.md#code-standards)

**Rule**: ALWAYS use `ColorGradientSettingsDropdown` in `<InspectorControls group="color">`, NEVER `PanelColorSettings`.

```javascript
import {
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes, clientId }) {
  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  return (
    <InspectorControls group="color">
      <ColorGradientSettingsDropdown
        panelId={clientId}
        title={__('Colors', 'designsetgo')}
        settings={[
          {
            label: __('Text Color', 'designsetgo'),
            colorValue: textColor,
            onColorChange: (color) =>
              setAttributes({ textColor: color || '' }),
            clearable: true,
          },
        ]}
        {...colorGradientSettings}
      />
    </InspectorControls>
  );
}
```

**Why This Matters**:

- `PanelColorSettings` is a deprecated WordPress pattern.
- `ColorGradientSettingsDropdown` places controls in the Styles tab (WordPress standard, better UX).
- There are zero `PanelColorSettings` instances left in `src/` — keep it that way.

#### Non-color inspector controls: `<DsgoInspectorPanel>`, not bare `PanelBody`

Every custom control outside of color must be wrapped in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` following the Settings → Style → Advanced three-panel convention — see `../.claude/CLAUDE.md` ("Inspector IA (Theme 3)") and `src/components/shared/DsgoInspectorPanel/`. Reaching for `PanelBody` directly is the older pattern several of the guides below still illustrate.

### Reference Documents

#### [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md)

- **Use for**: Quick reference during development
- **Contains**: Critical rules, decision trees, copy-paste patterns

#### [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)

- **Use for**: Deep understanding of patterns and rationale
- **Contains**: Major topics with real-world examples (some code samples predate Theme 3 — see the note above)

#### [BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js)

- **Use for**: Starting point for new blocks
- **Copy this file** when creating new blocks

### Specialized Guides

#### [BLOCK-CONTROLS-ORGANIZATION.md](./guides/BLOCK-CONTROLS-ORGANIZATION.md)

Inspector controls organization: Settings tab vs. Styles tab decision tree, Block Supports usage.

#### `.claude/docs/` guides

Several deeper technical guides live in `../.claude/docs/` rather than `docs/guides/` because they're written as working references for AI-assisted development and are linked directly from `../.claude/CLAUDE.md`:

- [`REFACTORING-GUIDE.md`](../.claude/docs/REFACTORING-GUIDE.md) - File-size limits and the standard block refactor pattern
- [`FSE-COMPATIBILITY-GUIDE.md`](../.claude/docs/FSE-COMPATIBILITY-GUIDE.md) - Full Site Editing / theme.json support checklist
- [`EDITOR-STYLING-GUIDE.md`](../.claude/docs/EDITOR-STYLING-GUIDE.md) - Declarative styling (`useInnerBlocksProps`, `:where()` specificity) — canonical version of the editor/frontend-parity lessons
- [`KSES-ALLOWLIST-GUIDE.md`](../.claude/docs/KSES-ALLOWLIST-GUIDE.md) - Why and how DSGo extends `wp_kses_post()`
- [`QUERY-BLOCK-GUIDE.md`](../.claude/docs/QUERY-BLOCK-GUIDE.md) - Dynamic Query block family developer guide

## 🎯 Common Tasks

### Creating a New Block

1. Copy [BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js) to `src/blocks/{block-name}/edit.js`
2. Update `block.json` with proper `supports` (native supports before custom controls)
3. Implement `save.js` matching `edit.js` structure
4. Add color controls using the `ColorGradientSettingsDropdown` pattern above, and wrap any other custom controls in `<DsgoInspectorPanel>`
5. Test in editor and frontend

### Adding Color Controls to an Existing Block

1. Import `ColorGradientSettingsDropdown` and `useMultipleOriginColorsAndGradients` as shown above
2. Add `clientId` to the function signature
3. Call `useMultipleOriginColorsAndGradients()`
4. Place the dropdown inside `<InspectorControls group="color">`

### Refactoring Large Files

1. Check file line count: `wc -l src/blocks/{block-name}/edit.js`
2. If >300 lines, extract components and utilities (see [`../.claude/docs/REFACTORING-GUIDE.md`](../.claude/docs/REFACTORING-GUIDE.md))
3. Extract components into `components/` directory
4. Extract utilities into `utils/` directory
5. Keep `index.js` focused on registration only

## 🔍 Finding Information

### "How do I add color controls?"

→ [../.claude/CLAUDE.md](../.claude/CLAUDE.md) or [BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js)

### "What's the proper block structure?"

→ [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md) or [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)

### "How do I make my block FSE-compatible?"

→ [`../.claude/docs/FSE-COMPATIBILITY-GUIDE.md`](../.claude/docs/FSE-COMPATIBILITY-GUIDE.md)

### "My file is too large, how do I refactor?"

→ [`../.claude/docs/REFACTORING-GUIDE.md`](../.claude/docs/REFACTORING-GUIDE.md)

### "Should I use Block Supports or custom controls?"

→ [BLOCK-CONTROLS-ORGANIZATION.md](./guides/BLOCK-CONTROLS-ORGANIZATION.md)

### "Something's broken (build, wp-env, CI, dependencies)"

→ [troubleshooting/TROUBLESHOOTING.md](./troubleshooting/TROUBLESHOOTING.md)

## 🎓 Learning Path

### Beginner

1. Read [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md) - Critical rules
2. Copy [BLOCK-TEMPLATE-EDIT.js](./templates/BLOCK-TEMPLATE-EDIT.js) - Build first block
3. Read [`../.claude/docs/FSE-COMPATIBILITY-GUIDE.md`](../.claude/docs/FSE-COMPATIBILITY-GUIDE.md) - Make it compatible

### Intermediate

1. Read [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md) - Deep understanding
2. Review [BLOCK-CONTROLS-ORGANIZATION.md](./guides/BLOCK-CONTROLS-ORGANIZATION.md) - Better UX patterns
3. Study [Design System](./guides/DESIGN-SYSTEM.md) - Proper styling

### Advanced

1. Review the extension architecture in [`planning/`](./planning/)
2. Study advanced patterns in [`patterns/`](./patterns/)
3. Contribute patterns back to [../.claude/CLAUDE.md](../.claude/CLAUDE.md)

## 📝 Contributing to Documentation

When you discover new patterns or best practices:

1. **Critical patterns** → Add to [../.claude/CLAUDE.md](../.claude/CLAUDE.md)
2. **Quick reference** → Add to [BEST-PRACTICES-SUMMARY.md](./guides/BEST-PRACTICES-SUMMARY.md)
3. **Deep explanations** → Add to [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)
4. **Specialized topics** → Create or update a specialized guide in the appropriate folder
5. Before adding a new doc, check whether an existing one already covers the topic — keep one canonical doc per topic rather than a second file with the same title.

---

**WordPress Compatibility**: 6.7+ (tested to 6.9 via `.wp-env.json`)
