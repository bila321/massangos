<?php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();

// Redirecionar para a home, pois agora as publicações são via modal
header('Location: ' . BASE_URL . 'index.php');
exit;
