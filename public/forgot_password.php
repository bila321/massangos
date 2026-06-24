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

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

(new \Massango\Controllers\ForgotPasswordController($pdo))->handle();
