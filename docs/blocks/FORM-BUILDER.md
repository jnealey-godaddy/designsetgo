# Form Builder Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: form, contact, submit, fields, survey

## Overview

The **Form Builder Block** lets you create custom forms with 12 field types, AJAX submission, email notifications, and built-in spam protection. Perfect for contact forms, surveys, registrations, and lead generation.

**Key Features:**
- 12 field types (text, email, phone, number, select, checkbox, textarea, date, time, URL, file, hidden)
- AJAX or standard form submission
- Email notifications with customizable templates
- Honeypot and rate limiting spam protection
- Inline or below-field button positioning
- Field-level validation and customization
- Full styling control (colors, spacing, sizing)

## Quick Start

### Creating a Basic Contact Form
1. Insert the **Form Builder** block.
2. The default template includes Name (text), Email, and Message (textarea) fields.
3. Customize field labels by clicking each field.
4. Configure form settings in the sidebar (optional).
5. Preview and publish.

### Adding More Fields
1. Click the `+` button inside the Form Builder.
2. Select from 12 field types.
3. Configure each field's settings (label, placeholder, validation, etc.).

## Available Field Types

**Text-based inputs:**
- **Text Field**: Single-line text (names, subjects, etc.)
- **Email Field**: Email with validation
- **Phone Field**: Phone numbers with validation
- **URL Field**: Web addresses with validation
- **Number Field**: Numeric values with min/max
- **Textarea**: Multi-line text (messages, comments)

**Selection inputs:**
- **Select Field**: Dropdown menu with custom options
- **Checkbox**: Single checkbox (agreements, opt-ins)

**Date/Time inputs:**
- **Date Field**: Date picker
- **Time Field**: Time selector

**Special fields:**
- **Hidden Field**: Pass hidden data (UTM params, page IDs)
- **File Upload**: Coming soon

## Form Settings

### General Settings
- **AJAX Submit**: Submit without page reload (default: ON)
- **Submit Button Text**: Customize button label
- **Button Position**: Below fields or inline with last field
- **Button Alignment**: Left, center, or right (when below)

### Button Styling
- **Button Height**: Minimum height (default: 44px)
- **Padding**: Vertical and horizontal spacing
- **Font Size**: Custom button text size

### Field Styling
- **Field Spacing**: Gap between fields (default: 1.5rem)
- **Input Height**: Minimum height for inputs (default: 44px)
- **Input Padding**: Padding inside inputs (default: 0.75rem)

### Colors
Access via **Styles > Color** in the sidebar:
- **Label Color**: Field label text color
- **Border Color**: Input border color
- **Field Background**: Input background color
- **Button Text Color**: Submit button text
- **Button Background Color**: Submit button background

### Messages
- **Success Message**: Shown after successful submission
- **Error Message**: Shown if submission fails

**Confirmation persistence (2.1.0):** After a successful AJAX submission the confirmation message is stored in `sessionStorage` keyed to the form's unique ID. If the submitter refreshes the page the message is restored once and then cleared, so they see the confirmation even after a reload without seeing it on every subsequent visit.

**Stale confirmation clearing:** The stored confirmation is keyed to the form's unique ID (`data-form-id` attribute). If the same form instance is reopened with a different unique key (for example, after a template switch or block duplication that assigns a new ID) the old `sessionStorage` key no longer matches and the stale confirmation is silently discarded.

### Spam Protection
- **Honeypot**: Invisible field to catch bots (default: ON)
- **Rate Limiting**: Limit submissions per IP (default: ON, 3 per 60 seconds)

### Email Notifications
Enable to receive email when forms are submitted:
- **Recipient Email**: Where to send notifications
- **Email Subject**: Subject line (supports `{field_name}` placeholders)
- **From Name/Email**: Customize sender info
- **Reply-To Field**: Use form field for reply-to (e.g., "email")
- **Email Body Template**: Custom message body using `{field_name}` or `{all_fields}`

**Template Example:**
```
New form submission from {name}:

{all_fields}

Submitted from: {page_url}
```

## Field-Level Settings

Each field block has its own settings:

### Common Settings (All Fields)
- **Field Name**: Internal name for data (auto-generated)
- **Label**: Visible label text
- **Help Text**: Optional instruction text below field
- **Required**: Mark field as mandatory
- **Field Width**: Width percentage (25%, 50%, 75%, 100%)

### Text Field Specific
- **Placeholder**: Example text inside input
- **Default Value**: Pre-filled value
- **Min/Max Length**: Character limits
- **Validation**: None, letters only, numbers only, alphanumeric, custom regex
- **Validation Pattern**: Custom regex pattern
- **Validation Message**: Error message for invalid input

### Select Field Specific
- **Options**: List of choices (label and value pairs)
- **Placeholder**: Default option text
- **Default Value**: Pre-selected option

### Checkbox Specific
- **Checked by Default**: Start checked
- **Value**: Value when checked (default: "1")

### Number Field Specific
- **Min/Max**: Number range limits
- **Step**: Increment value

### Hidden Field Specific
- **Value**: The hidden value to submit

## Common Use Cases

### 1. Contact Form
Use **Email Field** (required), **Text Field** for name, and **Textarea** for message. Enable email notifications.

### 2. Newsletter Signup
Single **Email Field** with button positioned **inline** for a compact layout.

### 3. Event Registration
Combine **Text** (name), **Email**, **Phone**, **Date** (event date), and **Select** (session choice).

### 4. Survey Form
Use multiple **Select** and **Checkbox** fields. Consider using **Textarea** for open-ended responses.

### 5. Lead Capture with Source Tracking
Add **Hidden Field** to capture UTM parameters or page source. Use JavaScript to populate:
```javascript
document.querySelector('[name="utm_source"]').value =
  new URLSearchParams(window.location.search).get('utm_source');
```

## Best Practices

**DO:**
- Use descriptive field labels ("Your Email Address" not just "Email")
- Mark important fields as required
- Provide help text for complex fields
- Enable spam protection (honeypot + rate limiting)
- Test form submission before publishing
- Use AJAX submit for better UX
- Set up email notifications to capture leads

**DON'T:**
- Ask for unnecessary information (reduces conversions)
- Use default field names ("text-field-1") - customize them
- Skip validation on email/phone fields
- Forget to test error states
- Make forms too long (break into multiple steps if needed)

## Accessibility

- **Labels**: All fields include proper `<label>` elements
- **Required Fields**: Marked with `aria-required="true"` and visual indicator
- **Error Messages**: Announced via `role="alert"` for screen readers
- **Keyboard Navigation**: Full keyboard support (Tab, Enter, Escape)
- **Focus Management**: Clear focus indicators on all inputs
- **Help Text**: Associated with inputs via `aria-describedby`
- **Success/Error Feedback**: Announced to screen readers via `aria-live`

## Advanced Features

### Form Data Storage
Form submissions are stored in the WordPress database. Access via **Tools > Form Submissions** (if enabled in plugin settings).

### Custom Events (Developers)
Listen for form submission events:
```javascript
document.addEventListener('dsgoFormSubmitted', (e) => {
  console.log('Form submitted:', e.detail.formId);
  console.log('Submission ID:', e.detail.submissionId);
  // Track with Google Analytics, etc.
});

document.addEventListener('dsgoFormError', (e) => {
  console.log('Form error:', e.detail.error);
});
```

### Email Template Variables
Available in email subject and body:
- `{field_name}`: Any form field value (e.g., `{email}`, `{name}`)
- `{all_fields}`: All submitted fields formatted
- `{page_url}`: URL where form was submitted
- `{page_title}`: Title of the page

### Standard (Non-AJAX) Submission
Disable AJAX to use traditional form submission. Useful for:
- Redirecting to thank-you pages
- Payment gateway integration
- Custom server-side processing

### Redirect URL Hardening (2.1.0)
The redirect URL set in form settings is normalized with the browser's `URL` constructor before navigation occurs. Only `http:` and `https:` protocol URLs are followed; `javascript:`, `data:`, and any other non-HTTP protocol are silently blocked. Relative paths are resolved against the current page origin.

## Troubleshooting

**Form not submitting?**
- Check browser console for JavaScript errors
- Verify all required fields are filled
- Ensure nonce/REST API is working (check Network tab)

**Email notifications not sending?**
- Verify recipient email is correct
- Check WordPress email settings (Settings > General)
- Test with SMTP plugin if using shared hosting

**Spam submissions?**
- Ensure honeypot is enabled
- Adjust rate limiting (lower count, higher window)
- Consider adding CAPTCHA via third-party plugin

**Styling issues?**
- Use browser DevTools to inspect elements
- Check theme CSS conflicts
- Adjust color settings in sidebar

## Tips & Tricks

1. **Inline Button Layout**: Great for newsletter signups - set button position to "inline"
2. **Field Width**: Use 50% width on two fields to create side-by-side layouts
3. **Hidden Fields**: Track campaign sources, page IDs, or user roles
4. **Custom Validation**: Use regex patterns for specific formats (e.g., zip codes)
5. **Conditional Logic**: Use CSS to hide/show fields based on other selections (custom code)
6. **Multi-Column Forms**: Combine Field Width settings (25%, 50%, 75%) for complex layouts

---

**Need Help?** Visit the [DesignSetGo documentation](https://designsetgo.com/docs) or [open a support ticket](https://designsetgo.com/support).
