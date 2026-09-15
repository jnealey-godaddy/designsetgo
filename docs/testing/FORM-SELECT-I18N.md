# Select placeholder regression checks

Validated on 2026-09-14 with the production build of DesignSetGo 2.7.5,
WordPress 6.9, PHP 8.3.33, and Chromium.

## Automated coverage

- `tests/unit/form-select-placeholder.test.js` checks translated and missing
  editor catalogs, custom text, clearing, both reset controls, and serialization.
  It also resets with the bundled French JSON catalog, then reopens with the
  German catalog and verifies that the default was never saved as authored text.
- `tests/unit/form-select-migration.test.js` parses historical static markup with
  the real WordPress deprecation pipeline. Localized text, English text, and an
  absent placeholder migrate to dynamic blocks without validation issues. The
  label, options, help text, and required state survive serialization/reopening.
- `tests/phpunit/blocks/form-select-field/render-test.php` uses `do_blocks()` and
  the registered built block, including WordPress schema-default preparation.
  It covers all nine bundled MO catalogs, missing translations, explicit empty
  and custom placeholders, differing site/user locales, and WordPress loading
  the JSON catalog for the registered editor script.

The migration test exposed an additional edge case: static markup with no
placeholder option migrated to `undefined`, enabling the new translated default.
The static deprecation now migrates that state to an explicit empty string.

## Rerun

```bash
npm run build
npm run test:unit -- --runInBand
npm run lint:js
npm run wp-env:start
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/designsetgo composer install
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/designsetgo \
  vendor/bin/phpunit --test-suffix=-test.php tests/phpunit/blocks/form-select-field
vendor/bin/phpcs --standard=phpcs.xml --warning-severity=0 \
  tests/phpunit/blocks/form-select-field/render-test.php
```

For this run, `WP_ENV_HOME=/tmp/designsetgo-step4-wp-env` was used for wp-env's
files. The complete Jest suite passed **159 suites / 3,456 tests**; the focused
WordPress suite passed **14 tests / 45 assertions**. The production build and
JavaScript/PHP lint checks passed. All seven Issue 4 strings were also verified
in every PO/MO catalog, with applicable source/built JavaScript JSON checked.

## Browser validation

Using the local test site, install the `fr_FR` and `de_DE` core language packs,
set the site language to French, and set the test editor user's language to
German. Insert a select inside a Form Builder block.

1. Supply a custom placeholder. Use the inspector menu's placeholder reset,
   save the draft, and reopen it. Repeat with **Reset all**. Both must display
   the German default in the editor while leaving `placeholder` absent from
   the saved block comment.
2. Preview that draft on the frontend while logged in as the same German user.
   The placeholder must use French. Normal WordPress content typography turns
   the default's double hyphens into em dashes on this theme's frontend.
3. Enter `Texte <choix> & café`, save, reopen, and preview. The displayed text
   must remain intact, with HTML-sensitive characters escaped in the markup.
4. Clear the placeholder, save, reopen, and preview. The select must still
   render, with no placeholder option.
5. Parse the three historical fixtures through the built editor's
   `wp.blocks.parse()`, serialize, and parse again. Verify valid blocks, no
   validation issues, preserved attributes, and no static `<select>` markup.

All five browser checks passed in Chromium. Validation used temporary drafts;
no release was published.
