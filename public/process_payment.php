<?php
// public/process_payment.php
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
    redirect(BASE_URL . 'index.php');
    exit();
}

$buyer_id = get_current_user_id();
$content_type = $_POST['content_type'] ?? null;
$content_id = (int)($_POST['content_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$seller_id = (int)($_POST['seller_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? '';
$phone_number = $_POST['phone_number'] ?? '';

// Validação básica do número de telefone (Moçambique)
$phone_number = preg_replace('/\D/', '', $phone_number);
if (strlen($phone_number) === 9) {
    $phone_number = '258' . $phone_number;
}

if (strlen($phone_number) !== 12 || !str_starts_with($phone_number, '258')) {
    set_message("Número de telefone inválido. Use o formato 84XXXXXXX ou 25884XXXXXXX.", "danger");
    redirect(BASE_URL . "checkout.php?type=$content_type&id=$content_id");
}

try {
    $paymentService = new PaymentService($pdo);
    $result = $paymentService->createSale(
        $buyer_id,
        $seller_id,
        $content_type,
        $content_id,
        $amount,
        $payment_method,
        $phone_number
    );

    if ($result['success']) {
        set_message($result['message'], "success");
        // Redirecionar para uma página de espera ou para o conteúdo
        // Como o pagamento é assíncrono (STK Push), o usuário deve esperar a confirmação
        redirect(BASE_URL . "waiting_payment.php?sale_id=" . $result['sale_id']);
    } else {
        set_message($result['message'], "danger");
        redirect(BASE_URL . "checkout.php?type=$content_type&id=$content_id");
    }
} catch (Exception $e) {
    set_message("Erro ao processar pagamento: " . $e->getMessage(), "danger");
    redirect(BASE_URL . "checkout.php?type=$content_type&id=$content_id");
}
