<?php

/**
 * public/saved.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/SavedService.php
 * Orquestração      → app/Controllers/SavedController.php
 * Templates HTML    → includes/views/saved/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

ob_start();

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

ob_end_clean();

require_once __DIR__ . '/../vendor/autoload.php';
SecurityManager::initSecurity();

(new \Massango\Controllers\SavedController($pdo))->show();
