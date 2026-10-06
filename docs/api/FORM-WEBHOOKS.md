# Form Webhooks

A Form Builder block with a **Webhook URL** sends every submission to that URL as a JSON `POST`. This page is the contract for whoever writes the receiver.

Available since 2.10.0.

## Request

```http
POST /your/endpoint HTTP/1.1
Content-Type: application/json
User-Agent: DesignSetGo/2.10.0; https://example.com/
X-DSGo-Event: form.submitted
X-DSGo-Delivery: 6f1c1d3e-8a59-4d4b-9a7e-2f0c5b1e9d42
X-DSGo-Timestamp: 1791281700
X-DSGo-Signature: sha256=5d2c…
```

| Header | Meaning |
| --- | --- |
| `X-DSGo-Event` | Always `form.submitted` for now. |
| `X-DSGo-Delivery` | UUID of this delivery. **The same on every automatic retry**, so you can use it to drop duplicates. A manual **Resend** from the admin gets a new one, because a resend is an explicit request to deliver again. |
| `X-DSGo-Timestamp` | Unix time of this attempt. It changes on every retry. |
| `X-DSGo-Signature` | Only present when the site has a signing secret (DesignSetGo → Settings → Integrations). |

## Payload

```json
{
  "version": 1,
  "event": "form.submitted",
  "form_id": "contact-a1b2",
  "submission_id": 4821,
  "submitted_at": "2026-10-06T14:15:00+00:00",
  "source_url": "https://example.com/contact/",
  "fields": { "your_name": "Pat Lee", "topics": ["billing", "support"] },
  "labels": { "your_name": "Your name", "topics": "topics" }
}
```

- `version` — payload format. It only changes for a breaking change, and existing keys are never removed within a version.
- `submission_id` — stable for the submission. Use it, not `X-DSGo-Delivery`, if a manual Resend should update the same record instead of creating a new one.
- `submitted_at` — ISO 8601, UTC.
- `fields` — field name → value. A value is a string, or an array of strings for multi-value fields such as checkboxes. An empty form sends `{}`.
- `labels` — field name → the label the visitor saw. Fields without a label (hidden fields, and submissions stored before 2.10.0) fall back to the field name.

Sites can change the payload with the `designsetgo_form_webhook_payload` filter, so treat unknown keys as optional.

## Verifying the signature

The signature is `sha256=` followed by the hex HMAC-SHA256 of:

```
{X-DSGo-Timestamp}.{raw request body}
```

…keyed with the signing secret. Verify against the **raw** body bytes, before any JSON parsing, and compare in constant time. Reject requests whose timestamp is more than about five minutes from your clock, so a captured request can't be replayed.

```js
// Node.js
const crypto = require( 'crypto' );

function verify( rawBody, headers, secret ) {
	const timestamp = headers[ 'x-dsgo-timestamp' ];
	const signature = headers[ 'x-dsgo-signature' ] || '';
	if ( Math.abs( Date.now() / 1000 - Number( timestamp ) ) > 300 ) {
		return false;
	}
	const expected = 'sha256=' + crypto.createHmac( 'sha256', secret ).update( `${ timestamp }.${ rawBody }` ).digest( 'hex' );
	return signature.length === expected.length && crypto.timingSafeEqual( Buffer.from( signature ), Buffer.from( expected ) );
}
```

```php
// PHP
function verify_dsgo_webhook( string $raw_body, string $timestamp, string $signature, string $secret ): bool {
	if ( abs( time() - (int) $timestamp ) > 300 ) {
		return false;
	}
	$expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
	return hash_equals( $expected, $signature );
}
```

Without a secret, requests are unsigned and anyone who knows the URL can post to it. Set a secret for anything that acts on the data.

## Responses and retries

| Your response | What happens |
| --- | --- |
| `2xx` | Delivered. |
| No response (timeout, DNS, TLS), `408`, `425`, `429`, `5xx` | Retried after 1 min, 5 min, 30 min and 2 h — five attempts in all — then marked **Failed**. |
| `3xx` or any other `4xx` | Marked **Failed** at once. These repeat until the URL or receiver is fixed. Redirects are never followed. |

The first attempt happens just after the visitor's response is sent (on PHP-FPM and LiteSpeed), or a moment later via WP-Cron on other servers. Each attempt waits up to 5 seconds for you to answer, so respond fast and process asynchronously.

Admins can see each submission's status under **DesignSetGo → Form Submissions** and resend it from the row actions or the submission screen.

## Restrictions

- The URL must be `http` or `https`, on port 80, 443 or 8080, and resolve to a public IP. Loopback, private and link-local addresses are refused (WordPress's `wp_http_validate_url()`).
- The URL comes from the saved block, never from the visitor's request, and is copied onto the submission, so later edits to the form don't redirect pending retries.
- Anyone who can edit the page can read the URL in the block markup. Don't put a password in it; use the signing secret.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `designsetgo_form_webhook_payload` | filter | `( array $payload, int $submission_id, string $form_id )`. Change the payload. A non-array return is ignored. |
| `designsetgo_form_webhook_url` | filter | `( string $url, string $form_id, int $submission_id )`. Return `''` to block a destination. The result is validated again. |
| `designsetgo_form_webhook_timeout` | filter | `( int $seconds )`. Default 5, clamped to 1–30. |
| `designsetgo_form_webhook_retry_delays` | filter | `( int[] $delays )`. Seconds before each retry; the count is the number of retries, and an empty array disables them. |
| `designsetgo_form_webhook_send_after_response` | filter | `( bool $after_response )`. Whether the first attempt runs at shutdown (true) or on WP-Cron (false). Defaults to whether the server can flush the response early. |
| `designsetgo_form_webhook_delivered` | action | `( int $submission_id, string $form_id, int $code )`. |
| `designsetgo_form_webhook_failed` | action | `( int $submission_id, string $form_id, string $error, int $code )`. Fires once, when delivery gives up. |
