<?php
// public/create-post.php — entry point, zero lógica
header('Content-Type: text/html; charset=UTF-8');

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

SecurityManager::initSecurity();

$controller = new \Massango\Controllers\CreatePostController($pdo, get_current_user_id());
$controller->show();
