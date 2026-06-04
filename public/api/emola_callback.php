<?php
// public/api/emola_callback.php
header('Content-Type: application/json');
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Services\PaymentService;

$input = file_get_contents('php://input');
error_log("e-Mola Callback: " . $input);

$data = json_decode($input, true);

// Ajustar conforme a resposta real da API e-Mola
if (isset($data['status']) && ($data['status'] === 'SUCCESS' || $data['status'] === '00')) {
    $transactionId = $data['transactionId'] ?? 'EMOLA-' . time();
    $reference = $data['reference'] ?? '';

    if (preg_match('/(SALE|STARS)-(\d+)-/', $reference, $matches)) {
        $saleId = (int)$matches[2];

        $paymentService = new PaymentService($pdo);
        $paymentService->confirmPayment($saleId, $transactionId, $reference);

        echo json_encode(['status' => 'success']);
        exit();
    }
}

echo json_encode(['status' => 'ignored']);
