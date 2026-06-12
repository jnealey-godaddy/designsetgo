# DesignSetGo

Professional Gutenberg block library with 53 blocks and 16 powerful extensions - complete Form Builder, Dynamic Query family, container system, interactive elements, maps, modals, breadcrumbs, scroll effects, animations, and WooCommerce blocks. Built with WordPress standards for guaranteed editor/frontend parity.

## 🤖 **First AI-Native WordPress Block Library**

DesignSetGo is the **first WordPress block plugin** to integrate with the WordPress 6.9 Abilities API, making it fully accessible to AI agents and automation tools. Build pages programmatically with Claude, ChatGPT, or custom AI workflows.

> **[📖 Read the Abilities API Documentation →](docs/api/ABILITIES-API.md)**

## Status

✨ **Active Development** - Core blocks complete, expanding features

**Current Progress:**
- ✅ Project foundation and architecture complete
- ✅ Build system configured (webpack + @wordpress/scripts)
- ✅ PHP plugin architecture implemented
- ✅ 53 custom blocks across 8+ categories with FSE integration
- ✅ Complete Form Builder system (13 blocks: builder + 11 field types)
- ✅ Container system (Row, Section, Grid)
- ✅ Interactive blocks (Tabs, Accordion, Flip Card, Reveal, Scroll Slides, Sticky Sections, Scroll Marquee, Slider, Counters, Progress)
- ✅ Dynamic Query family (6 blocks: Query, Pagination, Filter, Results, Group Header, No Results)
- ✅ Content blocks (Icon, Icon Button, Icon List, Card, Pill, Divider, Countdown, Blobs, Dynamic Image)
- ✅ WooCommerce blocks (Product Categories Grid, Product Showcase Hero)
- ✅ Location block (Map with OpenStreetMap & Google Maps support)
- ✅ 16 Block Extensions - Animations, Sticky Header, Clickable Groups, Background Video, Responsive, Hover Effects, Conditional Visibility, Dynamic Tags, Style Binding, SVG Patterns, Scroll Effects (Parallax, Text Reveal, Expanding Background), and more
- ✅ Global styles system (theme.json integration)
- ✅ Block patterns library
- ✅ Comprehensive testing setup (E2E + Unit + PHP)
- ✅ WordPress 6.7+ compatibility
- ✅ WordPress Abilities API integration (AI-native)
- ✅ Comprehensive documentation (15,000+ lines)
- ✅ Zero JavaScript errors - comprehensive linting cleanup
- ✅ ViewScript support for interactive blocks
- ✅ 9 language translations (de, es, fr, it, ja, nl, pt, ru, zh)
- 🔄 Expanding pattern library

See [CLAUDE.md](.claude/CLAUDE.md) for development learnings and best practices.

## Features at a Glance

| Category | Features |
|----------|----------|
| **🤖 AI Integration** | **WordPress Abilities API** — First plugin with AI-native programmatic access. **Per-URL Markdown** — any published URL returns Markdown when a client sends `Accept: text/markdown`, passes the acceptmarkdown.com contract. |
| **Blocks** | **53 blocks** across 8+ categories: **Layout (3)** - Row, Section, Grid; **Forms (13)** - Complete form system with AJAX, spam protection, 11 field types; **Interactive (15)** - Tabs, Accordion, Flip Card, Slider, Scroll Slides, Sticky Sections, Scroll Marquee, Scroll Accordion, Image Accordion, Counters, Progress, Comparison Table, Timeline, Modal, Modal Trigger; **Dynamic Query (6)** - Query, Pagination, Filter, Results, Group Header, No Results; **Typography & Navigation (3)** - Advanced Heading, Breadcrumbs (Schema.org), Table of Contents; **Visual (9)** - 500+ Icons, Icon Button, Icon List, Pills, Cards, Dividers, Countdown, Blobs, Dynamic Image; **Media & Location (2)** - Fifty Fifty split layout, Map (OSM + Google Maps); **WooCommerce (2)** - Product Categories Grid, Product Showcase Hero |
| **Extensions** | **16 Extensions** - Block Animations (24 effects), Sticky Header, Clickable Groups, Background Video, Responsive Visibility, Hover Effects, Conditional Visibility, Dynamic Tags, Style Binding, SVG Patterns (25+), Max Width, Custom CSS, Grid Span, Grid Mobile Order, Reveal Control, Text Alignment Inheritance |
| **Patterns** | Pre-designed layouts (Hero, CTA, Features, FAQ) |
| **FSE Ready** | Full Site Editing compatible, theme.json integration, dual categorization |
| **Performance** | Optimized bundles, code-splitting, no jQuery, declarative styling, viewScript support |
| **Accessibility** | WCAG 2.1 AA compliant, keyboard navigation, screen reader friendly, Schema.org markup |
| **Developer DX** | WordPress best practices, < 300 lines per file, 15,000+ lines of docs, comprehensive refactoring guides |
| **Testing** | E2E (Playwright) + Unit (Jest) + PHP (PHPUnit + PHPStan) |
| **i18n** | Translation-ready with 9 language translations included |

## Quick Start

### Requirements

- **Node.js**: 18+ (for development)
- **PHP**: 8.0+
- **WordPress**: 6.7+
- **npm**: 8+ (or pnpm/yarn)

### Getting Started

```bash
# 1. Clone the repository
git clone https://github.com/jnealey-godaddy/designsetgo.git
cd designsetgo

# 2. Install dependencies
npm install

# 3. Start WordPress environment
npx wp-env start
# This starts a local WordPress at http://localhost:8888
# Login: admin / password

# 4. Start development (in a new terminal)
npm start
# This watches files and rebuilds automatically
```

**That's it!** The plugin is now active in your local WordPress. Visit [http://localhost:8888/wp-admin](http://localhost:8888/wp-admin) and start creating with DesignSetGo blocks.

### WordPress Environment

```bash
# Start WordPress
npx wp-env start

# Stop WordPress
npx wp-env stop

# Reset WordPress (clean install)
npx wp-env clean all

# Access:
# - Frontend: http://localhost:8888
# - Admin: http://localhost:8888/wp-admin (admin/password)
# - Database: http://localhost:8889 (phpMyAdmin - root/password)
```

### Development Workflow

```bash
# Start development with hot reload
npm start

# Build for production
npm run build

# Code Quality
npm run lint:js          # Lint JavaScript
npm run lint:css         # Lint SCSS/CSS
npm run lint:php         # Lint PHP (requires Composer)
npm run format           # Format all code (Prettier)

# Testing
npm run test:unit        # Run Jest unit tests
npm run test:e2e         # Run Playwright E2E tests
npm run test:e2e:ui      # Run E2E tests with UI
npm run test:e2e:debug   # Debug E2E tests
npm run test:php         # Run PHPUnit tests (requires Composer)

# Security & Compliance
npm run security:audit   # Check for vulnerabilities and license issues

# Create plugin ZIP for distribution
npm run plugin-zip
```

## Current Features

### 53 Custom Blocks

📚 **[View Complete Blocks Reference →](https://github.com/jnealey-godaddy/designsetgo/wiki/Blocks-Reference)**

#### Layout Containers (3 Blocks)
- **Row** - Horizontal layouts with flexible alignment and spacing
- **Section** - Full-width sections with inner content width constraints
- **Grid Container** - Responsive CSS Grid with column/row controls

#### Interactive Content (15 Blocks + Child Blocks)
- **Accordion** - Collapsible content panels with customizable icons
- **Tabs** - Tabbed content interface with icon support
- **Slider** - Modern carousel with multiple effects (slide, fade, zoom)
- **Flip Card** - Interactive cards that flip on hover/click
- **Reveal** - Content that reveals on hover with multiple effects
- **Scroll Accordion** - Sticky stacking accordion triggered by scroll
- **Image Accordion** - Expandable image panels for portfolios
- **Counter Group** - Animated counting statistics
- **Progress Bar** - Animated progress indicators with labels
- **Scroll Marquee** - Horizontal scrolling marquee with click-drag and mouse wheel support
- **Scroll Slides** - Scroll-pinned slideshow with crossfade transitions
- **Sticky Sections** - Card-stacking scroll effect where sections stack as you scroll
- **Comparison Table** - Feature comparison tables for products, services, or plans
- **Timeline** - Chronological events, company history, or process steps with scroll animations
- **Modal** - Accessible popup/dialog with customizable triggers (moved from Modals category)
- **Modal Trigger** - Button or element to open modals
- Plus child blocks: Accordion Item, Tab, Slide, Flip Card Face, Image Accordion Item, Scroll Accordion Item, Counter, Timeline Item

#### Content & UI Elements (10 Blocks)
- **Icon** - 500+ icons with shapes, sizes, and animations
- **Icon Button** - Icon-based buttons with extensive styling options
- **Icon List** - Lists with custom icons and formatting
- **Card** - Content cards with images, headers, and call-to-action buttons
- **Pill** - Badge/tag components for labels and categories
- **Divider** - Styleable content separators with multiple styles
- **Countdown Timer** - Countdown to specific dates and times
- **Blobs** - Organic animated background shapes
- **Breadcrumbs** - Navigation breadcrumbs with customizable separators
- **Table of Contents** - Auto-generated TOC from headings
- Plus child blocks: Icon List Item

#### Modals (2 Blocks)
- **Modal** - Accessible popup/dialog with customizable triggers
- **Modal Trigger** - Button or element to open modals

#### Media Layouts (1 Block)
- **Fifty Fifty** - Full-width 50/50 split layout with edge-to-edge media on one side and constrained content on the other. Includes media position toggle (left/right), focal point picker, min height control, content vertical alignment, and mobile-responsive stacking.

#### Location (1 Block)
- **Map** - Interactive maps with dual provider support:
  - OpenStreetMap (no API key required, privacy-friendly)
  - Google Maps (requires API key, advanced features)
  - Custom markers, zoom control, height settings
  - Multiple map styles and themes
  - Responsive and accessible

#### Form Builder (13 Blocks)
- **Form Builder** - Complete form system with 11 field types:
  - Text, Email, Phone, URL fields
  - Date, Time, Number fields
  - Checkbox, Select, Textarea fields
  - Hidden field
- AJAX submission with honeypot spam protection
- Cloudflare Turnstile spam protection
- Email notifications with customizable templates
- Success/error message customization
- Form validation and error handling

### 16 Block Extensions

Extensions work with **any WordPress block** (core, third-party, or DesignSetGo):

**Block Animations Extension** - Professional entrance/exit animations
- 24+ animation types (fadeIn, slideIn, bounce, zoom, flip, rotate, etc.)
- Scroll-triggered animations
- Configurable duration, delay, and easing
- Respects `prefers-reduced-motion`
- CSS-only implementation (no JS libraries)

**Hover Effects Extension** - Animated hover interactions
- Works with any block, including core blocks
- Multiple hover animation styles

**Sticky Header Extension** - Make headers/navs stick on scroll
- Smooth sticky behavior with customizable offset
- Works with core Group, Row, Section blocks
- Mobile-responsive with hide on scroll option
- Z-index control for proper layering

**Clickable Groups Extension** - Turn any group/container into a clickable link
- Link entire cards or sections
- Accessibility-friendly with proper ARIA labels
- External link support with security (rel="noopener")
- Prevents conflicts with inner interactive elements

**Background Video Extension** - Add video backgrounds to containers
- YouTube and self-hosted video support
- Overlay color/opacity controls
- Mobile fallback images
- Autoplay, loop, and mute controls
- Accessibility considerations

**Responsive Visibility Extension** - Control block visibility by device
- Hide/show on Desktop, Tablet, Mobile
- Works with any WordPress block
- CSS-based (no JavaScript)

**Conditional Visibility Extension** - Show or hide blocks by rule
- Rules: meta, taxonomy, login state, query loop index
- AND/OR rule combinations
- Editor previews mirror frontend evaluation

**Dynamic Tags Extension** - Bind block content to live data
- Bind text, titles, URLs, and images to post meta, ACF, Meta Box, Pods, JetEngine, or custom sources
- Works on DesignSetGo blocks and core blocks via WordPress 6.9 Block Bindings API
- Live preview in the editor

**Style Binding Extension** - Map CSS properties to dynamic data
- Bind any CSS property (including custom properties) to a DSGo binding source
- Validated against an allowlist to block dangerous values

**SVG Patterns Extension** - Add background patterns to any block
- 31 repeatable SVG background patterns
- Customizable color, opacity, and scale

**Max Width Extension** - Constrain content width
- Custom max-width controls
- Auto-center alignment
- Responsive width constraints
- Works with containers and content blocks

**Custom CSS Extension** - Add custom CSS to any block
- Block-specific CSS editor
- Advanced styling control
- Scoped to individual blocks

**Grid Span Extension** - Control grid item spanning
- Column span (1-12 columns)
- Row span controls
- Works with Grid Container and core Columns block

**Grid Mobile Order Extension** - Control mobile stacking order
- Reorder grid items on mobile without changing HTML structure
- Order values 0-10, lower numbers appear first
- Only active for blocks inside Grid containers

**Reveal Control Extension** - Advanced hover reveal settings
- Timing and transition controls
- Direction and distance settings
- Works with Reveal block

**Text Alignment Inheritance** - Inherit alignment from parent blocks
- Consistent text alignment across nested blocks
- Parent-child context awareness

**Draft Mode Extension** - Control draft content visibility
- Hide blocks from frontend while visible in editor
- Useful for work-in-progress content

**Vertical Scroll Parallax Extension** - Smooth parallax scroll effects
- Configurable parallax intensity
- Performance-optimized scroll handling
- Respects `prefers-reduced-motion`

**Text Reveal Extension** - Animated text reveal on scroll
- Multiple reveal directions and styles
- Scroll-triggered animation
- Configurable timing and easing

**Expanding Background Extension** - Dynamic expanding backgrounds
- Animated background expansion on scroll
- Customizable expansion direction and speed
- CSS-only implementation for performance

### Block Patterns

Pre-designed layouts ready to use:
- **Hero Section** - Full-width hero with container
- **Three Column Grid** - Feature/service showcase
- **Centered CTA** - Call-to-action section
- **FAQ Accordion** - Frequently asked questions

### Global Styles System
- FSE-first approach using theme.json
- Color palette integration
- Spacing scale (xs, sm, md, lg, xl)
- Typography presets
- Full Twenty Twenty-Five theme compatibility

### Developer Experience
- WordPress best practices (useBlockProps, useInnerBlocksProps)
- Declarative styling (no DOM manipulation)
- Comprehensive JSDoc documentation
- Refactored component architecture (< 300 lines per file)
- Security-first approach (sanitization, escaping, nonces)

## Project Structure

```
designsetgo/
├── includes/              # PHP classes
│   ├── admin/            # Admin interface
│   ├── blocks/           # Block registration
│   ├── patterns/         # Pattern registration
│   └── class-*.php       # Core classes
├── src/                  # JavaScript source
│   ├── blocks/          # Block implementations
│   │   └── container/   # Container block
│   ├── components/      # Shared React components
│   ├── extensions/      # Block extensions
│   ├── styles/          # Global SCSS
│   └── utils/           # Utility functions
├── build/               # Compiled output
├── patterns/            # Block patterns (PHP)
└── designsetgo.php      # Main plugin file
```

## Documentation

Complete documentation for users and developers.

### 📖 For Users

**[Visit the DesignSetGo Wiki](https://github.com/jnealey-godaddy/designsetgo/wiki)** for user-facing documentation:

- **[Quick Start Guide](https://github.com/jnealey-godaddy/designsetgo/wiki/Quick-Start)** - Get up and running in 5 minutes
- **[Installation Guide](https://github.com/jnealey-godaddy/designsetgo/wiki/Installation)** - All installation methods
- **[Blocks Reference](https://github.com/jnealey-godaddy/designsetgo/wiki/Blocks-Reference)** - Complete blocks documentation
- **[Troubleshooting](https://github.com/jnealey-godaddy/designsetgo/wiki/Troubleshooting)** - Common issues and solutions

### 👨‍💻 For Contributors & Developers

**[View Developer Documentation](docs/)** - Technical guides in the `/docs/` folder:

**Getting Started:**
- **[Getting Started Guide](docs/GETTING-STARTED.md)** ⭐ **Start here!**
  - Complete setup walkthrough for new contributors
  - Prerequisites and installation
  - Making your first change
  - Common workflows and troubleshooting

- **[Architecture Overview](docs/ARCHITECTURE.md)** ⭐ **Understand the code!**
  - Project structure and folder organization
  - How blocks work (code → editor → frontend)
  - Build system and asset pipeline
  - Data flow and component architecture

- **[Contributing Guide](CONTRIBUTING.md)** ⭐ **Ready to contribute!**
  - Complete contribution workflow
  - Code standards and best practices
  - Pull request process
  - Getting help

**Development Guides:**
- **[Best Practices Summary](docs/guides/BEST-PRACTICES-SUMMARY.md)** - Quick reference patterns
- **[Block Development (Comprehensive)](docs/guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)** - Deep dive
- **[WordPress Block Editor Best Practices](docs/guides/WORDPRESS-BLOCK-EDITOR-BEST-PRACTICES.md)**
- **[FSE Compatibility Guide](docs/guides/WORDPRESS-BLOCK-EDITOR-BEST-PRACTICES.md)** - Full Site Editing integration
- **[Testing Guide](TESTING.md)** - E2E and unit testing

**Specialized Topics:**
- **[Block Controls Organization](docs/guides/BLOCK-CONTROLS-ORGANIZATION.md)**
- **[Color Controls Pattern](docs/patterns/COLOR-CONTROLS-PATTERN.md)**
- **[Width & Layout Patterns](docs/patterns/WIDTH-LAYOUT-PATTERNS.md)**
- **[Extension vs Custom Blocks](docs/planning/EXTENSION-VS-CUSTOM-BLOCKS.md)**
- **[Abilities API](docs/api/ABILITIES-API.md)** - AI integration

**AI-Assisted Development:**
- **[AI-Assisted Development Guide](docs/guides/AI-ASSISTED-DEVELOPMENT.md)** - Complete guide to building with AI
- **[.claude/CLAUDE.md](.claude/CLAUDE.md)** - Development patterns for AI agents

### 🗺️ Documentation Map

```
New to the project?
  ↓
1. Read GETTING-STARTED.md (setup and first contribution)
  ↓
2. Read ARCHITECTURE.md (understand the codebase)
  ↓
3. Read CONTRIBUTING.md (contribution workflow)
  ↓
4. Pick a good-first-issue and start coding!
  ↓
Reference .claude/CLAUDE.md as you develop
```

> **Note**: The `/docs/` folder contains **developer documentation**. User-facing docs are in the [Wiki](https://github.com/jnealey-godaddy/designsetgo/wiki).

## Roadmap

### ✅ Completed (Phase 1)
- 53 custom blocks across 8+ categories (Layout, Forms, Interactive, Dynamic Query, Typography & Navigation, Visual, Media & Location, WooCommerce)
- 16 block extensions (Animations, Sticky Header, Clickable Groups, Background Video, Responsive Visibility, Hover Effects, Conditional Visibility, Dynamic Tags, Style Binding, SVG Patterns, Scroll Effects, and more)
- Global styles integration with theme.json
- Animation system (24+ entrance/exit animations)
- Map block with OpenStreetMap & Google Maps support
- Block patterns (Hero, CTA, Features, FAQ)
- Comprehensive documentation (15,000+ lines across wiki and docs)
- Testing infrastructure (E2E + Unit + PHP)
- FSE compatibility (Twenty Twenty-Five)
- WordPress Abilities API integration

### 🔄 Current Focus
- Expanding pattern library
- Additional block variations
- Performance optimization
- Accessibility improvements
- WordPress.org submission preparation

### 📋 Near-Term (Next 3-6 Months)
- Additional interactive blocks (Testimonials, Pricing Tables)
- Advanced animation sequences
- More block patterns (20+ total)
- Video tutorials and documentation
- Enhanced Form Builder features (conditional logic, multi-step forms)
- Community feedback integration
- WordPress.org release

### 🔮 Future Phases
- Dynamic content blocks
- WooCommerce integration blocks
- Advanced theme builder features
- Template library
- Community pattern marketplace
- Form integrations (Mailchimp, ConvertKit, etc.)

## What Makes DesignSetGo Different?

### WordPress-First Philosophy
- **Native Integration**: Uses WordPress's built-in features (useBlockProps, useInnerBlocksProps, theme.json)
- **FSE Compatible**: Full Site Editing support out of the box
- **Theme Agnostic**: Works seamlessly with any modern WordPress theme
- **No jQuery**: Pure vanilla JavaScript for better performance

### Developer-Friendly
- **Clean Code**: < 300 lines per file, comprehensive JSDoc documentation
- **Best Practices**: Follows official WordPress block development patterns
- **Declarative Styling**: No DOM manipulation, React-based architecture
- **Security-First**: Input sanitization, output escaping, nonce verification

### Performance Optimized
- **Small Bundle Sizes**: < 10 KB per block (editor), < 5 KB (frontend)
- **CSS-Only Animations**: No JavaScript libraries required
- **Progressive Enhancement**: Core features work without JavaScript
- **Tree-Shaking**: Only loads what you use

### Accessibility First
- **WCAG 2.1 AA**: Meets accessibility standards
- **Keyboard Navigation**: Full keyboard support
- **Screen Reader Friendly**: Proper ARIA labels and announcements
- **Reduced Motion**: Respects `prefers-reduced-motion` preference

## Contributing

This project is 100% free and open source (GPL v2+). Contributions welcome!

**⚠️ Important**: This repository requires Pull Requests for all changes. External contributors must create an issue first and link it to their PR. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### 🚀 New Contributors - Start Here!

**Never contributed before?** We've created comprehensive guides to get you started:

1. **[Getting Started Guide](docs/GETTING-STARTED.md)** - Complete step-by-step setup walkthrough
   - Prerequisites and software installation
   - Local environment setup with wp-env
   - Making your first code change
   - Development tools and workflows
   - Troubleshooting common issues

2. **[Architecture Overview](docs/ARCHITECTURE.md)** - Understanding the codebase
   - Project structure and folder organization
   - How blocks work (from code to browser)
   - Build system and asset pipeline
   - Data flow and state management
   - Testing infrastructure

3. **[Contributing Guide](CONTRIBUTING.md)** - Complete contribution workflow
   - Development setup and prerequisites
   - Code standards and best practices
   - Testing requirements
   - Pull request process
   - Getting help and community support

**Looking for something specific?**
- **Setup help**: [docs/GETTING-STARTED.md](docs/GETTING-STARTED.md)
- **Code structure**: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Development patterns**: [.claude/CLAUDE.md](.claude/CLAUDE.md)
- **Testing guide**: [docs/TESTING.md](docs/TESTING.md)
- **Common issues**: [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)

### Quick Start for Developers

Already familiar with WordPress block development? Here's the quick version:

```bash
# 1. Fork and clone
git clone https://github.com/YOUR-USERNAME/designsetgo.git
cd designsetgo

# 2. Install and start
npm install
npx wp-env start  # Terminal 1
npm start         # Terminal 2

# 3. Access WordPress
# http://localhost:8888/wp-admin (admin/password)

# 4. Make changes, test, and submit PR!
```

**First contribution?** Look for issues labeled [`good-first-issue`](https://github.com/jnealey-godaddy/designsetgo/issues?q=is%3Aissue+is%3Aopen+label%3A%22good-first-issue%22).

### For AI-Assisted Development

**This plugin was built 100% with AI assistance (Claude Code).** We've documented everything:

- **[AI-Assisted Development Guide](docs/AI-ASSISTED-DEVELOPMENT.md)** ⭐ **Complete guide!**
  - How this entire plugin was built with AI
  - Available slash commands (/add-block, /lint, /test, /deploy, etc.)
  - Best practices and workflows
  - Tips, tricks, and real examples

- **[.claude/CLAUDE.md](.claude/CLAUDE.md)** - Development patterns for AI agents
  - Critical patterns AI should follow
  - WordPress best practices
  - Project-specific conventions

- **[WordPress Abilities API](docs/api/ABILITIES-API.md)** - AI-native programmatic access
  - REST API for AI agents
  - Block insertion and configuration
  - Automation workflows

**Using Claude Code, ChatGPT, or GitHub Copilot?** These guides will help you contribute effectively with AI assistance.

### Development Guidelines

**Core Principles:**
- ✅ Use WordPress defaults first (Block Supports, useBlockProps, theme.json)
- ✅ Keep files under 300 lines (extract components/utils)
- ✅ Test in both editor AND frontend
- ✅ Internationalize all strings (`__('Text', 'designsetgo')`)
- ✅ Follow WordPress coding standards

See [.claude/CLAUDE.md](.claude/CLAUDE.md) for complete patterns.

### Reporting Issues

**Found a bug or have a feature request?**

1. Check [existing issues](https://github.com/jnealey-godaddy/designsetgo/issues) first
2. If new, [create an issue](https://github.com/jnealey-godaddy/designsetgo/issues/new) with:
   - WordPress version, PHP version, browser
   - Steps to reproduce (for bugs)
   - Use case and expected behavior (for features)
   - Screenshots or videos (if helpful)

### Getting Help

**Questions?** We're here to help!

- **Setup/Development**: See [docs/GETTING-STARTED.md](docs/GETTING-STARTED.md)
- **Ask Questions**: [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)
- **Report Bugs**: [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues)

## License

GPL-2.0-or-later - 100% Free Forever

## Block Categories

Blocks are organized in the WordPress block inserter:

**DesignSetGo Collection** - All 53 blocks grouped together
- **Layout**: Row, Section, Grid
- **Forms**: Form Builder + 11 field types (Text, Email, Phone, URL, Date, Time, Number, Checkbox, Select, Textarea, Hidden)
- **Interactive**: Accordion, Tabs, Slider, Flip Card, Reveal, Scroll Accordion, Image Accordion, Counter Group, Progress Bar, Scroll Marquee, Scroll Slides, Sticky Sections, Comparison Table, Timeline, Modal, Modal Trigger
- **Dynamic Query**: Query, Pagination, Filter, Results, Group Header, No Results
- **Typography & Navigation**: Advanced Heading, Breadcrumbs, Table of Contents
- **Visual**: Icon, Icon Button, Icon List, Card, Pill, Divider, Countdown Timer, Blobs, Dynamic Image
- **Media & Location**: Fifty Fifty (50/50 split with edge-to-edge media), Map (OpenStreetMap & Google Maps)
- **WooCommerce**: Product Categories Grid, Product Showcase Hero

**WordPress Core Categories** - Blocks also appear in native categories
- **Design**: Layout containers and interactive blocks
- **Text**: Icon List, Card
- **Widgets**: Forms, Counters, Progress Bars, Map, Countdown Timer

**16 Block Extensions** - Available for ANY block (core, third-party, or DesignSetGo)
- **Block Animations** - 24+ entrance/exit effects with scroll triggers
- **Hover Effects** - Animated hover interactions (works on any block)
- **Sticky Header** - Sticky navigation on scroll
- **Clickable Groups** - Make containers clickable
- **Background Video** - YouTube or self-hosted video backgrounds
- **Responsive Visibility** - Hide/show by device
- **Conditional Visibility** - Show/hide by meta, taxonomy, login state, or query index
- **Dynamic Tags** - Bind text, links, and images to live data (post meta, ACF, and more)
- **Style Binding** - Map CSS properties to dynamic data sources
- **SVG Patterns** - 31 repeatable SVG background patterns
- **Max Width** - Content width constraints
- **Custom CSS** - Block-specific CSS
- **Grid Span** - Column/row spanning for grids
- **Grid Mobile Order** - Mobile stacking order for grid items
- **Reveal Control** - Advanced hover reveal settings
- **Text Alignment Inheritance** - Align with parent context
- **Draft Mode** - Control draft content visibility
- **Vertical Scroll Parallax** - Parallax scroll effects
- **Text Reveal** - Animated text reveal on scroll
- **Expanding Background** - Dynamic expanding backgrounds

**Patterns** - Pre-designed layouts
- Look for the "DesignSetGo" category in the pattern inserter
- Hero sections, CTAs, Features, FAQ layouts

📚 **[Complete Blocks Reference](https://github.com/jnealey-godaddy/designsetgo/wiki/Blocks-Reference)** - Detailed documentation for all 53 blocks

## 🤖 AI Integration (WordPress Abilities API)

DesignSetGo is the **first WordPress block plugin** to fully integrate with the WordPress 6.9 Abilities API, enabling AI agents and automation tools to programmatically interact with blocks.

### Available Abilities

| Category | Examples |
|----------|----------|
| **Info** | `list-abilities`, `list-blocks`, `list-extensions`, `get-post-blocks`, `find-blocks` |
| **Inserters** | `add-block` (generic top-level), `add-child-block` (nested), `add-accordion-item`, `add-tab`, `add-timeline-item` |
| **Configurators** | `update-block` (generic), `configure-shape-divider`, `configure-custom-css`, `batch-update`, `delete-block` |

#### Info Abilities
- `list-abilities` - Discover all registered abilities with schemas
- `list-blocks` - List all available blocks with metadata
- `list-extensions` - List all extensions with attribute schemas and applicable blocks
- `get-post-blocks` - Get blocks from a post with document-order indices
- `find-blocks` - Search for blocks across multiple posts

#### Inserter Abilities
- `add-block` - Insert any block at the top level of a post
- `add-child-block` - Insert any block as a child of an existing block
- `add-accordion-item` - Add an item to an existing accordion
- `add-tab` - Add a tab to an existing tabs block
- `add-timeline-item` - Add an item to an existing timeline

#### Configurator Abilities
- **Generic:** `update-block` - Update any block's attributes, including extension attributes
- **Block-Specific:** `configure-shape-divider` (position fan-out logic), `configure-custom-css` (CSS sanitization)
- **Extensions:** Use `list-extensions` to discover all extension attribute schemas (animation, parallax, responsive visibility, background video, clickable groups, etc.), then apply them via `update-block`
- **Operations:** `batch-update` (bulk modify blocks), `delete-block` (remove blocks)

### Quick Example

```bash
# Discover all available abilities
curl -X GET http://yoursite.com/wp-json/wp-abilities/v1/abilities/designsetgo/list-abilities/run \
  -u "username:password" \
  -d '{"input": {"category": "all"}}'

# Insert a block into a post
curl -X POST http://yoursite.com/wp-json/wp-abilities/v1/abilities/designsetgo/add-block/run \
  -u "username:password" \
  -H "Content-Type: application/json" \
  -d '{"input": {
    "post_id": 123,
    "block_name": "designsetgo/section",
    "attributes": {
      "contentWidth": "1140px"
    }
  }}'
```

### AI Agent Support

- ✅ **Claude** (via Model Context Protocol)
- ✅ **ChatGPT** (via REST API)
- ✅ **Custom Automation Tools**

**[📖 Full Abilities API Documentation →](docs/api/ABILITIES-API.md)**

---

## Support

### Getting Help
- **Documentation**: See [docs/](./docs/) folder for comprehensive guides
- **GitHub Issues**: [Report bugs or request features](https://github.com/jnealey-godaddy/designsetgo/issues)
- **Discussions**: Ask questions in [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)

### Useful Resources
- [Troubleshooting](docs/troubleshooting/TROUBLESHOOTING.md) - Common issues
- [Claude Code Learnings](.claude/CLAUDE.md) - Development insights

### WordPress.org
Coming soon - preparing for submission

## Credits

Built with ❤️ for the WordPress community by developers who believe in:
- WordPress-first development
- Open source collaboration
- Accessible, performant web experiences
- Clean, maintainable code

**Powered by**: React, WordPress Block Editor, Webpack, @wordpress/scripts, WordPress Abilities API

---

**License**: GPL-2.0-or-later | **Version**: 2.1.0 | **Requires WordPress**: 6.7+ | **Requires PHP**: 7.4+
