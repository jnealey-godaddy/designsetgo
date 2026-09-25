# Codex issue notes

## Codex-2026-09-25-extract-zip

- Dependabot alerts #128 and #145 both concern the development-only `extract-zip` package. As of 2026-09-25, npm has no patched release.
- `@wordpress/env` 10.35.0 brings in `extract-zip` 1.7.0. `@wordpress/scripts` 31.6.0 brings in two copies of 2.0.1 through Puppeteer and Lighthouse.
- Updating to `@wordpress/env` 11.16.0 and `@wordpress/scripts` 35.0.0 removes every `extract-zip` entry from the npm lockfile. The Lighthouse toolchain now requires Node 22.19 or newer, so CI and deployment use Node 24.
- `@wordpress/scripts` 35 uses ESLint 10 and ignores this repo's `.eslintrc.js`. Keep ESLint 8 and the WordPress ESLint plugin 24 as explicit lint dependencies until the project migrates its rules to flat config.
