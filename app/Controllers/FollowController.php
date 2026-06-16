<?php
// app/Controllers/FollowController.php
namespace Massango\Controllers;

use Massango\Models\User;
use Massango\Models\Notification;

class FollowController
{
    private \PDO $pdo;
    private int  $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    public function handleRequest(array $post): array
    {
        $followerId = (int)($post['follower_id'] ?? 0);
        $action     = $post['action'] ?? '';

        $follower = User::getUserById($this->pdo, $followerId);
        if (!$follower) {
            return ['success' => false, 'message' => 'Utilizador nao encontrado.'];
        }

        $currentUser = User::getUserById($this->pdo, $this->userId);
        $username    = $currentUser['username'] ?? 'Alguem';

        if ($action === 'accept') {
            if (!User::acceptFollowRequest($this->pdo, $followerId, $this->userId)) {
                return ['success' => false, 'message' => 'Erro ao aceitar pedido.'];
            }
            Notification::createNotification(
                $this->pdo, $followerId,
                "{$username} aceitou o seu pedido de seguimento.",
                BASE_URL . 'profile.php?id=' . $this->userId,
                $this->userId, 'follow_request_accepted', $this->userId
            );
            return ['success' => true, 'message' => 'Pedido aceite de ' . htmlspecialchars($follower['username']) . '.'];
        }

        if ($action === 'reject') {
            if (!User::rejectFollowRequest($this->pdo, $followerId, $this->userId)) {
                return ['success' => false, 'message' => 'Erro ao rejeitar pedido.'];
            }
            return ['success' => true, 'message' => 'Pedido rejeitado de ' . htmlspecialchars($follower['username']) . '.'];
        }

        return ['success' => false, 'message' => 'Acao invalida.'];
    }
}
