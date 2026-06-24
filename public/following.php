<?php
// public/following.php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\FollowListController($pdo, 'following'))->handle();
