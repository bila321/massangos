<?php
/**
 * public/forgot_password.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/PasswordResetService.php
 * Orquestração      → app/Controllers/ForgotPasswordController.php
 * Templates HTML    → includes/views/auth/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\ForgotPasswordController($pdo))->handle();
