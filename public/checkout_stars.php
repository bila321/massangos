<?php
// public/checkout_stars.php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\CheckoutController($pdo, 'stars'))->handle();
