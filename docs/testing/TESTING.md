# Testing Guide - DesignSetGo

This document covers the end-to-end (E2E) testing setup for the DesignSetGo WordPress plugin using Playwright.

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Test Structure](#test-structure)
- [Writing Tests](#writing-tests)
- [MCP Integration](#mcp-integration)
- [Troubleshooting](#troubleshooting)

## Overview

DesignSetGo uses [Playwright](https://playwright.dev) for end-to-end testing. Playwright provides:

- **Cross-browser testing**: Chromium, Firefox, WebKit (Safari)
- **Mobile testing**: Emulated mobile devices
- **Auto-wait**: Automatically waits for elements to be ready
- **Network control**: Mock APIs, intercept requests
- **Screenshots & videos**: Automatic debugging artifacts
- **Parallel execution**: Fast test runs

### What We Test

- **Block enhancements**: Responsive grid, visibility, overlays
- **Editor interactions**: Block insertion, settings, controls
- **Frontend rendering**: Verify correct HTML/CSS output
- **Responsive behavior**: Different viewport sizes
- **WordPress integration**: Compatibility with core blocks

## Installation

### 1. Install Dependencies

```bash
npm install
```

This installs Playwright and all dependencies listed in `package.json`.

### 2. Install Playwright Browsers

```bash
npm run test:install
```

This downloads Chromium, Firefox, and WebKit browsers (~400MB).

### 3. Start WordPress Environment

```bash
npm run wp-env:start
```

This starts a local WordPress instance using wp-env (Docker).

**WordPress will be available at:**
- Frontend: http://localhost:9451
- Admin: http://localhost:9451/wp-admin
- Credentials: `admin` / `password`

## Running Tests

### Basic Commands

```bash
# Run all tests (headless)
npm run test:e2e

# Run with UI mode (interactive)
npm run test:e2e:ui

# Run with browser visible
npm run test:e2e:headed

# Debug mode (step through tests)
npm run test:e2e:debug
```

### Browser-Specific Tests

```bash
# Chrome only
npm run test:e2e:chromium

# Firefox only
npm run test:e2e:firefox

# Safari only
npm run test:e2e:webkit

# Mobile devices
npm run test:e2e:mobile
```

### View Test Reports

```bash
# After test run, open HTML report
npm run test:e2e:report
```

### Running Specific Tests

```bash
# Run a single test file
npx playwright test tests/e2e/group-enhancements.spec.js

# Run tests matching a pattern
npx playwright test --grep "responsive grid"

# Run a specific test by line number
npx playwright test tests/e2e/group-enhancements.spec.js:15
```

## Test Structure

### Directory Layout

```
tests/
├── e2e/
│   ├── auth.setup.js              ← Authentication setup
│   ├── cleanup.teardown.js        ← Cleanup after tests
│   ├── group-enhancements.spec.js ← Group block tests
│   └── helpers/
│       └── wordpress.js           ← Helper functions
├── unit/                          ← Unit tests (Jest)
└── phpunit/                       ← PHP unit tests
```

### Test Files

- **`*.setup.js`**: Setup scripts (authentication, database seeding)
- **`*.spec.js`**: Test files
- **`*.teardown.js`**: Cleanup scripts
- **`helpers/`**: Shared utility functions

### Configuration

- **[playwright.config.js](../../playwright.config.js)**: Main Playwright configuration
- **[.claude/mcp.json](../../.claude/mcp.json)**: MCP server configuration for Claude integration

## Writing Tests

### Basic Test Structure

```javascript
const { test, expect } = require('@playwright/test');
const { createNewPost, insertBlock, selectBlock } = require('./helpers/wordpress');

test.describe('My Feature', () => {
  test('should do something', async ({ page }) => {
    // Arrange
    await createNewPost(page, 'post');
    await insertBlock(page, 'core/group');

    // Act
    await selectBlock(page, 'core/group');
    await page.click('button[aria-label="Settings"]');

    // Assert
    await expect(page.locator('.my-control')).toBeVisible();
  });
});
```

### Available Helper Functions

#### `createNewPost(page, postType)`
Creates a new post or page in the block editor.

```javascript
await createNewPost(page, 'post'); // or 'page'
```

#### `insertBlock(page, blockName)`
Inserts a block by name.

```javascript
await insertBlock(page, 'core/group');
await insertBlock(page, 'core/paragraph');
```

#### `selectBlock(page, blockType, index)`
Selects a block by type and optional index.

```javascript
await selectBlock(page, 'core/group', 0);
```

#### `openBlockSettings(page)`
Opens the block settings sidebar.

```javascript
await openBlockSettings(page);
```

#### `savePost(page)`
Saves the current post.

```javascript
await savePost(page);
```

#### `publishPost(page)`
Publishes the current post.

```javascript
await publishPost(page);
```

#### `blockHasClass(page, blockType, className, index)`
Checks if a block has a specific CSS class.

```javascript
const hasClass = await blockHasClass(page, 'core/group', 'dsgo-grid-cols-3');
expect(hasClass).toBeTruthy();
```

### Testing Responsive Behavior

```javascript
test('should hide on mobile', async ({ page, context }) => {
  // Desktop test
  await page.goto(postUrl);
  await expect(page.locator('.my-element')).toBeVisible();

  // Mobile test
  const mobilePage = await context.newPage();
  await mobilePage.setViewportSize({ width: 375, height: 667 });
  await mobilePage.goto(postUrl);
  await expect(mobilePage.locator('.my-element')).toBeHidden();
});
```

### Testing Frontend Output

```javascript
test('should render correctly on frontend', async ({ page }) => {
  // Create and publish post
  await createNewPost(page);
  await insertBlock(page, 'core/group');
  await publishPost(page);

  // Get frontend URL
  const previewUrl = await page.locator('.edit-post-header-preview__button-external').getAttribute('href');

  // Visit frontend
  await page.goto(previewUrl);

  // Verify frontend rendering
  const group = page.locator('.wp-block-group.dsgo-grid-cols-3');
  await expect(group).toBeVisible();
});
```

### Debugging Tips

#### 1. Use `page.pause()`
Pauses test execution and opens Playwright Inspector.

```javascript
await page.pause(); // Test stops here
```

#### 2. Take Screenshots
```javascript
await page.screenshot({ path: 'debug.png' });
```

#### 3. Console Logs
```javascript
page.on('console', msg => console.log(msg.text()));
```

#### 4. Slow Down Execution
```javascript
test.use({ slowMo: 1000 }); // Delay 1 second between actions
```

## MCP Integration

MCP (Model Context Protocol) allows Claude Code to interact with Playwright for automated testing and debugging.

### Configuration

The MCP server is configured in [.claude/mcp.json](../../.claude/mcp.json):

```json
{
  "mcpServers": {
    "playwright": {
      "command": "npx",
      "args": ["-y", "@executeautomation/playwright-mcp-server"],
      "env": {
        "PLAYWRIGHT_CONFIG": "playwright.config.js",
        "PLAYWRIGHT_BASE_URL": "http://localhost:9451"
      }
    }
  }
}
```

### Using MCP with Claude

Once configured, Claude can:
- Run Playwright tests
- Generate test code
- Debug test failures
- Analyze test results
- Suggest test improvements

**Example prompts:**
- "Run the group enhancements tests"
- "Debug the failing responsive grid test"
- "Create a test for the new overlay feature"
- "Show me the test coverage report"

## Troubleshooting

### WordPress Not Starting

**Problem**: `webServer` timeout error.

**Solution**:
```bash
# Stop and clean wp-env
npm run wp-env:stop
npm run wp-env:clean

# Start fresh
npm run wp-env:start
```

### Authentication Failing

**Problem**: Tests fail at login step.

**Solution**:
1. Check WordPress is running: http://localhost:9451
2. Verify credentials in `tests/e2e/auth.setup.js`
3. Delete old storage state: `rm -rf artifacts/storage-states`
4. Re-run setup: `npx playwright test --project=setup`

### Browsers Not Installed

**Problem**: `browserType.launch: Executable doesn't exist`

**Solution**:
```bash
npm run test:install
```

### Port Conflicts

**Problem**: Port 9451 already in use.

**Solution**:
```bash
# Option 1: Stop wp-env
npm run wp-env:stop

# Option 2: Use different port
export WP_BASE_URL=http://localhost:9452
npm run wp-env:start
npm run test:e2e
```

### Tests Timing Out

**Problem**: `Test timeout of 60000ms exceeded`

**Solution**:
1. Increase timeout in `playwright.config.js`:
   ```javascript
   timeout: 120000, // 2 minutes
   ```
2. Use `test.slow()` for specific tests:
   ```javascript
   test.slow(); // 3x timeout for this test
   ```

### Flaky Tests

**Problem**: Tests pass/fail randomly.

**Solution**:
1. Add explicit waits:
   ```javascript
   await page.waitForSelector('.my-element');
   await page.waitForLoadState('networkidle');
   ```
2. Use auto-wait assertions:
   ```javascript
   await expect(page.locator('.my-element')).toBeVisible();
   ```
3. Enable retries in config:
   ```javascript
   retries: 2,
   ```

### Debug Mode Not Working

**Problem**: `--debug` flag doesn't stop at breakpoints.

**Solution**:
1. Use `await page.pause()` in test code
2. Run with UI mode instead: `npm run test:e2e:ui`

## Best Practices

### 1. Use Page Objects
Create reusable page objects for common workflows:

```javascript
class EditorPage {
  constructor(page) {
    this.page = page;
  }

  async openSettings() {
    await this.page.click('button[aria-label="Settings"]');
  }

  async setGridColumns(desktop, tablet, mobile) {
    await this.openSettings();
    // ... implementation
  }
}
```

### 2. Keep Tests Independent
Each test should be able to run in isolation:

```javascript
test.beforeEach(async ({ page }) => {
  await createNewPost(page);
});
```

### 3. Use Descriptive Names
```javascript
// ✅ Good
test('should hide group on mobile when responsive visibility is enabled', ...)

// ❌ Bad
test('test1', ...)
```

### 4. Test User Flows, Not Implementation
```javascript
// ✅ Good - Tests user behavior
test('user can create a 3-column grid', async ({ page }) => {
  await insertBlock(page, 'core/group');
  await setGridLayout(page, 3);
  await expect(page.locator('.grid-cols-3')).toBeVisible();
});

// ❌ Bad - Tests implementation details
test('dsgGridColumns attribute is set', ...)
```

### 5. Clean Up After Tests
```javascript
test.afterEach(async ({ page }) => {
  // Delete test posts, reset state, etc.
});
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: 24
      - run: npm ci
      - run: npm run test:install
      - run: npm run test:e2e
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: playwright-report/
```

## Resources

- [Playwright Documentation](https://playwright.dev)
- [WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [wp-env Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [Best Practices for E2E Testing](https://playwright.dev/docs/best-practices)

## Getting Help

- **Playwright Issues**: https://github.com/microsoft/playwright/issues
- **WordPress Testing**: https://make.wordpress.org/core/handbook/testing/
- **DesignSetGo Issues**: [Project issue tracker]

---

**Last Updated**: 2025-10-24
**Playwright Version**: 1.56.1
**WordPress Compatibility**: 6.4+
