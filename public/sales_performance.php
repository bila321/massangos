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

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();

(new \Massango\Controllers\SalesPerformanceController($pdo))->show();
