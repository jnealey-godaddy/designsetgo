# Draft Mode Test Suite

Comprehensive test suite for the Draft Mode feature added in version 1.4.0.

## Test Coverage

### PHP Unit Tests (PHPUnit)

Located in `tests/phpunit/`:

#### 1. `draft-mode-test.php` - Core Functionality
Tests the `Draft_Mode` class core functionality:
- ✅ Creating drafts from published pages
- ✅ Publishing drafts (merging to original)
- ✅ Discarding drafts
- ✅ Draft with content overrides
- ✅ Draft metadata management
- ✅ Error handling (invalid post, wrong post type, wrong status)
- ✅ Preventing duplicate drafts
- ✅ Meta cleanup on deletion
- ✅ Action hooks (draft_created, draft_published, draft_discarded)

**Total Tests: 15**

#### 2. `draft-mode-rest-test.php` - REST API Endpoints
Tests the `Draft_Mode_REST` class API functionality:
- ✅ REST route registration
- ✅ Create draft endpoint with/without overrides
- ✅ Publish draft endpoint
- ✅ Discard draft endpoint
- ✅ Get status endpoint (all states)
- ✅ Permission checks (`publish_pages`, `edit_pages`)
- ✅ Parameter validation
- ✅ Multi-user capability testing

**Total Tests: 14**

#### 3. `draft-mode-admin-test.php` - Admin UI
Tests the `Draft_Mode_Admin` class UI functionality:
- ✅ Row actions (Create Draft, Edit Draft, View Live)
- ✅ Draft status column rendering
- ✅ Permission checks for UI elements
- ✅ Page-only filtering
- ✅ Inline styles generation
- ✅ Inline script generation

**Total Tests: 10**

### JavaScript Unit Tests (Jest)

Located in `tests/unit/`:

#### 4. `draft-mode-panel.test.js` - Sidebar Panel Component

Tests the `DraftModePanel` React component:

- ✅ Post type filtering (non-page types)
- ✅ Draft mode enabled/disabled state
- ✅ Published page without draft (create button)
- ✅ Published page with draft (notice and edit button)
- ✅ Draft page editing (publish and discard buttons)

Total Tests: 7

#### 5. `draft-mode-controls.test.js` - Editor Controls Component

Tests the `DraftModeControls` React component:

- ✅ Post type filtering (non-page types)
- ✅ Draft mode enabled/disabled state
- ✅ Edit Draft button when draft exists
- ✅ Save Draft button visibility (with/without edits)
- ✅ Saving state indicators
- ✅ Draft editing banner with view live link
- ✅ Accessibility (aria-labels for external links)
- ✅ Save draft action on button click

Total Tests: 9

## Running Tests

### PHP Tests

#### Using wp-env (Recommended)

```bash
# Start wp-env
npm run wp-env:start

# Run all PHPUnit tests
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit

# Run only draft mode tests
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit --filter Draft_Mode
```

#### Using Local WordPress Test Suite

```bash
# Install WordPress test suite (one-time setup)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
vendor/bin/phpunit

# Run only draft mode tests
vendor/bin/phpunit --filter Draft_Mode
```

### JavaScript Tests

```bash
# Run all Jest tests
npm test

# Run only draft mode tests
npm test -- draft-mode

# Run with coverage
npm test -- --coverage draft-mode

# Watch mode during development
npm test -- --watch draft-mode
```

## Test Data

### Test Users
- **Administrator**: Full permissions
- **Editor**: Has `publish_pages` and `edit_pages` capabilities
- **Subscriber**: No page permissions (for negative testing)

### Test Scenarios

1. **Happy Path**: Create draft → Edit → Publish
2. **Discard Path**: Create draft → Edit → Discard
3. **Conflict Handling**: Attempt to create draft when one exists
4. **Permission Denial**: Subscriber attempting draft operations
5. **Error Recovery**: API failures, invalid data
6. **Subdirectory Install**: URL construction for WordPress in subdirectory

## Coverage Goals

- **PHP**: >90% code coverage for Draft_Mode classes
- **JavaScript**: >85% code coverage for React components

## Continuous Integration

These tests are designed to run in CI/CD pipelines:

```yaml
# Example GitHub Actions workflow snippet
- name: Setup Node
  uses: actions/setup-node@v3

- name: Run JavaScript Tests
  run: npm test -- --coverage

- name: Run PHP Tests
  run: npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit
```

## Test Maintenance

When modifying draft mode functionality:

1. ✅ Update relevant test cases
2. ✅ Add new tests for new features
3. ✅ Ensure all tests pass before committing
4. ✅ Verify coverage hasn't decreased

## Known Limitations

- E2E tests not yet implemented (future enhancement)
- Some edge cases around concurrent draft editing not fully tested
- Browser-specific behavior testing requires manual QA

## Future Enhancements

- [ ] Add E2E tests with Playwright
- [ ] Add performance benchmarks
- [ ] Add visual regression tests
- [ ] Add load testing for REST endpoints

---

**Created**: 2026-01-31
**Version**: 1.0
**Feature Version**: 1.4.0
