<?php

/**
 * public/edit_video.php
 *
 * Ponto de entrada — bootstrap + Controller.
 * Suporta modo página completa e modo modal AJAX (?ajax=1).
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

$pdo = \Massango\Core\Database::getInstance();

(new \Massango\Controllers\EditVideoController($pdo))->show();
