<?php

/**
 * public/register.php
 * Bootstrap + Controller.
 */
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';


SecurityManager::initSecurity();

(new \Massango\Controllers\RegisterController($pdo))->handle();
