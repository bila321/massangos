<?php
/**
 * public/sales_performance.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/SalesPerformanceService.php
 * Orquestração      → app/Controllers/SalesPerformanceController.php
 * Templates HTML    → includes/views/sales/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\SalesPerformanceController($pdo))->show();
