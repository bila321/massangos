<?php
// public/api/mpesa_callback.php
header('Content-Type: application/json');
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Services\PaymentService;

// Log the callback for debugging
$input = file_get_contents('php://input');
error_log("M-Pesa Callback: " . $input);

$data = json_decode($input, true);

if (isset($data['output_ResponseCode']) && $data['output_ResponseCode'] === 'INS-0') {
    $transactionId = $data['output_TransactionID'] ?? 'MPESA-' . time();
    $thirdPartyReference = $data['output_ThirdPartyReference'] ?? '';

    // Extrair o ID da venda da referência (formato: SALE-ID-TIMESTAMP ou STARS-ID-...)
    if (preg_match('/(SALE|STARS)-(\d+)-/', $thirdPartyReference, $matches)) {
        $saleId = (int)$matches[2];

        $paymentService = new PaymentService($pdo);
        $paymentService->confirmPayment($saleId, $transactionId, $thirdPartyReference);

        echo json_encode(['status' => 'success']);
        exit();
    }
}

echo json_encode(['status' => 'ignored']);
