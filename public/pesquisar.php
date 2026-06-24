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

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();


(new \Massango\Controllers\SearchController($pdo))->show();
