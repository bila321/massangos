<?php
// public/process_payment_stars.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\PaymentService;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'buy_stars.php');
    exit();
}

$buyer_id = get_current_user_id();
$stars = (int)$_POST['stars'];
$duration = $_POST['duration'];
$amount = (float)$_POST['amount'];
$payment_method = $_POST['payment_method'] ?? '';
$phone_number = $_POST['phone_number'] ?? '';

// Validacao do numero de telefone
$phone_number = preg_replace('/\D/', '', $phone_number);
if (strlen($phone_number) === 9) {
    $phone_number = '258' . $phone_number;
}

if (strlen($phone_number) !== 12 || !str_starts_with($phone_number, '258')) {
    set_message("Numero de telefone invalido.", "danger");
    redirect(BASE_URL . "checkout_stars.php?stars=$stars&duration=$duration");
}

try {
    $paymentService = new PaymentService($pdo);
    $result = $paymentService->createStarsSale(
        $buyer_id,
        $stars,
        $duration,
        $amount,
        $payment_method,
        $phone_number
    );

    if ($result['success']) {
        set_message($result['message'], "success");
        redirect(BASE_URL . "waiting_payment.php?sale_id=" . $result['sale_id']);
    } else {
        set_message($result['message'], "danger");
        redirect(BASE_URL . "checkout_stars.php?stars=$stars&duration=$duration");
    }
} catch (Exception $e) {
    set_message("Erro ao processar pagamento: " . $e->getMessage(), "danger");
    redirect(BASE_URL . "checkout_stars.php?stars=$stars&duration=$duration");
}
