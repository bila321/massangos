<?php
// public/notifications.php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

(new \Massango\Controllers\NotificationController($pdo))->handle();
