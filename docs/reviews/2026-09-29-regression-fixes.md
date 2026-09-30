# Existing-user regression fixes

Scope: REG01–04 from the release-to-release audit, based on main `d67376370848981397635634468818580e45e7e8` (including the markdown-it dependency update merged during verification). No version bump, deployment or saved block markup change.

## Repairs

- Custom form patterns are enforced in PHP only when their syntax has a supported HTML-v/PCRE interpretation. ASCII digit/word classes, ECMAScript whitespace and dot semantics are mapped explicitly. JavaScript set operations, unsupported escapes/groups and invalid-v classes remain browser-only, as custom patterns were before the new backend validation. Required/type/length/range/step checks are unchanged. This deliberately does not promise server enforcement of every custom JavaScript expression; custom patterns are not an authorization boundary.
- Dynamic icons use a deduplicated serial queue of at most 100 names per REST request. Omitted/non-string/failed answers remain retryable on a later DOM scan after the existing 30-second cooldown; only explicit empty strings mean unknown names. Successful batches remain available when another fails.
- Modal content keeps its historical white background when unset, with a dark default close icon. Explicit authored colors still win. Themes can opt into `--wp--custom--designsetgo--modal--background`; the editor and frontend now share that exact token and fallback.

## Verification

Failing tests preceded the repairs. Focused form coverage passed 57 tests / 89 assertions; icon coverage passed 11 tests; four compiled Modal surface tests verify both canvases and the default close control. Independent review found no actionable issue in this scope. A bounded independent PHP/ECMAScript-v comparison checked 8,246 combinations with no browser-accepted value incorrectly rejected; this is not an exhaustive equivalence claim.

- Full JavaScript suite: 201 suites, 4,274 tests passed.
- Full WordPress PHP suite: 1,962 tests, 7,515 assertions passed.
- Production build, JS/CSS/PHP lint, PHPStan and diff whitespace checks passed. The final JS suite/build/lint were repeated after a clean install of current main’s exact dependency lockfile. The build retains its existing large-editor-bundle size warning.
- Final rebuilt form/icon/Modal runtime files were byte-identical to the browser-tested ZIP after rebasing current main.
- Built ZIP installed into disposable WordPress 7.1.2 / PHP 8.3.35 on localhost:9452 using Twenty Twenty-Five. Browser tests used the packaged assets rather than a source mount.
- Chrome: 162 distinct icons inserted after page load all rendered, with actual REST batches of 100 and 62 and no console errors/warnings.
- Chrome: legacy modal with explicitly black text and dark page palette variables rendered a white surface and black close icon. A real Global Styles token (`#eef2ff`) produced RGB(238,242,255) in both editor and frontend. At a measured 390px viewport, the modal and close control fit the viewport. Temporary theme styles and viewport overrides were restored.
- Chrome: browser-valid values for a valid nested set intersection (`[[A-Z]&&[^AEIOU]]+`) and browser-ignored invalid legacy patterns matched packaged PHP acceptance. Portable `[A-Z]+` validation also passed. Browser/server results were compared directly; this fixture did not send email.
- Chrome editor: a genuine 2.8.2 compound fixture (Accordion, Slider, Comparison Table, Progress Bar, Timeline, Counter, Countdown, Form Builder and Icon Button) opened with no invalid-content/recovery UI or console errors. WordPress emitted its existing Global Styles iframe-enqueue warning. The legacy Modal fixture also opened without recovery UI.

## Limits

These checks establish the four repairs, not a guarantee of zero impact across every theme, third-party extension, browser or authored pattern. Broader intentional theme/typography, countdown timezone, Turnstile and Query cache behavior from the release audit still apply. The release must retain its existing cache-purge and upgrade guidance. Production was not updated, and the full cross-browser E2E/version matrix is left to CI and release validation.
