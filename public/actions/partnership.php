<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';
SecurityManager::initSecurity();

use Massango\Controllers\AlbumController;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
    exit();
}

try {
    $ctrl   = new AlbumController($pdo, get_current_user_id());
    $result = $ctrl->handlePartnership($_POST);
} catch (Exception $e) {
    error_log('[AlbumController::partnership] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro ao processar parceria.'];
}

set_message($result['message'], $result['success'] ? 'success' : 'danger');
redirect(BASE_URL . 'notifications.php');
