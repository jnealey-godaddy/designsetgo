# Turnstile verification can succeed without a server secret

**Status:** Fixed in [#610](https://github.com/jnealey-godaddy/designsetgo/pull/610) · **Severity:** Medium · **Scan issue ID:** `e41cd356-b6c6-4bca-ac42-c332554afa51`

## Finding

A form author can enable Cloudflare Turnstile while the site has a site key but no server secret. The form then shows the Turnstile widget, but the public submission handler accepts any nonempty token without checking it with Cloudflare. So an unauthenticated caller can submit the form without completing the challenge.

What still applies is the submission-timing check and field validation. The honeypot and the rate limits apply only when they are enabled in the plugin settings ([handler](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/includes/blocks/forms/class-form-handler.php#L316-L342)).

How it happens:

- **Editor.** The editor [lets the author enable the setting](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/src/blocks/form-builder/edit.js#L921-L943) whatever the key configuration. Once enabled, it shows a [fixed reminder to configure the keys](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/src/blocks/form-builder/edit.js#L944-L972), but it never checks whether they are.
- **Frontend.** The widget [initializes when a site key exists](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/src/blocks/form-builder/view.js#L106-L136).
- **Submission.** The handler [requires a nonempty token and calls verification](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/includes/blocks/forms/class-form-handler.php#L344-L355).
- **Verification.** `verify_turnstile()` [returns success when the secret is empty](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/includes/blocks/forms/class-form-security.php#L177-L185). It also [returns success on an HTTP error or a non-JSON response](https://github.com/jnealey-godaddy/designsetgo/blob/bb90099082cc2ae057387af1d7c92870ab6febb5/includes/blocks/forms/class-form-security.php#L206-L222). Only an explicit Cloudflare failure is rejected.

The finding is conditional on Turnstile being enabled with incomplete server configuration. It was confirmed by source review at commit `bb90099082cc2ae057387af1d7c92870ab6febb5`, and the links above are pinned to it. No live site configuration or runtime exploit was tested.

## Recommended fix

When a published form requires Turnstile, treat a missing server secret, an HTTP error, and an invalid verification response as failed verification. Accept a token only when Cloudflare explicitly reports success.

In the editor, prevent or clearly flag enabling Turnstile until both keys are configured. The frontend should render the widget only when the server can verify it. Either way, the visible widget should not imply protection the server cannot enforce.

### What failing closed costs

Accepting submissions when verification can't complete is deliberate today; the code calls it graceful degradation, so as not to punish users. Failing closed trades that availability for enforcement, and the fix and its release notes should say so:

- **Outages.** While Cloudflare is unreachable, or slower than the 3-second verification timeout, every form with Turnstile enabled rejects every submission.
- **Existing forms.** Forms that already have Turnstile enabled without a server secret accept submissions today, unprotected. They stop accepting them as soon as the fix ships. Site owners need to be told, for example through a warning in the editor, an admin notice, or the changelog, before they lose submissions they can't see.

## Verifying the fix

Cover the change with server-side tests for:

- a missing secret;
- a forged token;
- an HTTP error;
- malformed verification JSON;
- a successful Cloudflare response.

Also confirm that a form with Turnstile disabled keeps the existing public submission flow.

## Resolution

Fixed in [#610](https://github.com/jnealey-godaddy/designsetgo/pull/610), which follows the approach proposed in [#611](https://github.com/jnealey-godaddy/designsetgo/pull/611).

- **Fail closed.** `verify_turnstile()` accepts a token only when Cloudflare reports `success: true`. A missing secret, an HTTP error, a timeout, a 5xx and an unreadable reply all fail.
- **One configuration check.** `Form_Security::is_turnstile_configured()` requires both keys, whitespace trimmed. The submission handler and both localizations (frontend and editor) use it.
- **Honest rejection.** A Turnstile form with incomplete keys is turned away before any Cloudflare call, with a 503 and "This form can't accept submissions right now". It no longer asks the visitor to complete a challenge that isn't there.
- **Only Turnstile forms verify.** A token sent to a form without Turnstile is ignored, so those forms never depend on Cloudflare.
- **Editor.** Turnstile can't be switched on until both keys are set. A form already switched on with incomplete keys shows an error notice, and can still be switched off.
- **Frontend.** The widget renders only when the server can verify its token.
- **Outage trade-off.** Rejection during a Cloudflare outage is the default. `designsetgo_turnstile_accept_when_unavailable` lets a site accept submissions while Cloudflare is unreachable. It never covers a rejected token or a missing secret.
- **Release notes.** The changelog tells site owners that forms with Turnstile on and incomplete keys will stop accepting submissions.
- **Tests.** Server-side coverage for a missing secret, a forged token, HTTP and malformed failures, a 5xx, success, the outage filter, a misconfigured form and a stray token on a form without Turnstile. Jest covers the editor toggle and the frontend widget gate.
