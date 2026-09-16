# Security Policy

## Supported Versions

DesignSetGo follows the WordPress.org plugin convention of supporting only the latest released version. We release security patches for the current 2.x line (currently 2.7.x); older versions should update to the latest release to receive fixes.

## Reporting a Vulnerability

We take the security of DesignSetGo seriously. If you believe you have found a security vulnerability, please report it to us responsibly.

### How to Report

Please report security vulnerabilities through **GitHub Security Advisories**:

1. Go to the [Security tab](https://github.com/jnealey-godaddy/designsetgo/security/advisories) on the repository
2. Click **"Report a vulnerability"**
3. Fill out the advisory form with the details below

This keeps the report private between you and the maintainers until a fix is ready.

### What to Include

Please include the following information in your report:

- Type of vulnerability (e.g., XSS, SQL injection, CSRF, etc.)
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the vulnerability, including how an attacker might exploit it

### Response Timeline

- **Initial Response:** Within 48 hours
- **Status Update:** Within 7 days
- **Fix Timeline:** Depends on severity
  - Critical: 1-3 days
  - High: 1-2 weeks
  - Medium: 2-4 weeks
  - Low: Next release cycle

### Disclosure Policy

- We will confirm receipt of your vulnerability report
- We will investigate and validate the vulnerability
- We will work on a fix and release timeline
- We will keep you informed of our progress
- Once the vulnerability is patched, we will publicly disclose it (with credit to you if desired)

## Security Features

DesignSetGo implements multiple layers of security:

### Input Validation & Sanitization

- **Form Submissions:** All user input is validated and sanitized based on field type
- **REST API Parameters:** Schema validation with type checking and sanitize callbacks
- **Email Addresses:** Validated using WordPress `is_email()` function
- **URLs:** Validated and sanitized using WordPress functions
- **Custom CSS:** Sanitized using `wp_strip_all_tags()` to prevent XSS

### Output Escaping

- **HTML Output:** All dynamic content is escaped using appropriate WordPress functions
  - `esc_html()` for plain text
  - `esc_attr()` for HTML attributes
  - `esc_url()` for URLs
  - `wp_kses_post()` for rich content

### CSRF Protection

- **Nonces:** All state-changing operations require valid WordPress nonces
- **REST API:** Nonce verification on all authenticated endpoints
- **Capability Checks:** Permission checks before nonce verification for better performance

### SQL Injection Prevention

- **Prepared Statements:** All database queries use `$wpdb->prepare()`
- **No Dynamic SQL:** No raw SQL queries with user input

### Email Security

- **Header Injection Prevention:** All email parameters sanitized to remove newlines (`\r`, `\n`, `%0a`, `%0d`)
- **Email Validation:** All email addresses validated before use
- **Reply-To Protection:** Reply-To headers sanitized and validated

### Form Submission Protection

- **Honeypot Fields:** Bot detection using hidden fields
- **Time-Based Checks:** Submissions faster than 3 seconds are rejected
- **Rate Limiting:** Maximum 3 submissions per 60 seconds per IP address
- **IP Tracking:** Optional IP address logging for abuse prevention

### Authentication & Authorization

- **Capability Checks:** All admin operations require `manage_options` capability
- **Public Endpoints:** Carefully documented with explicit security measures
- **REST API Security:** Proper permission callbacks on all endpoints

### External Link Security

- **window.opener Protection:** External links use `window.opener = null` to prevent tab-napping
- **Rel Attributes:** Support for `nofollow`, `noopener`, `noreferrer` on links

## Security Best Practices for Users

### Installation

1.  **Download from Official Sources:** Only install DesignSetGo from WordPress.org or GitHub releases
2.  **Keep Updated:** Always use the latest version to get security patches
3.  **Secure WordPress:** Follow WordPress security best practices

### Configuration

1.  **Form Settings:**
    - Enable rate limiting (enabled by default)
    - Use honeypot fields (enabled by default)
    - Disable IP logging if privacy is a concern

2.  **Custom CSS:**
    - Only allow trusted users to add custom CSS
    - Custom CSS is sanitized, but limit access to admin users only

3.  **File Uploads:**
    - DesignSetGo does not handle file uploads
    - If you add file upload functionality via hooks, ensure proper validation

### Monitoring

1.  **Review Form Submissions:** Regularly check for spam or suspicious submissions
2.  **Monitor Rate Limits:** Check if legitimate users are being rate-limited
3.  **Update Dependencies:** Keep WordPress and all plugins updated

## Known Security Considerations

### Public Form Endpoint

The form submission endpoint (`/wp-json/designsetgo/v1/form/submit`) is intentionally public to accept submissions from non-logged-in users.

**Security measures in place:**
- Honeypot field detection
- Time-based submission checking
- Rate limiting per IP address
- Comprehensive input validation
- Email header injection prevention

**Recommendations:**
- Monitor form submissions for abuse
- Consider adding reCAPTCHA for high-traffic sites
- Use Cloudflare or similar for additional protection

### Custom CSS Feature

The Custom CSS feature allows admin users to add CSS to blocks.

**Security measures in place:**
- Limited to users with `manage_options` capability
- CSS is sanitized using `wp_strip_all_tags()`
- Stored in post meta with proper escaping

**Recommendations:**
- Only grant admin access to trusted users
- Regularly audit custom CSS for malicious code

### IP Address Logging

Form submissions can optionally log IP addresses for abuse prevention.

**Privacy considerations:**
- IP addresses are personal data under GDPR
- Include IP logging in your privacy policy
- Provide opt-out option if required by local laws
- Consider disabling IP logging if not needed

### GDPR Compliance

DesignSetGo includes comprehensive GDPR compliance features for form submissions:

**Features:**
- **Personal Data Export**: Export all form submissions for a specific email address via WordPress Privacy Tools
- **Personal Data Erasure**: Delete all form submissions for a specific email address (right to be forgotten)
- **Privacy Policy Integration**: Suggested privacy policy text automatically added to WordPress Privacy settings
- **REST API Access**: Programmatic data export and deletion endpoints for developers

**How to use:**
1.  **Export Data**: Go to Tools → Export Personal Data, enter email address
2.  **Erase Data**: Go to Tools → Erase Personal Data, enter email address
3.  **Privacy Policy**: Go to Settings → Privacy, find "DesignSetGo Forms" section

**For developers:**
- REST API: `POST /wp-json/designsetgo/v1/gdpr/export`
- REST API: `DELETE /wp-json/designsetgo/v1/gdpr/delete`
- Hook: `designsetgo_can_delete_form_submission` - Prevent specific deletions
- Hook: `designsetgo_form_submission_erased` - Run actions after erasure

See [docs/compliance/GDPR-COMPLIANCE.md](docs/compliance/GDPR-COMPLIANCE.md) for complete documentation.

## Security Audit History

### Version 2.0.x (2026-02)

**Security Improvements:**

- Added global `safe_style_css` filter for block inline styles (KSES allowlist)
- Moved form email configuration to server-side for improved security
- Unified form field block context namespace
- Dependency updates (qs 6.14.2)

**Audited by:** Internal security review

### Version 1.5.0 (2026-02)

**Security Improvements:**

- Server-side SVG pattern rendering (eliminates client-side injection surface)
- Code splitting with lazy loading for extensions and admin
- REST API exposure of extension attributes with proper permission callbacks
- Server-side attribute injection for parallax effects

**Audited by:** Internal security review

### Version 1.4.0 (2026-02-01)

**Security Improvements:**
- Bumped minimum PHP requirement from 7.4 to 8.0
- Updated lodash and lodash-es to 4.17.23 (security patches)
- Added block exclusion system to prevent extension conflicts with third-party blocks

**Audited by:** Internal security review

### Version 1.2.0 (2025-11-21)

**Comprehensive Security Review:**
- Verified all REST API endpoints are secured
- Confirmed input sanitization and output escaping
- Verified SQL injection prevention
- Checked form security measures (honeypot, rate limiting)
- Validated dependency security

**Grade:** A+ (100/100)

**Audited by:** Internal security review

### Version 1.0.0 (2025-11-12)

**Comprehensive Security Review:**
- Fixed SQL injection vulnerability in stats endpoint
- Fixed email header injection vulnerability
- Added REST API parameter validation
- Improved permission check ordering
- Added database query caching
- Created comprehensive unit tests

**Grade:** A- (94/100)

**Audited by:** Internal security review

## Responsible Disclosure Credits

We appreciate the security researchers who have responsibly disclosed vulnerabilities:

- None reported yet

If you report a vulnerability, we'll credit you here (with your permission).

## Security Resources

- [WordPress Security Best Practices](https://developer.wordpress.org/apis/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)

## Contact

For security-related questions or concerns:

- **Security Vulnerabilities:** [Report via GitHub Security Advisory](https://github.com/jnealey-godaddy/designsetgo/security/advisories)
- **GitHub Issues:** For non-security bugs and general security questions
- **Documentation:** See README.md

---

**Last Updated:** 2026-09-16
**Version:** 2.7.5
