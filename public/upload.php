<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirecionar para a home, pois agora as publicações são via modal
header('Location: ' . BASE_URL . 'index.php');
exit;
?>
