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

SecurityManager::initSecurity();


(new \Massango\Controllers\HistoryController($pdo))->show();
