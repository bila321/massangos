<?php
// public/followers.php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\FollowListController($pdo, 'followers'))->handle();
