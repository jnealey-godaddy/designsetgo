# WP-CLI Reference

**Since**: 2.1.0 (filter-index commands) — see individual commands for version notes.

DesignSetGo registers its commands under the `dsgo` top-level namespace. Run any command as:

```
wp dsgo <subcommand> [options]
```

Commands are registered in `includes/` when WP-CLI is the active SAPI and require a WordPress installation with DesignSetGo active. Most write commands require the same capability as their REST equivalents (`manage_options` for index operations). The `--allow-root` flag is needed if you run WP-CLI as the root OS user.

---

## Query: Filter Index

**Source**: `includes/blocks/class-query-filter-index-cli.php`
**Since**: 2.1.0

Manages the persistent filter-index table (`{prefix}dsgo_query_filter_index`) used by the Dynamic Query block to compute per-option post counts for Query Filter blocks.

---

### `wp dsgo query index rebuild`

Drops and rebuilds the full filter index across all registered filters.

**Synopsis**

```
wp dsgo query index rebuild [--batch-size=<n>]
```

**Options**

| Flag | Default | Description |
|------|---------|-------------|
| `--batch-size=<n>` | `200` | Posts to process per batch. Minimum 50. |

**Example**

```bash
wp dsgo query index rebuild
wp dsgo query index rebuild --batch-size=500
```

**Example output**

```
Success: Indexed 1248 objects (3940 rows).
```

---

### `wp dsgo query index rebuild-filter`

Rebuilds the index for a single named filter only. Use this for a targeted refresh after adding or changing one filter's registration without paying the cost of a full rebuild.

**Synopsis**

```
wp dsgo query index rebuild-filter <filter_key> [--batch-size=<n>]
```

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `<filter_key>` | Yes | The registered filter key to rebuild (e.g. `category`, `post_tag`, `price`). |

**Options**

| Flag | Default | Description |
|------|---------|-------------|
| `--batch-size=<n>` | `200` | Posts to process per batch. |

**Example**

```bash
wp dsgo query index rebuild-filter category
wp dsgo query index rebuild-filter price --batch-size=500
```

**Example output**

```
Success: Rebuilt filter "category" (1248 objects, 2310 rows).
```

If the filter key is not registered the command exits with a warning (no error status):

```
Warning: Filter "unknown" is not registered — nothing to do.
```

---

### `wp dsgo query index status`

Displays the current health and state of the filter index as a formatted table.

**Synopsis**

```
wp dsgo query index status
```

**Example**

```bash
wp dsgo query index status
```

**Example output**

```
+--------------------+-----------------------+
| last_rebuilt_at    | in_progress           |
+--------------------+-----------------------+
| 2026-04-24 09:15:00 UTC | no            |
+--------------------+-----------------------+
```

If the index has never been built, `last_rebuilt_at` shows `never`.

---

### `wp dsgo query index drop`

> **Destructive.** Drops the filter index table and clears all associated options. The table is recreated automatically on the next `admin_init` (which fires `maybe_upgrade()`), but all indexed data is permanently lost until a rebuild is run.

**Synopsis**

```
wp dsgo query index drop [--yes]
```

**Options**

| Flag | Description |
|------|-------------|
| `--yes` | Skip the confirmation prompt. Use with caution in scripts. |

**Example**

```bash
wp dsgo query index drop
wp dsgo query index drop --yes
```

**Example output**

```
Success: Filter index table dropped.
```

---

## See Also

- [`docs/api/REST-API-REFERENCE.md`](REST-API-REFERENCE.md) — REST endpoints, including the filter-index rebuild routes (`/designsetgo/v1/query/filter-rebuild`, `/filter-status`).
- [`docs/api/MARKDOWN-CONTENT-NEGOTIATION.md`](MARKDOWN-CONTENT-NEGOTIATION.md) — Per-URL Markdown delivery via `Accept: text/markdown`.
- [`docs/blocks/QUERY.md`](../blocks/QUERY.md) — Dynamic Query block: sources, filters, group-by, and template reference.
