# AI-Assisted Development Guide

**DesignSetGo is the first WordPress block plugin built 100% with AI assistance.** This guide shares the best practices, workflows, and lessons learned from building this entire plugin using Claude Code.

## 📋 Table of Contents

- [Why AI-Assisted Development?](#why-ai-assisted-development)
- [Getting Started with Claude Code](#getting-started-with-claude-code)
- [Available Slash Commands](#available-slash-commands)
- [Best Practices](#best-practices)
- [Common Workflows](#common-workflows)
- [Using Other AI Tools](#using-other-ai-tools)
- [Tips and Tricks](#tips-and-tricks)
- [Limitations and Validation](#limitations-and-validation)

## Why AI-Assisted Development?

### The DesignSetGo Story

This entire plugin—43 blocks, 11 extensions, complete documentation, testing infrastructure, and all features—was built using AI assistance (primarily Claude Code). This demonstrates that AI can be a powerful development partner when used correctly.

### Benefits

✅ **Faster development** - Rapid prototyping and iteration
✅ **Better documentation** - AI helps write comprehensive docs
✅ **Consistent patterns** - AI follows established conventions
✅ **Learning accelerator** - Understand WordPress patterns faster
✅ **Code quality** - AI can spot issues and suggest improvements
✅ **Reduced boilerplate** - AI handles repetitive code patterns

### When to Use AI

**Great for:**
- Creating new blocks with similar patterns
- Writing documentation
- Refactoring code
- Finding and fixing bugs
- Implementing established patterns
- Writing tests
- Explaining code

**Less suitable for:**
- Novel architectural decisions
- Complex state management logic
- Performance-critical code (without review)
- Security-sensitive operations (without review)

## Getting Started with Claude Code

### Installation

**Claude Code** is Anthropic's official CLI tool. It integrates directly with your IDE.

1. **Get access** to Claude Code (currently in beta)
2. **Install** via VSCode extension or CLI
3. **Authenticate** with your Anthropic account

### How Claude Code Uses This Project

Claude Code automatically reads:
- **`.claude/CLAUDE.md`** - Development patterns and context
- **Project structure** - Understands your codebase
- **Git history** - Sees recent changes
- **Documentation** - References all docs

**This means Claude Code already knows:**
- WordPress block development patterns
- Project-specific conventions (dsgo- prefix, etc.)
- File structure and organization
- Testing requirements
- Code standards

### Your First AI-Assisted Task

Try this simple example:

```
You: "Add a new text color control to the Icon block"
```

Claude Code will:
1. Read `.claude/CLAUDE.md` for color control patterns
2. Find the Icon block files
3. Add the attribute to `block.json`
4. Add the control to `edit.js` using the correct pattern
5. Update styles in `style.scss`
6. Test the changes

**Key insight:** Because the patterns are documented in `.claude/CLAUDE.md`, Claude Code will use the correct modern WordPress patterns automatically.

## Available Slash Commands

DesignSetGo includes custom slash commands that accelerate common tasks.

### Block Development

#### `/add-block`
**Purpose:** Create a new Gutenberg block with complete scaffolding

**Usage:**
```
/add-block
```

**What it does:**
- Creates block directory structure
- Generates `block.json` with proper metadata
- Creates `edit.js` with modern patterns
- Creates `save.js` with matching structure
- Adds `style.scss` and `editor.scss`
- Registers block in main index
- Follows all WordPress best practices

**Example:**
```
You: /add-block
AI: What would you like to call the new block?
You: Timeline
AI: [Creates complete Timeline block structure]
```

#### `/add-variation`
**Purpose:** Create a block variation with preset configurations

**Usage:**
```
/add-variation
```

**What it does:**
- Creates variation with preset attributes
- Adds to variations array
- Includes proper metadata
- Follows variation best practices

#### `/add-extension`
**Purpose:** Create a block extension to enhance core WordPress blocks

**Usage:**
```
/add-extension
```

**What it does:**
- Creates extension directory
- Sets up filter hooks
- Creates inspector panel
- Adds styles
- Ensures proper block targeting

### Code Quality

#### `/lint`
**Purpose:** Lint and auto-fix JavaScript, CSS, and PHP files

**Usage:**
```
/lint
```

**What it does:**
- Runs `npm run lint:js` for JavaScript
- Runs `npm run lint:css` for CSS/SCSS
- Runs `npm run lint:php` for PHP
- Reports errors but does NOT auto-fix (intentional)
- Suggests fixes

**Note:** Linters report errors only. Run `npm run format` to auto-format.

#### `/quick-fix`
**Purpose:** Quick fixes for common issues

**Usage:**
```
/quick-fix
```

**What it does:**
- Fixes build errors
- Addresses missing styles
- Resolves validation errors
- Common WordPress issues

### Development Workflow

#### `/build`
**Purpose:** Build plugin and watch for changes

**Usage:**
```
/build
```

**What it does:**
- Runs `npm run build` for production
- Or `npm start` for development with watch mode
- Reports build status
- Identifies errors

#### `/test`
**Purpose:** Run all plugin tests

**Usage:**
```
/test
```

**What it does:**
- Runs Jest unit tests
- Runs Playwright E2E tests
- Reports test results
- Identifies failing tests

### Plugin Management

#### `/plugin-info`
**Purpose:** Display DesignSetGo plugin architecture and structure

**Usage:**
```
/plugin-info
```

**What it does:**
- Shows plugin overview
- Lists all blocks
- Displays architecture
- Shows available commands

#### `/deploy`
**Purpose:** Prepare plugin for WordPress.org deployment

**Usage:**
```
/deploy
```

**What it does:**
- Creates production build
- Generates ZIP file
- Validates plugin structure
- Checks readme.txt
- Ensures WordPress.org compliance

### Auditing & Reviews

#### `/plugin-review`
**Purpose:** Comprehensive WordPress plugin audit by experienced developer

**What it checks:**
- WordPress coding standards
- Security best practices
- Performance optimization
- Accessibility compliance
- Translation readiness

#### `/security-audit`
**Purpose:** Run comprehensive security audit

**What it checks:**
- Input sanitization
- Output escaping
- Nonce verification
- SQL injection prevention
- XSS vulnerabilities

#### `/performance-audit`
**Purpose:** Deep performance and optimization audit

**What it checks:**
- Bundle sizes
- Code splitting opportunities
- Unnecessary re-renders
- Database query optimization
- Asset loading efficiency

#### `/block-supports-audit`
**Purpose:** Audit blocks for WordPress Block Supports optimization

**What it checks:**
- Custom controls that should use Block Supports
- Missing Block Supports opportunities
- Migration recommendations

### Utilities

#### `/check-compat`
**Purpose:** Check compatibility with WordPress and Gutenberg versions

#### `/i18n-update`
**Purpose:** Update translation files and check for untranslated strings

## Best Practices

### 1. Start with Context

**Before asking AI to code, provide context:**

```
❌ BAD:
"Add a border control to the Grid block"

✅ GOOD:
"Add a border control to the Grid block. This should use WordPress
Block Supports if possible. Check .claude/CLAUDE.md for the pattern.
Make sure to add it to block.json supports section."
```

**Why:** AI makes better decisions with more context.

### 2. Reference Documentation

**Point AI to existing patterns:**

```
"Add color controls to the Pill block following the pattern in
.claude/CLAUDE.md under 'Color Controls Pattern'. Use
ColorGradientSettingsDropdown, not PanelColorSettings."
```

**Why:** Ensures consistency with project standards.

### 3. Iterative Development

**Break complex tasks into steps:**

```
Step 1: "Create the Timeline block structure with block.json"
Step 2: "Add the edit.js component with timeline item support"
Step 3: "Implement the frontend view.js for interactivity"
Step 4: "Add styles for horizontal and vertical layouts"
```

**Why:** Easier to validate each step, catch issues early.

### 4. Ask for Explanations

**Don't just accept code—understand it:**

```
"Can you explain why you used useInnerBlocksProps here instead of
plain InnerBlocks?"
```

**Why:** You learn patterns and can validate AI decisions.

### 5. Validate Against Documentation

**After AI generates code, check:**

```
"Does this follow the patterns in .claude/CLAUDE.md?"
"Are we using WordPress Block Supports correctly?"
"Is this compatible with the FSE guide?"
```

**Why:** AI can make mistakes or use outdated patterns.

### 6. Test Thoroughly

**AI-generated code still needs testing:**

- ✅ Test in block editor
- ✅ Test on frontend
- ✅ Test responsive behavior
- ✅ Check browser console for errors
- ✅ Validate with linters
- ✅ Run automated tests

**Why:** AI doesn't execute code—it predicts it.

### 7. Keep Files Under 300 Lines

**When AI generates large files:**

```
"This file is 450 lines. Please refactor it following the
REFACTORING-GUIDE.md to keep files under 300 lines. Extract
components and utilities."
```

**Why:** Maintains code quality and readability.

### 8. Use Specific Commands

**Leverage npm scripts and slash commands:**

```
❌ "Check the code for errors"
✅ "Run /lint to check for linting errors"

❌ "Make sure the build works"
✅ "Run /build to verify the production build"
```

**Why:** More accurate, faster results.

## Common Workflows

### Creating a New Block (With AI)

**Step-by-step workflow:**

**1. Use the slash command:**
```
/add-block
```

**2. Specify requirements:**
```
"Create a 'Testimonial' block with:
- Author name and role attributes
- Image support
- Quote text (InnerBlocks)
- Star rating (1-5)
- Layout options (card, minimal, full)
Follow the block template in docs/BLOCK-TEMPLATE-EDIT.js"
```

**3. AI generates structure:**
- `block.json` with attributes and supports
- `edit.js` with InspectorControls
- `save.js` with matching structure
- `style.scss` for styling

**4. Review and iterate:**
```
"Add color controls following the ColorGradientSettingsDropdown
pattern from .claude/CLAUDE.md"
```

**5. Test:**
```
npm start
# Test in editor and frontend
```

**6. Lint and fix:**
```
/lint
# Fix any reported issues
npm run format
```

**7. Commit:**
```
git add src/blocks/testimonial/
git commit -m "feat: add Testimonial block with star ratings"
```

### Debugging with AI

**When you encounter an error:**

**1. Share the error:**
```
"I'm getting this error when inserting the Accordion block:
[paste error message]

The error appears in src/blocks/accordion/edit.js
Can you help debug?"
```

**2. AI analyzes:**
- Reads the file
- Identifies the issue
- Suggests a fix

**3. Validate the fix:**
```
"Explain why this fixes the issue"
```

**4. Test the fix:**
```
npm start
# Test in browser
# Check console
```

### Refactoring with AI

**Example: File too large**

**1. Identify the issue:**
```
"The edit.js file for the Form Builder block is 450 lines.
This exceeds our 300-line limit. Can you refactor it following
docs/REFACTORING-GUIDE.md?"
```

**2. AI suggests structure:**
```
src/blocks/form-builder/
├── edit.js (main component, <150 lines)
├── components/
│   ├── inspector/
│   │   ├── FormSettingsPanel.js
│   │   ├── EmailPanel.js
│   │   └── ValidationPanel.js
│   └── FormPreview.js
└── utils/
    └── validation.js
```

**3. Review extracted code:**
- Check imports are correct
- Verify functionality preserved
- Test in editor

**4. Validate:**
```
wc -l src/blocks/form-builder/edit.js
# Should be < 300
```

### Writing Documentation with AI

**Example: Document a new feature**

**1. Request documentation:**
```
"Write user documentation for the Timeline block. Include:
- Overview of what it does
- Available attributes/settings
- Usage examples
- Common patterns
Format for the GitHub Wiki"
```

**2. Review and edit:**
- Check accuracy
- Add screenshots (manual)
- Verify examples work

**3. Request developer docs:**
```
"Now write developer documentation for the Timeline block covering:
- Block architecture
- Props and attributes
- Extension points
- Testing approach
Format for docs/BLOCKS/ folder"
```

## Using Other AI Tools

### GitHub Copilot

**Integration with DesignSetGo:**

Copilot can help with:
- Inline code suggestions
- Function completion
- Pattern repetition

**Best practices:**
1. Still reference `.claude/CLAUDE.md` manually
2. Review all suggestions against project standards
3. Don't accept suggestions blindly
4. Particularly useful for repetitive tasks

### ChatGPT

**Using ChatGPT with the codebase:**

**1. Provide context:**
```
"I'm working on DesignSetGo, a WordPress block plugin. Here's
our color controls pattern: [paste from .claude/CLAUDE.md]

I need to add color controls to a new block. Can you show me
the correct pattern?"
```

**2. Share code:**
```
"Here's my current edit.js: [paste code]
Can you add the color controls following the pattern?"
```

**3. Validate:**
- Compare against `.claude/CLAUDE.md`
- Test in editor

### WordPress Abilities API (Programmatic)

**For automation and AI agents:**

DesignSetGo exposes the WordPress Abilities API for programmatic access:

```bash
# List all blocks
curl -X POST http://site.com/wp-json/wp-abilities/v1/abilities/designsetgo/list-blocks/execute \
  -u "user:pass" \
  -d '{"category": "all"}'

# Insert a block
curl -X POST http://site.com/wp-json/wp-abilities/v1/abilities/designsetgo/add-block/execute \
  -u "user:pass" \
  -d '{
    "post_id": 123,
    "block_name": "designsetgo/flex",
    "attributes": {
      "direction": "row",
      "justifyContent": "center"
    }
  }'
```

See [ABILITIES-API.md](ABILITIES-API.md) for complete documentation.

## Tips and Tricks

### 1. Build Context Incrementally

**Instead of:**
```
"Here's my entire 500-line file, fix the bug"
```

**Try:**
```
"I have a bug in the Tabs block where clicking a tab doesn't
switch content. The issue is in src/blocks/tabs/view.js around
line 45. Here's the relevant code: [paste 20-30 lines]"
```

### 2. Use Git for Safety

**Before AI makes large changes:**
```bash
git add .
git commit -m "wip: before AI refactor"
# Now ask AI to refactor
# Easy to revert if needed
```

### 3. Request Multiple Approaches

**For complex decisions:**
```
"I need to implement a Timeline block. What are three different
approaches for the data structure, with pros and cons of each?"
```

### 4. Learn the Patterns

**Ask AI to teach you:**
```
"Can you explain the difference between useInnerBlocksProps()
and plain InnerBlocks? When should I use each?"
```

### 5. Validate Against Multiple Sources

**Cross-reference:**
1. AI's suggestion
2. `.claude/CLAUDE.md` patterns
3. Existing block implementations
4. WordPress official docs

### 6. Use Descriptive Variable Names

**Help AI understand context:**
```javascript
// ❌ Harder for AI to work with
const a = props.attributes;
const s = setAttributes;

// ✅ Easier for AI to understand
const { iconSize, iconColor } = attributes;
const updateIconSize = (size) => setAttributes({ iconSize: size });
```

### 7. Keep AI Sessions Focused

**One topic per session:**
```
✅ Session 1: "Create the Timeline block structure"
✅ Session 2: "Add animation to Timeline items"

❌ Single session: "Create Timeline, add animations, fix Accordion
bug, update documentation, and refactor Grid block"
```

### 8. Save Successful Patterns

**When AI provides a great solution:**
```
"This pattern for handling responsive controls is great.
Can you add it to our documentation so other blocks can use it?"
```

## Limitations and Validation

### AI Can Make Mistakes

**Common issues to watch for:**

❌ **Outdated patterns** - AI might suggest deprecated methods
❌ **Missing context** - AI doesn't see the full codebase
❌ **Over-engineering** - AI might add unnecessary complexity
❌ **Security gaps** - AI might miss sanitization/escaping
❌ **Performance issues** - AI might not optimize for performance

### Validation Checklist

After AI generates code, always:

- [ ] **Read and understand** the code
- [ ] **Check against** `.claude/CLAUDE.md` patterns
- [ ] **Run linters** (`/lint` or `npm run lint:js`)
- [ ] **Test in editor** - Insert block, use all controls
- [ ] **Test on frontend** - View saved page
- [ ] **Check console** - No JavaScript errors
- [ ] **Test responsive** - Mobile/tablet/desktop
- [ ] **Validate accessibility** - Screen reader, keyboard nav
- [ ] **Review security** - Proper sanitization/escaping
- [ ] **Check performance** - Bundle size, re-renders

### When to Seek Human Review

**Ask for human review when:**
- Security-sensitive code
- Complex state management
- Performance-critical sections
- Architectural decisions
- Novel patterns not in documentation

### Red Flags

**Watch for these warning signs:**

🚩 AI suggests using `PanelColorSettings` (deprecated)
🚩 Direct DOM manipulation instead of React patterns
🚩 Hardcoded values instead of theme.json
🚩 Missing `useBlockProps()` or `useInnerBlocksProps()`
🚩 No internationalization (`__()` wrapper)
🚩 Custom controls instead of Block Supports
🚩 Files over 300 lines without refactoring suggestion

## Real-World Examples

### Example 1: Building the Icon Block

**Initial prompt:**
```
"Create an Icon block that:
- Supports 160+ icons from a curated library
- Has size controls (px, %, em, rem)
- Supports color controls (text, background)
- Has alignment options
- Follows all WordPress best practices"
```

**AI-generated structure:**
```
src/blocks/icon/
├── block.json
├── edit.js
├── save.js
├── style.scss
├── editor.scss
├── components/
│   └── IconPicker.js
└── utils/
    └── icons.js
```

**Iteration:**
```
"The IconPicker component is 400 lines. Extract the icon search
logic to a utility and the icon categories to a separate component."
```

**Result:** Well-structured, maintainable code following all project standards.

### Example 2: Refactoring for Block Supports

**Prompt:**
```
"The Counter block has custom color controls using PanelColorSettings.
According to .claude/CLAUDE.md, we should use
ColorGradientSettingsDropdown instead. Can you migrate it?"
```

**AI process:**
1. Reads current implementation
2. Reads `.claude/CLAUDE.md` pattern
3. Migrates to new pattern
4. Updates all references
5. Tests for compatibility

**Validation:**
```
"Can you show me the before and after to ensure the functionality
is preserved?"
```

### Example 3: Documentation Sprint

**Prompt:**
```
"I need comprehensive documentation for all 43 blocks. For each block:
1. User guide (what it does, how to use)
2. Developer guide (architecture, extension points)
3. Examples and screenshots placeholders

Start with container blocks (Flex, Grid, Stack)"
```

**AI generated:**
- Complete documentation structure
- Consistent formatting
- Code examples for each block
- Extension patterns

**Human contribution:**
- Add actual screenshots
- Verify examples work
- Add real-world use cases

## Summary: The AI-Assisted Development Mindset

### Core Principles

1. **AI is a tool, not a replacement** - You're still the architect
2. **Validate everything** - AI generates code, you ensure quality
3. **Document patterns** - Good docs = better AI assistance
4. **Iterate rapidly** - Use AI for fast prototyping, then refine
5. **Learn continuously** - Ask AI to explain, don't just copy
6. **Test thoroughly** - AI doesn't execute code, you must validate

### The Workflow Loop

```
1. Define the task clearly
   ↓
2. Provide context (patterns, constraints)
   ↓
3. AI generates solution
   ↓
4. Review and understand
   ↓
5. Validate against standards
   ↓
6. Test thoroughly
   ↓
7. Iterate if needed
   ↓
8. Document new patterns
```

### Success Metrics

**You're using AI effectively when:**

✅ Development is faster, but quality is maintained
✅ You understand all the code in your project
✅ You can explain AI decisions to others
✅ AI suggestions match project standards
✅ You catch AI mistakes quickly
✅ You contribute new patterns to documentation
✅ You're learning new techniques from AI

---

## Getting Help

**Questions about AI-assisted development?**

- **Read**: [.claude/CLAUDE.md](.claude/CLAUDE.md) - All development patterns
- **Ask**: [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)
- **Report**: AI-generated bugs to [Issues](https://github.com/jnealey-godaddy/designsetgo/issues)

**Remember:** This entire plugin is proof that AI-assisted development works when done thoughtfully. You can do it too! 🚀

---

**Last Updated**: 2025-11-15 | **WordPress**: 6.4+ | **Built with**: Claude Code
