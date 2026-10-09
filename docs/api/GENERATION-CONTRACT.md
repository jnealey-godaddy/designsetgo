# Native generation contract

DesignSetGo exposes the installed block schema, known styling targets and stored
markup serialization for native block generators. These APIs support both a
structured-first authoring tree and an HTML/CSS compiler that produces the same
native tree. HTML parsing, CSS adaptation and generation orchestration belong in
the caller.

For responsive composition, fluid sizing, Grid areas/placement and layering,
see [Layout flexibility](LAYOUT-FLEXIBILITY.md). Discovery advertises the
applicable `dsgoLayout` properties for each installed block.

## Discover the installed contract

Call `designsetgo/get-design-context` for resolved theme settings, styles, fonts
and registered block styles. Call `designsetgo/list-blocks` with `detail: "full"`
and the block names you intend to use for complete installed attribute schemas.
Both responses include `generationContract`:

```json
{
  "version": 1,
  "pluginVersion": "<installed plugin version>",
  "wordpressVersion": "<installed WordPress version>",
  "breakpoints": { "mobileMax": 767, "tabletMax": 1024 }
}
```

Cache discovery against the installed versions and contract version. Schema
attributes include defaults; omitted values in saved comments do not mean that
an attribute has no value. Read the installed schema before emitting attributes.

Each block in `list-blocks` also reports:

- `selectors`: WordPress's registered block selectors, including a visual root
  when the visible element differs from the positioning wrapper.
- `blockStyles`: installed style names, labels and default flags. Set
  `className: "is-style-<name>"` to select a registered style.
- `generation.serialization.supported`: whether the installed PHP serializers
  can produce stored markup. Unsupported static blocks include a `reason`.
- `generation.serialization.mode`: `static` means stored save markup; `dynamic`
  means a comment-only stored form. Hybrid blocks that render in PHP but still
  save a wrapper report `static`. This field describes storage, not rendering.
- `generation.targets` and `generation.styleOwnership`, where explicitly
  declared. Missing targets mean there is no published target contract for that
  block. They do not imply that every internal element is a supported API.

Targets are block-scoped selectors. For instance, Grid's layout lives on
`.wp-block-designsetgo-grid > .dsgo-grid__inner`, while its background and border
live on the outer wrapper. Icon Button's visual root is the inner button/link;
`justification` belongs to its outer wrapper. Scope residual CSS to an individual
block with the custom CSS `selector` placeholder.

Prefer native WordPress supports for color, gradient, typography, spacing,
border and shadow. For Grid, `style.spacing.blockGap` owns gaps and takes
precedence over `rowGap` and `columnGap`, including the block's default gap.
Supply the owning native attribute instead of competing CSS declarations.

## Serialize without creating a draft

Invoke `designsetgo/serialize-blocks` with the same definitions as `add-blocks`:

```json
{
  "blocks": [{
    "block_name": "designsetgo/grid",
    "attributes": {
      "columnTemplate": "minmax(0, 3fr) minmax(0, 2fr)",
      "tabletColumnTemplate": "minmax(0, 2fr) minmax(0, 1fr)",
      "mobileColumnTemplate": "minmax(0, 1fr)",
      "style": { "spacing": { "blockGap": "2rem" } }
    },
    "inner_blocks": [{
      "name": "core/paragraph",
      "attributes": { "content": "Editable <strong>native</strong> content" }
    }]
  }]
}
```

Top-level definitions use `block_name` and `inner_blocks`; nested definitions use
`name` and `innerBlocks` (nested `inner_blocks` is also accepted). Successful
responses contain `success: true`, `content` (stored block markup) and
`block_count` (top-level count). There is a maximum of 50 top-level definitions.
Supply stable block-specific IDs when repeatable markup is required; some
serializers generate missing IDs.

This ability requires `edit_posts`. It uses the insertion path's preparation,
sanitization, serializers and structural checks, without post writes or dynamic
render callbacks. Semantic refusals return `success: false`, `error_code` and
`message`, with `block_index` and nested paths when applicable. Refusals contain
no partial `content`. Transport-level schema/authentication errors may instead
be WordPress errors. Structural checks do not execute JavaScript `save()` or
prove visual equivalence: validate representative trees in the installed editor.

The result is suitable for the normal WordPress content write API. Preview,
revision handling and conditional publication remain the caller's responsibility.

## Editable responsive Grid tracks

`tabletColumnTemplate` and `mobileColumnTemplate` override their device column
counts. Empty values retain existing count behavior. The tablet range is
768–1024px and mobile is at most 767px; desktop `columnTemplate` applies above
1024px. Controls are available in Grid's Settings inspector and can be reset.

Templates are stored as optional variables on the inner grid. Existing content
with empty templates keeps the same saved markup. Nested grids resolve their
own values, and responsive spans clamp to rendered tracks without changing the
authored span. Do not append markup repair CSS for these layouts.

## Residual custom CSS

Use `designsetgo/configure-custom-css` for decoration that native supports cannot
express. It writes the registered `dsgoCustomCSS` string and synchronizes the
saved selector class. Dynamic blocks receive the selector at render time. The
ability requires `edit_posts`, `edit_css` and access to the target post; excluded
blocks such as `core/html` and `core/code` are refused before mutation.

```json
{
  "post_id": 123,
  "block_name": "designsetgo/grid",
  "css": {
    "desktop": "selector::before { transform: rotate(-3deg); }",
    "tablet": "selector::before { transform: rotate(-2deg); }",
    "mobile": "selector::before { transform: none; }"
  }
}
```

Desktop rules are the base at every width. Tablet rules apply up to 1024px and
mobile rules up to 767px, in that order. Supplied rules replace that section;
omitted sections are preserved separately for each matching block. Empty strings
clear a supplied section. `enabled: false` clears all CSS; enabling again does
not restore cleared rules. `update_all` uses the normal first/all matching rules.

Responsive sections carry reserved `dsgo-css` boundary comments in the single
editor field. Keep those comments for later per-breakpoint updates. If an author
removes them, those rules become ordinary base CSS and a desktop update replaces
them. No separate `dsgoCustomCss*` attributes are written.
