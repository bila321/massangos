<?php

namespace Massango\Services;

use Exception;

class EmolaService
{

    private string $apiUrl;
    private string $apiKey;
    private string $merchantId;

    public function __construct()
    {
        $this->apiUrl = EMOLA_API_URL;
        $this->apiKey = EMOLA_API_KEY;
        $this->merchantId = EMOLA_MERCHANT_ID;
    }

    /**
     * Inicia um pagamento via e-Mola.
     */
    public function initiatePayment(string $customerNumber, float $amount, string $reference): array
    {
        // Exemplo de implementação genérica para e-Mola
        // O usuário deverá ajustar conforme a documentação oficial da Movitel

        $data = [
            'merchantId' => $this->merchantId,
            'customerNumber' => $customerNumber,
            'amount' => $amount,
            'reference' => $reference,
            'callbackUrl' => BASE_URL . 'api/emola_callback.php'
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erro na API e-Mola ({$httpCode}): " . $response);
        }

        return json_decode($response, true);
    }
}
