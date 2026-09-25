# Turnstile verification can succeed without a server secret

**Status:** Open · **Severity:** Medium · **Scan issue ID:** `e41cd356-b6c6-4bca-ac42-c332554afa51`

## Finding

A form author can enable Cloudflare Turnstile while the site has a site key but no server secret. The form then displays the Turnstile widget, but the public submission handler accepts any nonempty token without verifying it with Cloudflare. An unauthenticated caller can submit that form without completing the challenge. The honeypot, field validation, and rate limits still apply.

The editor [allows the setting independently of key configuration](../../src/blocks/form-builder/edit.js#L921-L944). The frontend [initializes the widget when a site key exists](../../src/blocks/form-builder/view.js#L106-L136). On submission, the handler [requires a nonempty token and calls verification](../../includes/blocks/forms/class-form-handler.php#L344-L355), while `verify_turnstile()` [returns success when the secret is empty](../../includes/blocks/forms/class-form-security.php#L177-L185). The same verifier currently returns success on an HTTP error or an invalid Cloudflare response; those failure paths should be covered by the fix as well.

This finding is conditional on Turnstile being enabled with incomplete server configuration. It was confirmed by source review at commit `bb90099082cc2ae057387af1d7c92870ab6febb5`; no live site configuration or runtime exploit was tested.

## Recommended fix

When a published form requires Turnstile, treat a missing server secret, an HTTP error, and an invalid verification response as failed verification. Accept a submitted token only when Cloudflare explicitly reports success. The editor should also prevent or clearly flag enabling Turnstile until both keys are configured, so the visible widget does not imply protection that the server cannot enforce.

Verify the change with server-side tests for a missing secret, a forged token, an HTTP error, malformed verification JSON, and a successful Cloudflare response. Confirm that a form with Turnstile disabled continues to use the existing public submission flow.
