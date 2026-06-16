<?php
// public/actions/verification.php
// Delega ao ficheiro original durante refactor incremental.
// TODO: migrar logica para VerificationController.
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/face_api_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../process_verification_logic.php';
