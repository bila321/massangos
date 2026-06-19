<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/db.php';
echo isset($pdo) ? 'PDO OK' : 'PDO NULL';
