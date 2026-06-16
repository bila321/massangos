<?php
// app/Controllers/NotificationController.php
namespace Massango\Controllers;

use Massango\Models\Notification;

class NotificationController
{
    private \PDO $pdo;
    private int  $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    public function handle(array $post): array
    {
        $action = $post['action'] ?? '';

        switch ($action) {
            case 'mark_read':
                $id = (int)($post['notification_id'] ?? 0);
                if ($id <= 0) {
                    return ['success' => false, 'message' => 'ID invalido.'];
                }
                $ok = Notification::markAsRead($this->pdo, $id, $this->userId);
                return $ok
                    ? ['success' => true,  'message' => 'Notificacao marcada como lida.']
                    : ['success' => false, 'message' => 'Erro ao marcar notificacao.'];

            case 'clear_read':
                $ok = Notification::clearReadNotifications($this->pdo, $this->userId);
                return $ok
                    ? ['success' => true,  'message' => 'Notificacoes lidas removidas.']
                    : ['success' => false, 'message' => 'Erro ao limpar notificacoes.'];

            default:
                return ['success' => false, 'message' => 'Acao invalida.'];
        }
    }

    public function unreadCount(): int
    {
        return (int) Notification::getUnreadNotificationCount($this->pdo, $this->userId);
    }
}
