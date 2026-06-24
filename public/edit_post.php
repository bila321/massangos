<?php

/**
 * public/edit_post.php
 *
 * Ponto de entrada — bootstrap + Controller.
 * Suporta modo página completa e modo modal AJAX (?ajax=1).
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();


$pdo = \Massango\Core\Database::getInstance();

(new \Massango\Controllers\EditPostController($pdo))->show();
