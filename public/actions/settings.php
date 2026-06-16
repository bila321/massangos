<?php
// public/actions/settings.php
// Delega ao ficheiro original durante refactor incremental.
// TODO: migrar logica para SettingsController.
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../process_settings_logic.php';
