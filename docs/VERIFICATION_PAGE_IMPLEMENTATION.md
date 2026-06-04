# Verification Page Implementation Guide

## Overview

This document provides comprehensive documentation for the new Massango identity verification page refactor. The implementation transforms the legacy modal-based verification system into a modern, professional, and secure dedicated page with full integration to the FastAPI AI pipeline.

## Project Structure

```
public/
├── verification/
│   └── index.php                    # Main verification page
├── api/
│   ├── verification-submit.php      # Secure submission endpoint
│   └── verification-status.php      # Status polling endpoint
└── assets/
    ├── css/verification/
    │   ├── verification.css         # Main styles
    │   └── verification-responsive.css # Responsive design
    └── js/verification/
        ├── verification-main.js     # Core logic
        ├── verification-media.js    # Video capture
        └── verification-api.js      # API communication

includes/
└── verificationmodal_redirect.php   # Backward compatibility bridge

docs/
├── verification_page_design.md      # Design document
└── VERIFICATION_PAGE_IMPLEMENTATION.md # This file
```

## Features

### User Interface

The new verification page includes:

- **Hero Section:** Professional introduction with benefits highlighting
- **Progress Indicator:** Visual 4-step progress tracking
- **Personal Information Form:** Secure data collection with validation
- **Document Upload:** Drag-and-drop support with preview functionality
- **Video Capture:** Live camera feed with 10-second recording limit
- **Review Section:** Final data confirmation before submission
- **Processing Status:** Real-time AI processing indicators
- **Result Display:** Success/error messaging with detailed feedback

### Security Features

- **Authentication:** User login validation via `is_logged_in()`
- **Input Sanitization:** All inputs sanitized with `htmlspecialchars()`
- **MIME Validation:** File type verification using `finfo`
- **Size Limits:** 10MB maximum file size enforced
- **Path Traversal Protection:** Filename sanitization and path validation
- **Prepared Statements:** All database queries use prepared statements
- **XSS Prevention:** HTML encoding on all user-facing data
- **CSRF Protection:** X-Requested-With header validation

### Responsive Design

- Mobile-first approach
- Tablet optimization (768px - 1024px)
- Desktop layout (1025px+)
- Landscape mode support
- Accessibility features (high contrast, reduced motion)

## Installation & Setup

### 1. File Placement

Copy all files to their respective locations in the Massango project:

```bash
# Copy verification page
cp public/verification/index.php /path/to/massango/public/verification/

# Copy API endpoints
cp public/api/verification-submit.php /path/to/massango/public/api/
cp public/api/verification-status.php /path/to/massango/public/api/

# Copy stylesheets
cp public/assets/css/verification/*.css /path/to/massango/public/assets/css/verification/

# Copy JavaScript files
cp public/assets/js/verification/*.js /path/to/massango/public/assets/js/verification/

# Copy compatibility bridge
cp includes/verificationmodal_redirect.php /path/to/massango/includes/
```

### 2. Database Schema Verification

Ensure the `user_verifications` table includes these columns:

```sql
CREATE TABLE IF NOT EXISTS `user_verifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `nickname` VARCHAR(50) NOT NULL,
    `birth_date` DATE NOT NULL,
    `province` VARCHAR(50) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `id_front_path` VARCHAR(255),
    `id_back_path` VARCHAR(255),
    `video_path` VARCHAR(255),
    `status` ENUM('pending', 'processing', 'approved', 'rejected', 'manual_review'),
    `ai_status` VARCHAR(50),
    `ai_similarity` FLOAT,
    `ai_liveness` BOOLEAN,
    `ai_notes` TEXT,
    `ai_result_json` JSON,
    `admin_notes` TEXT,
    `risk_level` VARCHAR(20),
    `reviewed_by` INT,
    `reviewed_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```

### 3. Directory Permissions

Ensure the uploads directory is writable:

```bash
chmod 755 /path/to/massango/uploads/verifications
```

### 4. FastAPI Integration

The new page integrates with the existing FastAPI service. Ensure:

- FastAPI service is running on `http://127.0.0.1:8000`
- `/identity/verify` endpoint is available
- `/identity/status/{verification_id}` endpoint is available

## Usage

### Accessing the Verification Page

Users can access the verification page at:

```
https://yourdomain.com/massangos/public/verification/
```

### Redirecting from Old Modal

To redirect users from the old modal to the new page, include the compatibility bridge:

```php
<?php
include_once 'includes/verificationmodal_redirect.php';
?>
```

Then trigger the redirect modal:

```javascript
// Old code that triggered the modal
$('#verificationModal').modal('show');

// New code - redirect to verification page
window.location.href = '<?php echo BASE_URL; ?>verification/';
```

### API Endpoints

#### Submit Verification

**Endpoint:** `POST /api/verification-submit.php`

**Parameters:**
- `full_name` (string): Full name as in document
- `nickname` (string): Display name
- `birth_date` (string): YYYY-MM-DD format
- `province` (string): Province name
- `contact_phone` (string): Phone number
- `id_front` (string): Base64-encoded front ID image
- `id_back` (string): Base64-encoded back ID image
- `video` (string): Base64-encoded verification video

**Response:**
```json
{
    "success": true,
    "message": "Verificação enviada com sucesso",
    "verification_id": 123,
    "status": "pending"
}
```

#### Get Verification Status

**Endpoint:** `GET /api/verification-status.php?verification_id=123`

**Response:**
```json
{
    "success": true,
    "verification_id": 123,
    "status": "pending",
    "ai_status": "processing",
    "ai_similarity": 0.95,
    "ai_liveness": true,
    "ai_notes": "Processing...",
    "admin_notes": null,
    "risk_level": null,
    "reviewed_by": null,
    "reviewed_at": null,
    "created_at": "2024-05-08 10:30:00",
    "updated_at": "2024-05-08 10:35:00"
}
```

## Frontend JavaScript API

### Main Functions

#### `initializeVerificationPage()`
Initializes all event listeners and page setup.

#### `goToStep(stepNumber)`
Navigate to a specific step (1-4).

```javascript
goToStep(2); // Go to document upload step
```

#### `handleDocumentUpload(event, docType)`
Handle document image upload.

```javascript
// Called automatically on file input change
// docType: 'front' or 'back'
```

#### `toggleVideoRecording()`
Start/stop video recording.

```javascript
toggleVideoRecording(); // Toggle recording state
```

#### `submitVerification(formData)`
Submit verification data to backend.

```javascript
const result = await submitVerification({
    fullName: 'João Silva',
    nickname: 'joao',
    birthDate: '1990-05-15',
    province: 'Maputo',
    contactPhone: '+258843123456',
    frontDoc: base64Data,
    backDoc: base64Data,
    video: base64Data
});
```

#### `pollVerificationStatus()`
Start polling for verification status updates.

```javascript
pollVerificationStatus(); // Polls every 5 seconds
```

### Event Listeners

The page automatically sets up listeners for:

- File input changes
- Form field changes
- Drag and drop events
- Form submission
- Video recording controls

## Styling & Customization

### CSS Variables

All styling uses CSS variables from `variables.css`:

```css
--primary: #07c95b;
--primary-hover: #027e21;
--bg-main: #f8fafc;
--bg-card: #ffffff;
--text-main: #000000;
--text-muted: #64748b;
```

### Customizing Colors

To customize colors, modify the CSS variables in `public/assets/css/variables.css`.

### Customizing Layout

Main layout styles are in `verification.css`. Responsive breakpoints are in `verification-responsive.css`.

## Security Considerations

### Input Validation

All user inputs are validated:

- **Full Name:** 5-100 characters
- **Birth Date:** Valid date, age 18+
- **Phone Number:** Mozambique format validation
- **Province:** Whitelist validation
- **Files:** MIME type and size validation

### File Upload Security

- Files are saved outside web root when possible
- Filenames are sanitized and randomized
- Path traversal is prevented via `realpath()` validation
- MIME types are verified using `finfo`

### Database Security

- All queries use prepared statements
- User ID is verified for authorization
- Sensitive data is properly encoded

## Troubleshooting

### Camera Access Issues

If users encounter camera access errors:

1. Check browser permissions
2. Ensure HTTPS is used (required for camera access)
3. Verify `Permissions-Policy` header is set correctly

### File Upload Failures

If files fail to upload:

1. Check directory permissions (755 for uploads/)
2. Verify disk space availability
3. Check file size limits (10MB max)
4. Verify MIME type is supported

### FastAPI Integration Issues

If AI processing fails:

1. Verify FastAPI service is running
2. Check network connectivity to `http://127.0.0.1:8000`
3. Review FastAPI logs for errors
4. Ensure file paths are correct

### Database Connection Issues

If database operations fail:

1. Verify database credentials in `config.php`
2. Check database user permissions
3. Ensure `user_verifications` table exists
4. Review database error logs

## Migration from Old Modal

### Step 1: Deploy New Files

Copy all new files to the Massango project.

### Step 2: Update Links

Replace old modal triggers with new page links:

```php
// Old
<button onclick="$('#verificationModal').modal('show')">Verificar</button>

// New
<a href="<?php echo BASE_URL; ?>verification/" class="btn btn-primary">Verificar</a>
```

### Step 3: Update Settings Page

In `public/settings.php`, replace the inline modal with:

```php
<?php include_once __DIR__ . '/../includes/verificationmodal_redirect.php'; ?>
```

### Step 4: Test Thoroughly

1. Test verification flow end-to-end
2. Test on mobile devices
3. Test with different document types
4. Test with different browsers
5. Verify FastAPI integration

## Performance Optimization

### Frontend Optimization

- Lazy loading for images
- Debounced form validation
- Efficient event delegation
- Minimal DOM manipulation

### Backend Optimization

- Database query optimization
- Prepared statements (cached)
- File I/O optimization
- Asynchronous AI processing

### Caching

- Browser caching for static assets
- LocalStorage for form data recovery
- Session caching for user data

## Monitoring & Logging

### Error Logging

Errors are logged to:

- Browser console (development)
- Server error logs (production)
- Database audit trail

### Status Tracking

Verification status is tracked in:

- `user_verifications` table
- `users.verification_status` field
- Admin review interface

## Future Enhancements

Potential improvements:

1. **Liveness Detection Improvements:** Add challenge-response verification
2. **Document Recognition:** Auto-detect document type and orientation
3. **OCR Integration:** Extract data from documents
4. **Biometric Analysis:** Additional facial recognition features
5. **Mobile App Integration:** Native mobile app support
6. **Webhook Notifications:** Real-time status notifications
7. **Batch Processing:** Handle multiple verifications efficiently
8. **Advanced Analytics:** Detailed verification statistics

## Support & Maintenance

### Regular Maintenance

- Monitor verification success rates
- Review failed verification reasons
- Update MIME type whitelist as needed
- Test FastAPI integration regularly

### Backup & Recovery

- Regular database backups
- Backup uploaded files
- Document recovery procedures
- Disaster recovery plan

## License & Attribution

This implementation is part of the Massango project and follows the same license terms.

---

**Last Updated:** May 8, 2024
**Version:** 1.0.0
**Status:** Production Ready
