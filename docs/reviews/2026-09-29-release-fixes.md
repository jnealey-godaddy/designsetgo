# September 29 release blocker fixes

This PR repairs public Markdown visibility, client-visible Query templates and missing runtime pattern media. The release version remains 2.8.2; no tag or deployment is part of this work.

## Verification

- JavaScript: 199 suites, 4,262 tests pass, including real ZIP and rsync packaging regressions.
- WordPress PHPUnit: 1,925 tests, 7,477 assertions pass on WordPress 7.1.2 / PHP 8.3.
- Build, full-worktree JS/CSS/PHP lint, PHPStan (196 files, 2G worker memory) and license checks pass.
- The leak, expiry renewal, viewer replay and password-cookie regressions were observed failing before their fixes.
- Plugin Check against the packaged installation: no errors; only the existing readme 2.8.0 upgrade-notice length warning.
- Installed the actual ZIP in a separate localhost:9452 WordPress environment, using Twenty Twenty-Five 1.5 and the core editor.
- Browser: anonymous and signed-in Query load-more both append the next item. Hidden page/query markers are absent for anonymous visitors and present for the authenticated viewer. Decoded refresh data contains only v/queryId/sourcePostId/ref. All seven packaged images and an inserted Gallery Grid pattern load.
- Editor: no invalid-block warnings or JavaScript errors with correctly serialized Query markup; Query preview renders its first item and the Gallery Grid images. Existing global-styles iframe and useSelect stability warnings remain.
- Mobile: at 390px, document width is 390px with no horizontal overflow; anonymous private markers remain absent. Temporary browser emulation was restored.
- Anonymous HTTP checks verify REST Markdown and Accept:text/markdown output omit private markers, with Content-Type:text/markdown and Vary:Accept. Direct REST navigation was blocked by the browser extension; these requests were verified through HTTP and PHPUnit instead.
- Full automated cross-browser E2E and a repeated Gutenberg/theme matrix were not run for this server/packaging patch. The original preflight exercised the broader main-branch editor/theme set.

## Deployment and cache behavior

1. Purge full-page/CDN caches when installing the fix. Legacy plaintext Query references are rejected. Purge externally cached Markdown responses as well.
2. Load WordPress/admin after installing the update to run the version-independent repair on the first WordPress request. It removes old per-post caches and plugin-owned Markdown files, including physical llms.txt and llms-full.txt, even if the feature is disabled. It preserves unowned root files. Existing static links can return 404 until safe files are regenerated through the LLMS settings; dynamic endpoints remain available.
3. If filesystem cleanup fails, the repair stays pending, warns administrators and retries. PHP readers bypass stale static exports. Web-server-served files cannot be blocked by PHP; restore write access or remove those owned legacy files manually, then regenerate.
4. Query definitions use stable, viewer-bound server-side transients. Their 30-day inactivity lifetime renews on each server render/refresh. Keep page-cache lifetimes below 30 days. If source transients are evicted or cleared, purge corresponding page caches so visitors receive fresh references.
5. Previously issued references retain their saved template until the record expires or is deleted; active refresh renews that record. This snapshot behavior existed in the old signed transport. For immediate revocation after template/visibility edits, invalidate the dsgo_query_source_* transients as well as page caches. Current source-post publication/password/capability gates are still rechecked.

## Review

Independent spec and code-quality review found no remaining must-fix issue after closing the password-cookie export loophole. Query storage renewals add a timeout/cache-expiry update on uncached renders, but reuse the same key rather than growing storage per visit. Production changes remain scoped to public exports, Query refresh transport and release packaging.
