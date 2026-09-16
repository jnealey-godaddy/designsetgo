# Testing Quick Start

Quick guide to run the DesignSetGo test suite.

## 1. Install Dependencies

```bash
npm install
composer install
npm run test:install   # Playwright browsers
```

## 2. Start WordPress

```bash
npm run wp-env:start
npm run build
```

**WordPress will be available at:**

- Frontend: <http://localhost:9451>
- Admin: <http://localhost:9451/wp-admin>
- Credentials: `admin` / `password`

## 3. Run Tests

```bash
# JavaScript unit tests (Jest)
npm run test:unit

# E2E browser tests (Playwright)
npm run test:e2e

# PHP unit tests (PHPUnit)
npm run test:php
```

E2E setup temporarily sets the test administrator’s editor language to English
(`en_US`) because the suite uses English controls. Teardown restores the previous
user locale. The site language is unchanged; map migration also has an explicit
German-editor regression check. This setup requires access to the wp-env CLI.

---

## Interactive Development

```bash
# Jest watch mode
npm run test:unit -- --watch

# Playwright UI mode (recommended)
npm run test:e2e:ui

# Playwright with visible browser
npm run test:e2e:headed
```

---

## Run a Specific Test

```bash
# Single unit test file
npm run test:unit tests/unit/block-attributes.test.js

# Single E2E test file
npm run test:e2e tests/e2e/group-enhancements.spec.js

# Single PHP test method
composer test -- --filter=test_block_registered
```

---

## Before Committing

```bash
npm run build
npm run test:unit
npm run test:e2e
npm run test:php
npm run lint:js
npm run lint:css
npm run lint:php
```

---

## Troubleshooting

### WordPress not starting

```bash
npm run wp-env:stop
npm run wp-env:clean
npm run wp-env:start
```

### Playwright browsers not installed

```bash
npm run test:install
```

### E2E authentication issues

```bash
rm -rf artifacts/storage-states
npx playwright test --project=setup
```

---

## Full Documentation

- [TESTING.md](TESTING.md) - Comprehensive testing guide
- [tests/README.md](tests/README.md) - Test structure and commands
- [docs/testing/TESTING.md](docs/testing/TESTING.md) - Detailed E2E testing guide
- [docs/testing/TESTING-ABILITIES-API.md](docs/testing/TESTING-ABILITIES-API.md) - Abilities API testing
