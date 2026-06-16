<?php
// app/Controllers/PaymentController.php
namespace Massango\Controllers;

use Massango\Services\PaymentService;

class PaymentController
{
    private \PDO $pdo;
    private int  $buyerId;

    public function __construct(\PDO $pdo, int $buyerId)
    {
        $this->pdo     = $pdo;
        $this->buyerId = $buyerId;
    }

    public function handleContent(array $post): array
    {
        $phone = $this->normalizePhone($post['phone_number'] ?? '');
        if (!$phone) {
            return ['success' => false, 'message' => 'Numero de telefone invalido. Use 84XXXXXXX ou 25884XXXXXXX.'];
        }
        $service = new PaymentService($this->pdo);
        return $service->createSale(
            $this->buyerId,
            (int)($post['seller_id']   ?? 0),
            $post['content_type']       ?? null,
            (int)($post['content_id']  ?? 0),
            (float)($post['amount']    ?? 0),
            $post['payment_method']     ?? '',
            $phone
        );
    }

    public function handleStars(array $post): array
    {
        $phone = $this->normalizePhone($post['phone_number'] ?? '');
        if (!$phone) {
            return ['success' => false, 'message' => 'Numero de telefone invalido.'];
        }
        $service = new PaymentService($this->pdo);
        return $service->createStarsSale(
            $this->buyerId,
            (int)($post['stars']       ?? 0),
            $post['duration']           ?? '',
            (float)($post['amount']    ?? 0),
            $post['payment_method']     ?? '',
            $phone
        );
    }

    private function normalizePhone(string $raw): ?string
    {
        $phone = preg_replace('/\D/', '', $raw);
        if (strlen($phone) === 9) {
            $phone = '258' . $phone;
        }
        if (strlen($phone) === 12 && str_starts_with($phone, '258')) {
            return $phone;
        }
        return null;
    }
}
