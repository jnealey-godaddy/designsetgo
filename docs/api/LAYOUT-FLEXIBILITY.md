# Editable layout composition

Section, Row and Grid retain their native WordPress base controls. Optional
`dsgoLayout` overrides add responsive composition without residual custom CSS.
Installed `list-blocks` discovery exposes `generation.layoutOverrides` with its
version, applicable properties, value types, enum choices and root/inner targets.

In **Settings**, choose **Viewport**, then **Property group**: Layout and spacing,
Sizing, or Placement and layering. Supported direct children also expose sizing
and placement. Empty fields inherit; each authored field can be reset. Invalid
text remains a local draft while the last valid value keeps rendering.
Prefer native controls for desktop spacing and alignment. Optional desktop
overrides take priority over those controls; clear an override to restore the
native value.

```json
{
  "dsgoLayout": {
    "desktop": { "gap": "24px", "width": "clamp(20rem, 60vw, 70rem)" },
    "tablet": { "direction": "column" },
    "mobile": { "gap": "12px", "paddingLeft": "8px" }
  }
}
```

Desktop applies at every width. Tablet applies at 1024px and below; mobile at
767px and below. Omitted properties inherit from larger viewports, then native
base styles. Clearing mobile gap above restores 24px. These breakpoints match
the existing Grid and visibility behavior. Do not mix this contract with newer
WordPress `style["@mobile"]` or `style["@tablet"]` for the same property.

| Capability | Fields | Target |
| --- | --- | --- |
| Fluid sizing | width, minWidth, maxWidth, height, minHeight, maxHeight, aspectRatio | Block root |
| Layering/clipping | position, top, right, bottom, left, zIndex, overflow | Block root |
| Padding | paddingTop/Right/Bottom/Left | Container root |
| Inner layout | justifyContent, alignItems, gap, rowGap, columnGap, contentWidth | Immediate inner wrapper |
| Section/Row flow | direction, wrap | Immediate inner wrapper |
| Grid composition | gridTemplateRows, gridTemplateAreas | Immediate inner wrapper |
| Child placement | flexBasis/Grow/Shrink, alignSelf, gridColumn, gridRow, gridArea | Child root |

`update-block` synchronizes the saved root class and immediate Grid inner styles
when these fields change, whether targeting by name, client ID or document index.
`batch-update` uses the same validation and synchronization. Invalid layout
patches refuse the entire write; sourced child markup and unrelated attributes
are preserved. Clear `dsgoLayout` with `{}` and a column template with `""` to
restore inherited layout or the native column fallback. Column templates keep
their existing automatic-repeat and named-line support; the bounded row-track
grammar described below applies to `dsgoLayout.gridTemplateRows` only.
The specialized `add-tab` route refuses these fields in its legacy child builder;
use `add-child-block` or `serialize-blocks` for composed layout children.

Grid columns remain `columnTemplate`, `tabletColumnTemplate` and
`mobileColumnTemplate`. Pair those with matrices of the same column count:

```json
{
  "columnTemplate": "minmax(0, 2fr) minmax(0, 1fr)",
  "mobileColumnTemplate": "minmax(0, 1fr)",
  "dsgoLayout": {
    "desktop": {
      "gridTemplateRows": "160px 120px",
      "gridTemplateAreas": "\"hero aside\" \"copy aside\""
    },
    "mobile": {
      "gridTemplateRows": "160px 120px 120px",
      "gridTemplateAreas": "\"hero\" \"aside\" \"copy\""
    }
  }
}
```

Set `dsgoLayout.desktop.gridArea` on children to `hero`, `aside` or `copy`.
Named regions must be rectangular and rows equally wide; `.` means an empty
cell. Row/column expressions accept `auto`, signed nonzero line numbers, names,
`span N`, or two entries separated by `/`. Align Rows owns child placement while
enabled. Visual placement preserves DOM reading order; choose a source order
that makes sense for keyboard and screen-reader users.

Lengths accept supported CSS units, preset `var()` values, and bounded `calc()`,
`min()`, `max()` and `clamp()` expressions. Row tracks also accept integer-count
`repeat()`, `minmax()` and `fit-content()`. Automatic/nested repeats are outside
this row-track subset. Values use ASCII syntax, at most 512 characters and eight
nested parentheses. Numeric grow/shrink/layer values are finite, bounded to 9999
and at most four decimal places; layer order is an integer. Negative scalar
sizing/padding/gaps are refused. `none` resets maximum sizing; `auto`, `normal`,
`static` and `visible` reset their applicable properties. Unknown fields/devices,
malformed expressions, URLs and declaration injection refuse the entire
generation batch before writing any blocks.

Supplemental roots: core Group, Paragraph, Heading, Image, Buttons, Button,
Separator and Spacer; DesignSetGo Icon Button, Card and Dynamic Image. Wrapper
sizing applies to these roots; use native appearance controls for the visible
element. Wrapperless dynamic blocks are excluded.

Absent/empty overrides leave saved HTML unchanged. Valid settings add a stable
class shared by PHP serialization and Gutenberg save. Frontend rules print
outside block markup, deduplicated in the head with a footer flush for late
renders. Static query templates are precollected even when the initial result
is empty, so later refreshes retain their styles. Standalone REST fragments need
the host page's matching stylesheet; fragments do not contain style siblings.
Editor rules use WordPress's public `useStyleOverride` hook on the plugin's
supported WordPress 6.7+ baseline, including iframe canvases and previews.

The reference fixture covers split hero, editorial areas, asymmetric mosaic,
overlapping image/copy, fluid feature row, mobile area reordering, clipped frame
and an inset content column. Acceptance compares native and independent HTML
geometry at desktop/tablet/mobile, then verifies inspector edits, inheritance,
save and reload. These exercise the supported composition vocabulary; they do
not establish parity with arbitrary HTML/CSS or replace the caller's compiler.
