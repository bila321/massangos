<?php
// public/followers.php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';
SecurityManager::initSecurity();

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\FollowListController($pdo, 'followers'))->handle();
