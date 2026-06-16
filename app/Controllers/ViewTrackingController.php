<?php
// app/Controllers/ViewTrackingController.php
namespace Massango\Controllers;

class ViewTrackingController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function track(array $post, array $session, string $remoteAddr): array
    {
        $itemType   = $post['item_type'] ?? '';
        $itemId     = (int)($post['item_id'] ?? 0);

        if ($itemId <= 0 || !in_array($itemType, ['video', 'album'], true)) {
            return ['success' => false, 'error' => 'Parametros invalidos.'];
        }

        $table      = $itemType === 'video' ? 'videos' : 'albums';
        $userId     = (int)($session['user_id'] ?? 0);
        $ip         = trim(explode(',', $remoteAddr)[0]);
        $now        = time();
        $sessionKey = "last_view_{$itemType}_{$itemId}";

        if (isset($session[$sessionKey]) && ($now - $session[$sessionKey]) <= 3600) {
            return ['success' => true, 'already_counted' => true, 'message' => 'Ja contado recentemente.'];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE id = ? AND is_approved = 1 LIMIT 1");
        $stmt->execute([$itemId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Conteudo nao encontrado ou nao aprovado.'];
        }

        $viewerParam  = $userId > 0 ? $userId : $ip;
        $viewerClause = $userId > 0
            ? 'user_id = ? AND item_type = ? AND item_id = ?'
            : 'user_id = 0 AND ip_address = ? AND item_type = ? AND item_id = ?';

        $logStmt = $this->pdo->prepare(
            "SELECT id FROM view_logs WHERE {$viewerClause}
             AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1"
        );
        $logStmt->execute([$viewerParam, $itemType, $itemId]);
        if ($logStmt->fetch()) {
            $_SESSION[$sessionKey] = $now;
            return ['success' => true, 'already_counted' => true];
        }

        $this->pdo->beginTransaction();
        $update = $this->pdo->prepare("UPDATE {$table} SET views_count = views_count + 1 WHERE id = ?");
        $update->execute([$itemId]);
        if ($update->rowCount() === 0) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => 'Nao foi possivel registar a visualizacao.'];
        }
        $this->pdo->prepare(
            "INSERT INTO view_logs (user_id, ip_address, item_type, item_id) VALUES (?, ?, ?, ?)"
        )->execute([$userId, $ip, $itemType, $itemId]);
        $this->pdo->commit();

        $_SESSION[$sessionKey] = $now;

        $cntStmt = $this->pdo->prepare("SELECT views_count FROM {$table} WHERE id = ? LIMIT 1");
        $cntStmt->execute([$itemId]);
        $newCount = (int)($cntStmt->fetchColumn() ?: 0);

        return ['success' => true, 'message' => 'Visualizacao contada.', 'new_count' => $newCount];
    }
}
