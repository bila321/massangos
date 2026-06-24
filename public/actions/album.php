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
    redirect(BASE_URL . 'index.php');
    exit();
}

$redirectTo = $_POST['redirect_to'] ?? 'index.php';

try {
    $ctrl   = new AlbumController($pdo, get_current_user_id());
    $result = $ctrl->handle($_POST, $_FILES);
} catch (Exception $e) {
    error_log('[AlbumController] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro interno.'];
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
set_message($result['message'], $result['success'] ? 'success' : 'danger');
redirect(BASE_URL . $redirectTo);
