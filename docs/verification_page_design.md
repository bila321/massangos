# Design Document: Massango Identity Verification Page Refactor

## 1. Introduction

This document outlines the architectural and component design for the refactoring of the Massango identity verification system. The objective is to transform the existing modal-based verification process into a dedicated, modern, professional, and premium full-page experience. The new page will integrate seamlessly with the existing FastAPI/AI pipeline (DeepFace, ArcFace, OpenCV, liveness detection) while maintaining full compatibility with the current Massango ecosystem, including XAMPP/Windows environments.

## 2. Current System Analysis Summary

Based on the analysis of the provided project files, the current verification system operates as follows:

*   **Frontend (Modal):** The existing verification modal, found in `includes/verificationmodal.php` and duplicated within `public/settings.php`, presents a multi-step form. This form collects personal data (full name, nickname, birth date, province, contact) and captures document images (front and back of ID) and a 10-second video for liveness detection. Media capture utilizes `getUserMedia` and `MediaRecorder` APIs. The collected data and base64-encoded media are submitted via `FormData` to `public/process_verification.php`.

*   **Backend (PHP - `public/process_verification.php`):** This PHP script receives the form data. It sanitizes inputs, saves the base64-encoded media (ID images and video) to `UPLOAD_DIR/verifications/{user_id}/`, and then attempts to call an AI verification API. The `call_face_verification_api` function, though not explicitly defined in `includes/functions.php`, is intended to bridge to the FastAPI service.

*   **AI Integration (FastAPI - `ai/identity/api_endpoint.py` & `ai/identity/php_trigger.php`):** The FastAPI service exposes an `/identity/verify` endpoint that expects `user_id`, `verification_id`, and absolute paths to the `id_front_path`, `id_back_path`, and `video_path`. It supports both synchronous and asynchronous processing. A `/identity/status/{verification_id}` endpoint is available for polling the verification status. The `ai/identity/php_trigger.php` file is designed to facilitate this communication between the PHP backend and the FastAPI service.

*   **Database (`amassangos.sql`):** The `user_verifications` table stores all verification-related data, including personal details, paths to uploaded media, and AI-generated results such as `ai_status` (e.g., `ai_approved`, `ai_rejected`, `manual_review`), `ai_similarity`, `ai_liveness`, `ai_notes`, and `ai_result_json`. The `users` table also contains a `verification_status` field.

*   **Styling:** The project employs a modular CSS approach with `public/assets/css/style.css` importing `variables.css` and other component-specific stylesheets. A premium aesthetic is suggested by `public/assets/css/auth_premium.css`.

## 3. New Verification Page Architecture

The new dedicated verification page will reside at `public/verification/index.php` (or an equivalent structure following the project's existing patterns). It will be a single-page application (SPA)-like experience, managed primarily by JavaScript, to provide a dynamic and responsive user interface.

### 3.1. Core Technologies

*   **Frontend:** HTML5, CSS3 (leveraging existing Massango styles and variables), JavaScript (Vanilla JS or a lightweight framework if necessary for complex UI, but prioritizing existing patterns). Fetch API for asynchronous communication.
*   **Backend:** PHP (existing `process_verification.php` will be adapted/extended), FastAPI (existing AI service).
*   **Database:** MySQL (existing `user_verifications` and `users` tables).

### 3.2. Page Flow and Steps

The verification process will be broken down into distinct, visually guided steps:

1.  **Welcome/Introduction (Hero Section):** A professional hero section explaining the verification benefits and process.
2.  **Personal Information:** Collect `full_name`, `nickname`, `birth_date`, `province`, `contact_phone` using modern form elements.
3.  **Document Upload (Front & Back):** Allow users to upload images for the front and back of their identification document. This step will feature drag-and-drop functionality, elegant previews, and client-side validation.
4.  **Selfie/Video Capture (Liveness Detection):** Guide the user through capturing a selfie or a short video for liveness detection. This will include clear instructions and visual feedback during recording.
5.  **Review & Submit:** A summary of all provided information and captured media, allowing the user to review before final submission.
6.  **Processing/Status:** A dedicated screen displaying the real-time status of the verification, including AI processing indicators and the final outcome.

### 3.3. Component Plan

The page will be composed of several modular and reusable components:

*   **Hero Section:** A prominent, visually appealing section at the top of the page, introducing the verification process. It will use existing typography and color schemes from `variables.css`.

*   **Progress Indicator:** A visual, multi-step progress bar or indicator to guide users through the verification flow. Each step will be clearly labeled and visually distinct.

*   **Information Cards:** Modern, responsive cards (`.settings-card` or similar from existing CSS) to encapsulate each step's content, providing a clean and organized layout.

*   **Form Elements:** Reusable input fields, dropdowns, and date pickers, styled according to Massango's existing form patterns (`.form-group`).

*   **Media Upload Components:**
    *   **Drag-and-Drop Zones:** Visually appealing areas for users to drag and drop their document images and videos.
    *   **Elegant Previews:** Thumbnail previews of uploaded images and videos, with a modal preview functionality for closer inspection.
    *   **Upload Progress Bars:** Visual feedback during asynchronous uploads.
    *   **File Input Buttons:** Styled buttons to trigger file selection dialogs.

*   **Camera/Video Capture Interface:** A dedicated section for live camera feed, recording controls (start/stop), and a countdown timer for video capture.

*   **Loading States & Skeletons:** Implement skeleton loading for content that is being fetched or processed, and clear loading indicators for asynchronous operations.

*   **Feedback Messages:** Modern, elegant error and success messages, styled consistently with the Massango alert system (`display_site_messages` function). These will provide clear, actionable feedback to the user.

*   **Modern Icons:** Utilize Font Awesome (already included) for all icons to maintain visual consistency.

*   **AI Status Indicators & Badges:** Visual elements to display the AI processing status (e.g., `pending`, `processing`, `approved`, `rejected`, `manual_review`), facial match score, and liveness detection status. Modern badges will be used to highlight key statuses.

*   **Action Buttons:** Clearly labeled primary and secondary buttons for navigation between steps, submission, and re-submission.

## 4. Frontend-Backend Interaction

### 4.1. Data Submission

*   **Initial Data:** Personal information will be submitted to `process_verification.php` via AJAX (Fetch API). This initial submission will create a new entry in `user_verifications` with a `pending` status and return the `verification_id`.
*   **Media Uploads:** Document images and the video will be uploaded asynchronously to `process_verification.php` using `FormData`. The PHP script will save these files and then trigger the FastAPI AI service via `ai/identity/php_trigger.php`.

### 4.2. Status Tracking

*   **Polling:** The frontend will periodically poll the FastAPI endpoint `/identity/status/{verification_id}` to retrieve the latest AI processing status and results. This will allow for real-time updates to the user interface.
*   **PHP Status Update:** The `process_verification.php` script will be updated to handle the initial saving of files and then trigger the asynchronous AI processing. It will also be responsible for updating the `user_verifications` table with the `verification_id` and initial status.

### 4.3. Error Handling

*   **Frontend Validation:** Client-side validation for form fields (e.g., required fields, date format) and media (file size, MIME type) will provide immediate feedback.
*   **Backend Validation:** `process_verification.php` will perform server-side validation and sanitization. Errors will be returned as JSON responses.
*   **AI Service Errors:** Errors from the FastAPI service will be captured by `php_trigger.php` and propagated back to `process_verification.php`, which will then update the `user_verifications` table with an `ai_error` status and relevant notes.

## 5. Security Considerations

*   **Authentication:** The new page will enforce user authentication using `is_logged_in()` and `get_current_user_id()` from `includes/functions.php`.
*   **Input Sanitization:** All user inputs will be sanitized using `sanitize_input()` from `includes/functions.php` to prevent XSS and other injection attacks.
*   **Prepared Statements:** Database interactions in `process_verification.php` will continue to use prepared statements to prevent SQL injection.
*   **Upload Validation:** Server-side validation of uploaded files will include size limits (`MAX_UPLOAD_SIZE`), allowed MIME types (`ALLOWED_IMAGE_TYPES`, `ALLOWED_VIDEO_TYPES`), and proper handling of file paths to prevent path traversal vulnerabilities.
*   **Unauthorized Access:** Access to the verification page and its backend endpoints will be restricted to authenticated users.
*   **FastAPI Security:** The FastAPI service will be configured to run securely, and communication between PHP and FastAPI will be protected (e.g., by ensuring it's not publicly exposed if running on a private network).

## 6. Compatibility and Reusability

*   **Existing Backend:** The `process_verification.php` script will be extended and adapted, reusing existing functions for database interaction and file saving.
*   **FastAPI/AI Pipeline:** The existing FastAPI endpoints and AI logic (`ai/identity/api_endpoint.py`, `ai/identity/verify_identity.py`) will be fully utilized.
*   **Uploads:** The existing `uploads/verifications` directory structure will be maintained.
*   **XAMPP/Windows:** The design will ensure compatibility with the XAMPP/Windows environment, particularly concerning file paths and FFmpeg configurations.
*   **Massango Visual Pattern:** The new page will adhere to the current Massango visual style by reusing existing CSS variables, components, and general layout patterns.
*   **Modular Code:** The new frontend code will be modular, using JavaScript functions and potentially classes to manage different steps and UI components, ensuring maintainability and scalability.

## 7. Refactoring the Old Modal

The old verification modal (`includes/verificationmodal.php` and the inline version in `public/settings.php`) will be handled as follows:

*   **Redirection:** All calls or triggers to the old modal will be modified to redirect the user to the new `public/verification/index.php` page. This ensures a smooth transition and deprecates the old implementation without breaking existing links.
*   **Gradual Removal:** Once the new page is fully functional and stable, the old modal files can be gradually removed from the codebase.

## 8. Deliverables

*   `public/verification/index.php` (new dedicated verification page)
*   Updated `public/process_verification.php` (backend logic for new page)
*   Modifications to `public/settings.php` and other relevant files to redirect to the new page.
*   Any new CSS/JS files required for the new page, integrated into the existing asset structure.
*   Updated `ai/identity/php_trigger.php` (if necessary, to align with new data flow).
*   Documentation of changes and usage.

This design aims to deliver a robust, secure, and user-friendly identity verification experience that aligns with the premium and modern aesthetic of Massango.
