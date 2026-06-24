<?php
// public/buy_stars.php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';

(new \Massango\Controllers\BuyStarsController($pdo))->handle();
