<?php
// public/notifications.php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
SecurityManager::initSecurity();

(new \Massango\Controllers\NotificationController($pdo))->handle();
