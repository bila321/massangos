<?php
// app/Controllers/BlockController.php
namespace Massango\Controllers;

use Massango\Models\User;

class BlockController
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
        $targetId = (int)($post['user_id'] ?? 0);
        $action   = $post['action'] ?? '';

        if ($targetId <= 0 || !in_array($action, ['block', 'unblock'], true)) {
            return ['success' => false, 'message' => 'Parametros invalidos.'];
        }

        if ($action === 'block') {
            $ok = User::blockUser($this->pdo, $this->userId, $targetId);
            return $ok
                ? ['success' => true,  'message' => 'Utilizador bloqueado.']
                : ['success' => false, 'message' => 'Erro ao bloquear utilizador.'];
        }

        $ok = User::unblockUser($this->pdo, $this->userId, $targetId);
        return $ok
            ? ['success' => true,  'message' => 'Utilizador desbloqueado.']
            : ['success' => false, 'message' => 'Erro ao desbloquear utilizador.'];
    }
}
