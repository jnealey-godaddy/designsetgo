# DesignSetGo Claude Skills

The skills in this repo are the ones a contributor needs to follow DesignSetGo's conventions. `.claude/CLAUDE.md` is the source of truth; if a skill drifts from it or from the code, fix the skill.

| Skill | Use it to |
|-------|-----------|
| `/add-block` | Add a block, or a variation of an existing block (decides which first) |
| `/add-extension` | Add a filter-based extension to core or DSGo blocks |
| `/add-pattern` | Add a static pattern under `patterns/{category}/` |
| `/review-pr` | Check a branch against DSGo standards before opening a PR |

## Everything else is installed per developer, not vendored here

**WordPress knowledge** (block development, Interactivity API, REST API, Abilities API, PHPStan, WP-CLI, Playground): install the official skills from [WordPress/agent-skills](https://github.com/WordPress/agent-skills), for example:

```bash
npx skills add WordPress/agent-skills --skill wp-block-development wp-interactivity-api wp-rest-api --global
```

**Process skills** (brainstorming, planning, TDD, debugging, code review): use a general-purpose plugin such as `superpowers`, or Claude Code's built-in `/code-review` and `/security-review`.

Keeping these out of the repo means they stay current upstream and contributors aren't forced onto a vendored copy.

## Adding a skill

Only add one here if it encodes something specific to DesignSetGo that `.claude/CLAUDE.md` can't express as a rule. Give it a directory with a `SKILL.md` (frontmatter `name` and a one-line "Use when…" `description`) and list it in the table above.
