<?php

/**
 * public/history.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/HistoryService.php
 * Orquestração      → app/Controllers/HistoryController.php
 * Templates HTML    → includes/views/history/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\HistoryController($pdo))->show();
