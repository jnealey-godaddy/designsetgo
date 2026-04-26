# Conditional Visibility Extension

**Added in 2.1.0**

Controls whether a block is rendered based on per-item data when the block is inside a Dynamic Query. Rules evaluate against the queried item's post meta, taxonomy terms, loop index, or authentication state. Blocks without rules are always visible.

> **This is different from Responsive Visibility** (`RESPONSIVE-VISIBILITY.md`), which shows or hides blocks based on device screen size. Conditional Visibility acts at render time on the server and reflects the data of each loop item.

## How it works

Every block receives a `dsgoVisibility` attribute (type `object`, default `null`). The attribute holds a `{ operator, rules[] }` structure. On the frontend, a `render_block` filter compares the rules against the current item context pushed by Dynamic Query onto `$GLOBALS['designsetgo_parent_stack']`. Blocks that do not match are suppressed (rendered as an empty string). The editor canvas mirrors this evaluation inside query-item previews using the same rule logic compiled to JavaScript.

Blocks outside a Dynamic Query context are unaffected — visibility rules are only evaluated when the item-context stack is non-empty.

## Inspector controls

Open any block's settings, expand **Advanced**, then find the **Visibility** panel.

### Adding rules

Click **Add rule**. Each rule row has a **Rule Type** dropdown plus type-specific fields:

#### Post Meta
- **Meta Key** — the `_meta_key` to read from the current post
- **Condition** — `equals`, `does not equal`, `contains`, `is set`, `is not set`
- **Value** — the comparison string (hidden for `is set` / `is not set`)

#### Taxonomy
- **Taxonomy Slug** — e.g. `category`, `post_tag`, or a custom taxonomy
- **Condition** — `has term` / `does not have term`
- **Term Slug** — the term to check membership for

#### Item Index (0-based position in the query result)
- **Condition** — `is`, `is not`, `less than`, `greater than`
- **Index Value** — numeric position

#### Auth State
- **Visibility requires** — `Logged-in user` or `Logged-out visitor`

### Combining rules

When two or more rules are present a **Combine Rules** dropdown appears:

- **All rules must match (AND)** — block renders only if every rule passes
- **Any rule must match (OR)** — block renders if at least one rule passes

Remove any rule with its **Remove** button. Removing the last rule resets the attribute to `null` (always visible).

## Frontend behavior

Evaluation runs server-side inside `BlockVisibility::filter_render_block()`. The filter is only active while inside a `designsetgo_query_render_item()` call; top-level page blocks are never affected.

## Developer extension point

Add custom rule types via the `designsetgo_visibility_rule` filter:

```php
add_filter(
    'designsetgo_visibility_rule',
    function ( $match, $rule, $context ) {
        if ( $rule['type'] !== 'my-custom-type' ) {
            return $match; // pass through — let other handlers run
        }
        return my_custom_check( $rule, $context ); // return bool
    },
    10,
    3
);
```

The filter receives `($match, $rule, $context) → bool|null`. Return `null` to fall through to the default (false).

## Notes

- The extension does not apply to `core/freeform`, `core/missing`, or `core/template-part`.
- Rules are stored in block markup as a serialized JSON object inside the block comment delimiter.
- `has` and `not_has` operators are specific to taxonomy rules; `gt` and `lt` are specific to index rules. The full operator set is: `equals`, `not_equals`, `contains`, `gt`, `lt`, `empty`, `not_empty`, `has`, `not_has`.
