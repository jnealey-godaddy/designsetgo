---
name: add-pattern
description: Use when creating a new block pattern (hero, pricing table, FAQ, homepage section, etc.) for the DesignSetGo pattern library under patterns/{category}/
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(ls *), Bash(npx wp-env *)
---

Create a new block pattern for the DesignSetGo pattern library. This is a static markup pattern (a PHP file returning a WordPress block-comment string), not a new block — for a new block or block variation, use `/add-block` instead (it also covers variations).

## Before You Start

Read the two reference files in this skill's `references/` directory:

- **`references/markup-conventions.md`** — how DSGo pattern markup is written: the block-comment syntax, preset-vs-inline attribute formats, the color/spacing/font-size preset tables, the required `metadata` object on the root block, and the PHP-string escaping rules (content is a single-quoted PHP string).
- **`references/block-catalog.md`** — every `designsetgo/*` block usable in a pattern, with its key attributes, `supports`, and (for layout containers) exact HTML structure. Check here before hand-writing markup for a block you haven't used in a pattern before — getting the HTML structure wrong produces a pattern that looks fine in the inserter preview but is invalid once inserted.

For generic WordPress pattern guidance not specific to this plugin (accessibility in patterns, i18n of pattern strings, general block-markup mechanics, pattern categories vs. collections), see the `wp-patterns` skill from [WordPress/agent-skills](https://github.com/WordPress/agent-skills). This skill only covers what's specific to DesignSetGo's pattern library.

## File Location & Shape

Every pattern is a PHP file at `patterns/{category}/{slug}.php`. `{category}` **must** be one of the directory names `includes/patterns/class-loader.php` allow-lists (`Loader::ALLOWED_CATEGORIES`) — a pattern dropped into any other directory name is silently never scanned or registered, with no error:

```
homepage, header, footer, hero, features, pricing, testimonials,
team, cta, content, faq, modal, gallery, contact, services, headings
```

File shape (see any existing file in `patterns/hero/` as a working example):

```php
<?php
/**
 * Title: Human-Readable Title
 * Slug: designsetgo/{category}/{slug}
 * Categories: dsgo-{category}
 * Description: One sentence describing the pattern.
 * Keywords: comma, separated, search, terms
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'         => __( 'Human-Readable Title', 'designsetgo' ),
	'categories'    => array( 'dsgo-{category}' ),
	'viewportWidth' => 1200,
	'content'       => '<!-- wp:designsetgo/section {...} -->...<!-- /wp:designsetgo/section -->',
);
```

The registered pattern slug becomes `designsetgo/{category}/{filename-without-.php}` — the loader derives it from the directory + filename, so the PHP docblock's `Slug:` comment is documentation only (not read back), but keep it accurate.

The root block (always `designsetgo/section`) needs a `metadata` object for the pattern editor:

```json
"metadata": {
  "categories": ["dsgo-{category}"],
  "patternName": "designsetgo/{category}/{slug}",
  "name": "Human-Readable Title"
}
```

## Placeholder Images

Use `{{dsgo:placeholder-{type}}}` tokens for images, not hardcoded external URLs — `designsetgo_replace_pattern_placeholders()` (`includes/patterns/placeholder-images.php`) replaces these with local placeholder image URLs at registration time, so patterns don't depend on a third-party image host at runtime. Available types: `avatar`, `landscape`, `landscape-wide`, `portrait`, `square`, `gallery`, `logo` (see `designsetgo_get_placeholder_map()` for the current list — check it before inventing a new type).

## Testing

Pattern data is cached per-category in transients (`dsgo_pattern_data_{category}`, keyed by plugin version + a hash of the category directory's file mtimes) unless `WP_DEBUG` is on, in which case caching is skipped entirely. While iterating:

- Run with `WP_DEBUG` enabled (the default wp-env config) so edits are picked up on the next editor load with no manual cache clear.
- If testing against a build without `WP_DEBUG`, clear the cache by bumping the plugin version, deleting the transient directly, or calling `DesignSetGo\Patterns\Loader::clear_cache()`.
- Insert the pattern in the editor and confirm: it renders identically to the source markup (no "invalid content" warning — patterns aren't validated the way blocks are, but malformed block comments will still show broken blocks), the placeholder images resolve, and it appears under the correct `DesignSetGo: {Category}` inserter category.
