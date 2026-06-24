<?php

/**
 * public/users_list.php
 *
 * Ponto de entrada — bootstrap + Controller.
 * Suporta modo página completa e modo modal AJAX (?ajax=1).
 *
 * Lógica de negócio → app/Services/UsersListService.php
 * Orquestração      → app/Controllers/UsersListController.php
 * Templates HTML    → includes/views/users_list/
 */
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

(new \Massango\Controllers\UsersListController($pdo))->show();
