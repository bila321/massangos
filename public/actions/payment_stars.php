<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';
SecurityManager::initSecurity();

use Massango\Controllers\PaymentController;

if (!is_logged_in()) { redirect(BASE_URL . 'login.php'); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(BASE_URL . 'buy_stars.php'); exit(); }

$stars    = (int)($_POST['stars']    ?? 0);
$duration = $_POST['duration']       ?? '';

try {
    $ctrl   = new PaymentController($pdo, get_current_user_id());
    $result = $ctrl->handleStars($_POST);
} catch (Exception $e) {
    error_log('[PaymentController::stars] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro ao processar pagamento.'];
}

if ($result['success']) {
    set_message($result['message'], 'success');
    redirect(BASE_URL . 'waiting_payment.php?sale_id=' . ($result['sale_id'] ?? ''));
} else {
    set_message($result['message'], 'danger');
    redirect(BASE_URL . "checkout_stars.php?stars={$stars}&duration={$duration}");
}
