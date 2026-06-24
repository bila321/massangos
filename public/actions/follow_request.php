<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Controllers\FollowController;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL);
    exit();
}

$redirectUrl = $_POST['redirect_url'] ?? BASE_URL . 'notifications.php';

try {
    $ctrl   = new FollowController($pdo, get_current_user_id());
    $result = $ctrl->handleRequest($_POST);
} catch (Exception $e) {
    error_log('[FollowController] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro interno.'];
}

set_message($result['message'], $result['success'] ? 'success' : 'danger');
redirect($redirectUrl);
