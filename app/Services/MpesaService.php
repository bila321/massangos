<?php

namespace Massango\Services;

use Exception;

class MpesaService
{

    private $apiKey;
    private $publicKey;
    private $serviceProviderCode;
    private $host;

    public function __construct()
    {
        $this->apiKey = MPESA_API_KEY;
        $this->publicKey = MPESA_PUBLIC_KEY;
        $this->serviceProviderCode = MPESA_SERVICE_PROVIDER_CODE;
        $this->host = MPESA_API_HOST;
    }

    /**
     * Gera o token de autorização para a API do M-Pesa.
     */
    private function generateBearerToken(): string
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($this->publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";

        if (!openssl_public_encrypt($this->apiKey, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new Exception("Erro ao encriptar a API Key.");
        }

        return base64_encode($encrypted);
    }

    /**
     * Inicia um pagamento C2B (Customer to Business).
     */
    public function initiateC2BPayment(string $transactionReference, string $customerMSISDN, float $amount, string $thirdPartyReference): array
    {
        $token = $this->generateBearerToken();

        $url = "https://{$this->host}:18352/ipg/v1x/c2bPayment/singleStage/";

        $data = [
            'input_TransactionReference' => $transactionReference,
            'input_CustomerMSISDN' => $customerMSISDN,
            'input_Amount' => number_format($amount, 2, '.', ''),
            'input_ThirdPartyReference' => $thirdPartyReference,
            'input_ServiceProviderCode' => $this->serviceProviderCode
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "Origin: developer.mpesa.vm.co.mz"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para sandbox/desenvolvimento

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $error = json_decode($response, true);
            throw new Exception("Erro na API M-Pesa ({$httpCode}): " . ($error['output_ResponseDesc'] ?? 'Erro desconhecido'));
        }

        return json_decode($response, true);
    }
}

