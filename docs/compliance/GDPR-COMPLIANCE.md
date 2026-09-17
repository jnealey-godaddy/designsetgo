# GDPR Compliance Guide

DesignSetGo implements comprehensive GDPR (General Data Protection Regulation) compliance features for form submissions.

## Features

✅ **Personal Data Export** - Export all form submissions for a specific email address
✅ **Personal Data Erasure** - Delete all form submissions for a specific email address
✅ **WordPress Privacy Tools Integration** - Seamless integration with WordPress's built-in privacy features
✅ **Privacy Policy Text** - Suggested privacy policy content for your site
✅ **REST API** - Programmatic access for data requests

## Table of Contents

- [For Site Administrators](#for-site-administrators)
- [For Developers](#for-developers)
- [Privacy Policy Integration](#privacy-policy-integration)
- [Data Retention](#data-retention)
- [User Rights Under GDPR](#user-rights-under-gdpr)

---

## For Site Administrators

### Using WordPress Privacy Tools

DesignSetGo integrates with WordPress's built-in privacy tools (available since WordPress 4.9.6).

#### Exporting Personal Data

1. Go to **Tools → Export Personal Data**
2. Enter the user's email address
3. Click **Send Request** or **Create and Download** for immediate export
4. DesignSetGo form submissions will be included in the export under "Form Submissions"

**What gets exported:**
- Submission ID
- Form ID
- Submission date
- All form field values
- IP address (if logging is enabled)
- User agent (if logging is enabled)
- Referrer (if logging is enabled)

#### Erasing Personal Data

1. Go to **Tools → Erase Personal Data**
2. Enter the user's email address
3. Click **Send Request** or **Erase Personal Data** for immediate erasure
4. All form submissions containing that email address will be permanently deleted

**What gets erased:**
- All form submissions where the email appears in any field
- All associated metadata (IP, user agent, etc.)
- Submissions are permanently deleted (not moved to trash)

### Privacy Policy

DesignSetGo provides suggested privacy policy text that you can add to your site's privacy policy:

1. Go to **Settings → Privacy**
2. Find "DesignSetGo Forms" in the policy guide
3. Click **Use This Text** to add it to your privacy policy
4. Review and customize as needed

---

## For Developers

### REST API Endpoints

#### Export Personal Data

```http
POST /wp-json/designsetgo/v1/gdpr/export
```

**Headers:**
```
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "email": "user@example.com",
  "count": 3,
  "data": [
    {
      "group_id": "designsetgo-form-submissions",
      "group_label": "Form Submissions",
      "item_id": "form-submission-123",
      "data": [
        {
          "name": "Submission ID",
          "value": "123"
        },
        {
          "name": "Form ID",
          "value": "contact-form"
        },
        {
          "name": "Email",
          "value": "user@example.com"
        }
      ]
    }
  ]
}
```

**Example Usage:**
```javascript
const response = await fetch('/wp-json/designsetgo/v1/gdpr/export', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce,
  },
  body: JSON.stringify({
    email: 'user@example.com',
  }),
});

const data = await response.json();
console.log(`Found ${data.count} submissions`);
```

#### Delete Personal Data

```http
DELETE /wp-json/designsetgo/v1/gdpr/delete
```

**Headers:**
```
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "email": "user@example.com",
  "items_removed": true,
  "items_retained": false,
  "messages": []
}
```

**Example Usage:**
```javascript
const response = await fetch('/wp-json/designsetgo/v1/gdpr/delete', {
  method: 'DELETE',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce,
  },
  body: JSON.stringify({
    email: 'user@example.com',
  }),
});

const data = await response.json();
if (data.success && data.items_removed) {
  console.log('Data successfully deleted');
}
```

### PHP Filters and Actions

#### Prevent Deletion Hook

Prevent specific submissions from being deleted:

```php
add_filter( 'designsetgo_can_delete_form_submission', function( $can_delete, $post_id, $email ) {
    // Keep submissions marked as important
    $is_important = get_post_meta( $post_id, '_important', true );

    if ( $is_important ) {
        return false; // Prevent deletion
    }

    return $can_delete;
}, 10, 3 );
```

#### After Erasure Hook

Perform actions after data is erased:

```php
add_action( 'designsetgo_form_submission_erased', function( $post_id, $email ) {
    // Log the erasure
    error_log( "Form submission {$post_id} erased for {$email}" );

    // Notify admin
    wp_mail(
        get_option( 'admin_email' ),
        'GDPR Data Erasure',
        "Form submission {$post_id} was erased per GDPR request."
    );
}, 10, 2 );
```

#### Custom Data Exporter

Add custom data to the export:

```php
add_filter( 'wp_privacy_personal_data_exporters', function( $exporters ) {
    $exporters['my-custom-data'] = array(
        'exporter_friendly_name' => 'My Custom Data',
        'callback'               => 'my_custom_data_exporter',
    );
    return $exporters;
} );

function my_custom_data_exporter( $email_address, $page = 1 ) {
    // Export custom data related to form submissions
    $export_items = array();

    // Your custom export logic here

    return array(
        'data' => $export_items,
        'done' => true,
    );
}
```

### Programmatic Access

#### Export Data Programmatically

```php
// Get GDPR compliance instance
$gdpr = new \DesignSetGo\Includes\Admin\GDPR_Compliance();

// Export data for an email address
$result = $gdpr->export_form_submissions( 'user@example.com', 1 );

if ( ! empty( $result['data'] ) ) {
    foreach ( $result['data'] as $item ) {
        // Process exported data
        $submission_id = $item['item_id'];
        $data = $item['data'];
    }
}
```

#### Erase Data Programmatically

```php
// Get GDPR compliance instance
$gdpr = new \DesignSetGo\Includes\Admin\GDPR_Compliance();

// Erase data for an email address
$result = $gdpr->erase_form_submissions( 'user@example.com', 1 );

if ( $result['items_removed'] ) {
    // Data successfully erased
    echo 'Personal data has been erased.';
}

if ( $result['items_retained'] ) {
    // Some data was retained
    foreach ( $result['messages'] as $message ) {
        echo $message;
    }
}
```

---

## Privacy Policy Integration

DesignSetGo provides suggested privacy policy text that covers:

1. **Data Collection**
   - What information is collected from forms
   - Automatically collected metadata (IP, user agent, etc.)

2. **Data Usage**
   - How submitted data is used
   - Who has access to the data

3. **Data Retention**
   - How long data is stored
   - Deletion policies

4. **User Rights**
   - Right to access
   - Right to rectification
   - Right to erasure
   - Right to data portability
   - Right to object

5. **Security Measures**
   - How data is protected
   - Spam prevention features

### Adding to Your Privacy Policy

1. Navigate to **Settings → Privacy** in WordPress admin
2. Find the "DesignSetGo Forms" section
3. Review the suggested text
4. Click "Use This Text" to add it to your policy
5. Customize as needed for your specific use case

---

## Data Retention

### Current Policy

By default, DesignSetGo retains form submissions indefinitely. This allows you to:
- Review historical submissions
- Respond to inquiries over time
- Maintain records for business purposes

### Implementing Automatic Deletion

You can implement automatic deletion of old submissions using WordPress's built-in cron:

```php
// Delete submissions older than 90 days
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'designsetgo_cleanup_old_submissions' ) ) {
        wp_schedule_event( time(), 'daily', 'designsetgo_cleanup_old_submissions' );
    }
} );

add_action( 'designsetgo_cleanup_old_submissions', function() {
    $args = array(
        'post_type'      => 'dsgo_form_submission',
        'posts_per_page' => 100,
        'post_status'    => 'private',
        'date_query'     => array(
            array(
                'before' => '90 days ago',
            ),
        ),
    );

    $old_submissions = new WP_Query( $args );

    if ( $old_submissions->have_posts() ) {
        while ( $old_submissions->have_posts() ) {
            $old_submissions->the_post();
            wp_delete_post( get_the_ID(), true );
        }
    }

    wp_reset_postdata();
} );
```

### Data Retention Notice

Site administrators will see a notice on the form submissions screen with:
- Link to WordPress Privacy Tools
- Link to Privacy Policy settings
- Reminder about GDPR compliance

---

## User Rights Under GDPR

### Right to Access

Users can request a copy of their personal data. This is handled through:
- WordPress's "Export Personal Data" tool
- REST API endpoint: `/wp-json/designsetgo/v1/gdpr/export`

### Right to Rectification

Users can request correction of inaccurate data. Site administrators should:
1. Navigate to **Form Submissions**
2. Find the relevant submission
3. Edit the submission data manually

### Right to Erasure ("Right to be Forgotten")

Users can request deletion of their personal data. This is handled through:
- WordPress's "Erase Personal Data" tool
- REST API endpoint: `/wp-json/designsetgo/v1/gdpr/delete`

### Right to Data Portability

Data is exported in a structured, machine-readable format (JSON) that can be:
- Downloaded by users
- Imported into other systems
- Used for migration purposes

### Right to Object

Users can object to processing of their data. Site administrators should:
1. Stop collecting data from that user
2. Delete existing data using erasure tools
3. Block the user's email from future submissions (if necessary)

---

## Best Practices

### 1. Minimize Data Collection

Only collect data that is necessary:
```php
// Example: Disable IP logging if not needed
add_filter( 'designsetgo_form_log_ip_addresses', '__return_false' );
```

### 2. Regular Data Audits

Periodically review and clean up old submissions:
- Check for outdated data
- Remove unnecessary metadata
- Archive important submissions

### 3. Secure Data Storage

- Keep WordPress updated
- Use strong passwords
- Limit admin access
- Enable two-factor authentication
- Use HTTPS

### 4. Transparent Communication

- Keep privacy policy up to date
- Inform users about data collection
- Make privacy tools easily accessible
- Respond promptly to data requests

### 5. Document Compliance

- Keep records of data requests
- Document deletion processes
- Maintain audit logs
- Train staff on GDPR procedures

---

## Compliance Checklist

- [ ] Privacy policy includes form submission data collection
- [ ] Users are informed about data collection
- [ ] Data export functionality is tested
- [ ] Data erasure functionality is tested
- [ ] Admin team knows how to handle data requests
- [ ] Data retention policy is defined
- [ ] Automatic deletion is configured (if applicable)
- [ ] Security measures are in place
- [ ] Regular data audits are scheduled
- [ ] GDPR compliance documentation is maintained

---

## Support

For questions about GDPR compliance:

- **Documentation:** See [SECURITY.md](../../SECURITY.md) for security-related information
- **Privacy Tools:** Use WordPress's built-in privacy features (Tools menu)
- **Data Requests:** Follow the procedures outlined in this guide

---

**Last Updated:** 2024-11-11
**Version:** 1.0.0
