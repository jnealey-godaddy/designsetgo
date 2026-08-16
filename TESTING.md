# Testing Guide for DesignSetGo

Comprehensive testing documentation for the DesignSetGo WordPress plugin.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Test Types](#test-types)
  - [E2E Tests (Playwright)](#e2e-tests-playwright)
  - [Unit Tests (Jest)](#unit-tests-jest)
  - [PHP Tests (PHPUnit)](#php-tests-phpunit)
- [Running Tests](#running-tests)
- [Writing Tests](#writing-tests)
- [CI/CD Integration](#cicd-integration)
- [Troubleshooting](#troubleshooting)

---

## Overview

DesignSetGo uses a comprehensive testing strategy with three types of tests:

| Test Type | Tool       | Purpose                                                       | Coverage                                         |
| --------- | ---------- | ------------------------------------------------------------- | ------------------------------------------------ |
| **E2E**   | Playwright | Test complete user workflows in a real WordPress environment  | Block interactions, frontend rendering, admin UI |
| **Unit**  | Jest       | Test JavaScript functions and React components in isolation   | Block logic, utilities, data transformations     |
| **PHP**   | PHPUnit    | Test PHP classes, functions, and WordPress integration        | Server-side rendering, APIs, hooks               |

### Test Coverage Goals

- **E2E Tests**: Critical user paths (inserting blocks, editing, publishing)
- **Unit Tests**: 50%+ code coverage for JavaScript
- **PHP Tests**: Core plugin functionality and WordPress integration

---

## Quick Start

### 1. Install Dependencies

```bash
# Install Node.js dependencies
npm install

# Install Composer dependencies
composer install

# Install Playwright browsers
npm run test:install
```

### 2. Start WordPress Environment

```bash
# Start wp-env (required for E2E and PHP tests)
npm run wp-env:start
```

**WordPress will be available at:**

- Frontend: <http://localhost:8888>
- Admin: <http://localhost:8888/wp-admin>
- Credentials: `admin` / `password`

### 3. Run Tests

```bash
# Run all tests
npm run test:e2e       # E2E tests
npm run test:unit      # JavaScript unit tests
npm run test:php       # PHP unit tests (via Composer)

# Or run everything
npm run test:e2e && npm run test:unit && npm run test:php
```

---

## Test Types

### E2E Tests (Playwright)

End-to-end tests that simulate real user interactions in a WordPress environment.

**Location**: `tests/e2e/`
**Config**: [playwright.config.js](playwright.config.js)
**Documentation**: [tests/README.md](tests/README.md), [docs/testing/TESTING.md](docs/testing/TESTING.md)

#### E2E Commands

```bash
npm run test:e2e              # Run all E2E tests (headless)
npm run test:e2e:ui           # Interactive UI mode (recommended for dev)
npm run test:e2e:headed       # Run with browser visible
npm run test:e2e:debug        # Debug mode (step-by-step)
npm run test:e2e:chromium     # Chrome only
npm run test:e2e:firefox      # Firefox only
npm run test:e2e:webkit       # Safari only
npm run test:e2e:mobile       # Mobile devices only
npm run test:e2e:report       # View HTML test report
```

#### Example E2E Test

```javascript
const { test, expect } = require('@playwright/test');
const { createNewPost, insertBlock } = require('./helpers/wordpress');

test.describe('Accordion Block', () => {
  test('should insert and display accordion block', async ({ page }) => {
    await createNewPost(page, 'post');
    await insertBlock(page, 'designsetgo/accordion');

    const block = page.locator('[data-type="designsetgo/accordion"]');
    await expect(block).toBeVisible();
  });
});
```

---

### Unit Tests (Jest)

JavaScript unit tests for functions, utilities, and React components.

**Location**: `tests/unit/`
**Config**: [jest.config.js](jest.config.js)
**Setup**: [tests/unit/setup.js](tests/unit/setup.js)

#### Unit Test Commands

```bash
npm run test:unit              # Run unit tests
npm run test:unit -- --watch   # Watch mode
npm run test:unit -- --coverage # With coverage report
```

#### Example Unit Test

```javascript
describe('Block Utilities', () => {
  test('validates block name format', () => {
    const isValidBlockName = (name) => /^designsetgo\/[\w-]+$/.test(name);

    expect(isValidBlockName('designsetgo/accordion')).toBe(true);
    expect(isValidBlockName('invalid/name')).toBe(false);
  });
});
```

#### What to Unit Test

- Utility functions (pure functions)
- Data transformations
- Block attribute validation
- Component props and state
- Event handlers
- NOT WordPress APIs (mock them instead)

---

### PHP Tests (PHPUnit)

PHP unit tests for server-side functionality and WordPress integration.

**Location**: `tests/phpunit/`
**Config**: [phpunit.xml.dist](phpunit.xml.dist)
**Bootstrap**: [tests/phpunit/bootstrap.php](tests/phpunit/bootstrap.php)

#### PHP Test Commands

```bash
# Using Composer (recommended)
composer test

# Or run directly
vendor/bin/phpunit

# With coverage (requires Xdebug)
composer test -- --coverage-html coverage
```

#### Running PHPUnit Tests in wp-env

The recommended way to run PHPUnit tests is using wp-env:

```bash
# Start wp-env
npm run wp-env:start

# Run tests in wp-env container
npx wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit
```

#### Example PHP Test

```php
<?php
class Test_Accordion_Block extends WP_UnitTestCase {

  public function test_block_registered() {
    $registry = WP_Block_Type_Registry::get_instance();
    $this->assertTrue( $registry->is_registered( 'designsetgo/accordion' ) );
  }

  public function test_block_attributes() {
    $registry = WP_Block_Type_Registry::get_instance();
    $block = $registry->get_registered( 'designsetgo/accordion' );

    $this->assertArrayHasKey( 'allowMultipleOpen', $block->attributes );
    $this->assertFalse( $block->attributes['allowMultipleOpen']['default'] );
  }
}
```

#### What to PHP Test

- Block registration
- Server-side rendering (render.php)
- WordPress hooks and filters
- Plugin initialization
- Admin functionality
- REST API endpoints

---

## Running Tests

### Local Development

```bash
# Start WordPress environment
npm run wp-env:start

# Run tests in watch mode (recommended for dev)
npm run test:unit -- --watch
npm run test:e2e:ui

# Run specific test file
npm run test:unit tests/unit/block-attributes.test.js
npm run test:e2e tests/e2e/group-enhancements.spec.js

# Run tests with coverage
npm run test:unit -- --coverage
```

### Before Committing

```bash
# Build plugin
npm run build

# Run all tests
npm run test:e2e
npm run test:unit
composer test

# Run linters
npm run lint:js
npm run lint:css
composer run-script lint
```

### Debugging Tests

#### Debugging E2E Tests

```bash
# Debug mode (step-by-step)
npm run test:e2e:debug

# Headed mode (see browser)
npm run test:e2e:headed

# Pause test with debugger
await page.pause();
```

#### Debugging Unit Tests

```bash
# Debug in Chrome DevTools
node --inspect-brk node_modules/.bin/jest --runInBand

# Focus on single test
test.only('my test', () => { ... });

# Skip test
test.skip('broken test', () => { ... });
```

#### Debugging PHP Tests

```bash
# Run specific test
composer test -- --filter=test_block_registered

# Stop on failure
composer test -- --stop-on-failure

# Verbose output
composer test -- --verbose
```

---

## Writing Tests

### Test Naming Conventions

```javascript
// E2E Tests
test.describe('Block Name', () => {
  test('should do something specific', async ({ page }) => { ... });
});

// Unit Tests
describe('Function Name', () => {
  test('does something specific', () => { ... });
});

// PHP Tests
class Test_Block_Name extends WP_UnitTestCase {
  public function test_does_something_specific() { ... }
}
```

### Test Structure (AAA Pattern)

```javascript
test('should calculate sum correctly', () => {
  // Arrange - Set up test data
  const a = 5;
  const b = 3;

  // Act - Perform the action
  const result = add(a, b);

  // Assert - Verify the result
  expect(result).toBe(8);
});
```

### Common Assertions

```javascript
// Jest
expect(value).toBe(5);
expect(value).toEqual({ a: 1 });
expect(value).toBeTruthy();
expect(array).toContain('item');
expect(string).toMatch(/pattern/);

// PHPUnit
$this->assertEquals(5, $value);
$this->assertTrue($value);
$this->assertContains('item', $array);
$this->assertStringContainsString('text', $string);
```

### Best Practices

- Test one thing per test
- Use descriptive test names
- Keep tests independent
- Use beforeEach/afterEach for setup/teardown
- Mock external dependencies
- Don't test implementation details
- Don't skip tests (fix or remove them)
- Don't rely on test execution order

---

## CI/CD Integration

Tests run automatically via GitHub Actions on pushes and pull requests to `main`. The CI pipeline is defined in [.github/workflows/ci.yml](.github/workflows/ci.yml) and includes:

1. **Build** - Compiles the plugin (`npm ci && npm run build`)
2. **Lint & Unit Tests** - Runs JS/CSS linting and Jest unit tests
3. **PHP Lint & Analysis** - Runs PHPCS and PHPStan
4. **Security Audit** - `npm audit` and license checks
5. **E2E Tests** - Playwright browser tests against a wp-env instance
6. **WordPress Compatibility Matrix** - Tests across PHP 8.0-8.3 and WP 6.7-latest

---

## Troubleshooting

### E2E Test Issues

**Problem**: WordPress not starting

```bash
npm run wp-env:stop
npm run wp-env:clean
npm run wp-env:start
```

**Problem**: Authentication failed

```bash
rm -rf artifacts/storage-states
npx playwright test --project=setup
```

**Problem**: Tests timing out

- Increase timeout in [playwright.config.js](playwright.config.js)
- Check if wp-env is running: `npm run wp-env:start`

### Unit Test Issues

**Problem**: Module not found

```bash
npm install
npm run build
```

**Problem**: Tests failing after WordPress updates

```bash
npm install @wordpress/scripts@latest
npx jest --clearCache
```

### PHP Test Issues

**Problem**: WordPress test suite not found

```bash
# Use wp-env (recommended)
npm run wp-env:start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit

# Or install manually
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Problem**: Database connection errors

- Check MySQL is running
- Verify credentials in bootstrap.php
- Use wp-env instead for isolated environment

---

## Resources

### Documentation

- [Playwright Docs](https://playwright.dev)
- [Jest Docs](https://jestjs.io)
- [PHPUnit Docs](https://phpunit.de)
- [WordPress Testing](https://make.wordpress.org/core/handbook/testing/)
- [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

### Examples

- [tests/e2e/](tests/e2e/) - E2E test examples
- [tests/unit/](tests/unit/) - Unit test examples
- [tests/phpunit/](tests/phpunit/) - PHPUnit test examples

### Detailed Documentation

- [tests/README.md](tests/README.md) - Quick start guide
- [docs/testing/TESTING.md](docs/testing/TESTING.md) - Detailed E2E testing guide
- [docs/testing/TESTING-ABILITIES-API.md](docs/testing/TESTING-ABILITIES-API.md) - Abilities API testing

---

**Last Updated**: 2026-02-13
**Plugin Version**: 2.6.3
**WordPress Compatibility**: 6.7+
