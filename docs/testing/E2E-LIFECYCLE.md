# Pattern E2E lifecycle regressions

The September 2026 pattern sweep exposed two test-harness failures that initially
looked like navigation and CORS problems.

## Pattern insertion and collected promises

`insertPatternBySlug()` used one asynchronous `page.evaluate()` for both fetching
the pattern registry and parsing/inserting the chosen pattern. The browser could
report `Protocol error (Runtime.callFunctionOn): Promise was collected` even
though the blocks had already appeared in the editor. Playwright rewrote that
protocol error as `Execution context was destroyed, most likely because of a
navigation.` Browser lifecycle instrumentation confirmed that no document or
execution context was destroyed at that point.

Tracing changed the timing and concealed the failure. Repeating the same checks
with tracing disabled reproduced it. The helper now resolves the pattern content
first, then parses and inserts it in a separate, synchronous evaluation. It does
not retry insertion: repeating an operation that already inserted content could
duplicate blocks.

`tests/e2e/pattern-insertion.spec.js` covers initial and cached lookups, the exact
number of inserted blocks, unique client IDs, validation, and missing-pattern
errors without content changes.

## Frontend review and pending editor requests

The Mobile Safari failure involved the editor's `/wp/v2/pages?context=edit`
request. A trace showed that request returning HTTP 200, followed by later
requests to the same endpoint being canceled when the test navigated the editor
tab to the published frontend. The original failure surfaced an access-control
error during this transition.

The pattern sweep now uses `openFrontendPage()` to review the published frontend
in a separate page in the same browser context. Editor requests can finish.
Errors from both pages remain assertions, with editor/frontend labels for
triage. The frontend listener is attached before navigation so startup errors
are captured.

`tests/e2e/editor-lifecycle.spec.js` holds a real browser request pending while
opening the frontend. Same-tab navigation failed the check because the request
was canceled; separate-page navigation lets it complete with HTTP 200. A second
check verifies that frontend JavaScript exceptions remain observable.

## Focused checks

With wp-env running:

```bash
npx playwright test tests/e2e/pattern-insertion.spec.js tests/e2e/editor-lifecycle.spec.js --workers=2 --trace=off
```

Use `--trace=off` when reproducing the collected-promise failure. Traces remain
useful for examining request URLs, status codes, and navigation timing, but a
passing traced run alone does not rule out that race.
