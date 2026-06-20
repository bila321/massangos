<?php

/**
 * public/pesquisar.php
 *
 * Ponto de entrada — bootstrap + Controller.
 *
 * Lógica de negócio → app/Services/SearchService.php
 * Orquestração      → app/Controllers/SearchController.php
 * Templates HTML    → includes/views/search/
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\SearchController($pdo))->show();
