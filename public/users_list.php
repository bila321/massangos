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

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

(new \Massango\Controllers\UsersListController($pdo))->show();
