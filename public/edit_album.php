<?php

/**
 * public/edit_album.php
 *
 * Ponto de entrada — bootstrap + Controller.
 * Suporta modo página completa e modo modal AJAX (?ajax=1).
 *
 * Nota: o original não chamava SecurityManager::initSecurity() —
 * mantido assim para preservar o comportamento exato.
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

$pdo = \Massango\Core\Database::getInstance();

(new \Massango\Controllers\EditAlbumController($pdo))->show();
