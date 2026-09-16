# DesignSetGo Claude Skills

Skills available under `.claude/skills/`, invoked as `/skill-name` or picked up
automatically when their description matches the task.

## DesignSetGo-Specific Skills

Maintained in this repo. Source of truth for conventions is `.claude/CLAUDE.md`
— fix a skill here if it drifts from that file or from the actual code.

**Block & pattern authoring**
- `add-block` — scaffold a new Gutenberg block
- `add-extension` — scaffold a filter-based extension on a core block
- `add-variation` — register a block variation (checks the variation-vs-new-block rule first)
- `add-pattern` — add a static pattern under `patterns/{category}/`

**Quality & audits** (run in a forked subagent)
- `refactor` — clean up anti-patterns in existing block/extension code
- `review-extension` — review a block extension against project conventions
- `review-pr` — review a PR/branch against DesignSetGo standards
- `block-supports-audit` — find custom controls that could become Block Supports
- `security-audit` — security-focused audit (PHP + JS)
- `performance-audit` — bundle size, asset loading, render performance audit
- `plugin-review` — comprehensive plugin audit (architecture, a11y, i18n, etc.)
- `check-compat` — WordPress/Gutenberg version compatibility check

**Workflow**
- `deploy` — release-to-WordPress.org checklist (tag push triggers the deploy workflow)
- `i18n-update` — translation file workflow (POT/PO/MO + JS JSON catalogs)
- `quick-fix` — diagnose common build/style/validation issues

**General reasoning**
- `chain-of-verification` — fact-check generated claims (APIs, versions, config) before answering

## Upstream WordPress Agent-Skills

`wp-abilities-api`, `wp-abilities-audit`, `wp-abilities-verify`,
`wp-block-development`, `wp-block-themes`, `wp-interactivity-api`,
`wp-patterns`, `wp-performance`, `wp-phpstan`, `wp-playground`,
`wp-plugin-development`, `wp-plugin-directory-guidelines`,
`wp-project-triage`, `wp-rest-api`, `wp-wpcli-and-ops`, `wpds`, and
`blueprint` are synced from an upstream WordPress agent-skills source, not
authored for this repo.

**Do not hand-edit these.** If one is wrong or outdated, re-sync it from
upstream instead of patching it in place — a manual edit will just be
overwritten (or silently diverge) on the next sync.

## Process Skills Come From the Global `superpowers` Plugin

Skills like brainstorming, planning, TDD, systematic debugging, worktree
management, and code-review handoffs are not local to this repo — they're
provided by the global `superpowers` Claude Code plugin and referenced as
`superpowers:<name>` (e.g. `superpowers:systematic-debugging`). This repo
used to vendor local copies of several of these; they were removed in favor
of the plugin versions. If a DSGo skill needs to point at one, use the
`superpowers:` prefix, not a bare name.

## Maintenance

- New DSGo skill: add a directory with a `SKILL.md` (frontmatter `name` +
  a one-line "Use when…" `description`), then list it above.
- Changed conventions: update `.claude/CLAUDE.md` first, then fix any skill
  that documents the old behavior.
