# DesignSetGo Testing

Comprehensive testing suite for the DesignSetGo WordPress plugin.

For setup and run commands, see [TESTING-QUICKSTART.md](../TESTING-QUICKSTART.md) (fast reference) or [TESTING.md](../TESTING.md) (comprehensive guide). This file documents the layout of this directory.

## Test Types

- **E2E** (Playwright): End-to-end browser tests
- **Unit** (Jest): JavaScript unit tests
- **PHP** (PHPUnit): PHP unit tests
- **Integration**: block-level integration tests

## Test Structure

```
tests/
├── e2e/
│   ├── *.setup.js                 # Login/authentication setup projects
│   ├── *.teardown.js              # Post-test cleanup
│   ├── *.spec.js                  # E2E specs (one per block/feature)
│   └── helpers/                   # Shared helper functions
├── unit/                          # Jest unit tests (blocks/, components/, extensions/, hooks/, utils/, tools/)
├── integration/                   # Jest integration tests (blocks/)
├── js/                            # JS test fixtures/utilities shared across suites
└── phpunit/                       # PHP unit tests (admin/, blocks/, extensions/, helpers/)
```

## Available Commands

### E2E Tests (Playwright)

| Command | Description |
|---------|-------------|
| `npm run test:e2e` | Run all E2E tests (headless) |
| `npm run test:e2e:ui` | Run tests in interactive UI mode |
| `npm run test:e2e:headed` | Run tests with browser visible |
| `npm run test:e2e:debug` | Debug tests step-by-step |
| `npm run test:e2e:chromium` | Run tests in Chrome only |
| `npm run test:e2e:firefox` | Run tests in Firefox only |
| `npm run test:e2e:webkit` | Run tests in Safari only |
| `npm run test:e2e:mobile` | Run tests on mobile devices |
| `npm run test:e2e:report` | View HTML test report |

### Unit Tests (Jest)

| Command | Description |
|---------|-------------|
| `npm run test:unit` | Run JavaScript unit tests |
| `npm run test:unit -- --watch` | Run tests in watch mode |
| `npm run test:unit -- --coverage` | Run tests with coverage report |

### PHP Tests (PHPUnit)

| Command | Description |
|---------|-------------|
| `npm run test:php` | Run PHP unit tests |
| `composer test` | Alternative: Run PHP tests via Composer |

## Writing Tests

Example test:

```javascript
const { test, expect } = require('@playwright/test');
const { createNewPost, insertBlock } = require('./helpers/wordpress');

test('should add responsive grid columns', async ({ page }) => {
  // Create new post
  await createNewPost(page, 'post');

  // Insert Group block
  await insertBlock(page, 'core/group');

  // Your test assertions here
  await expect(page.locator('.wp-block-group')).toBeVisible();
});
```

## MCP Integration

Claude Code can interact with Playwright tests through MCP (Model Context Protocol).

Configuration is in [.claude/mcp.json](../.claude/mcp.json).

## Documentation

- **Quick Start**: [TESTING-QUICKSTART.md](../TESTING-QUICKSTART.md)
- **Comprehensive Guide**: [TESTING.md](../TESTING.md)
- **E2E Details**: [docs/testing/TESTING.md](../docs/testing/TESTING.md)

## Troubleshooting

### WordPress Not Starting

```bash
npm run wp-env:stop
npm run wp-env:clean
npm run wp-env:start
```

### Browsers Not Installed

```bash
npm run test:install
```

### Tests Failing

1. Ensure WordPress is running: http://localhost:9451
2. Check credentials: `admin` / `password`
3. Clear artifacts: `rm -rf artifacts`
4. Re-run setup: `npx playwright test --project=setup`

## Resources

- [Playwright Documentation](https://playwright.dev)
- [DesignSetGo Testing Guide](../docs/testing/TESTING.md)
- [WordPress E2E Testing](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils/)
