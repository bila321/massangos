<?php
// app/Controllers/AlbumController.php
namespace Massango\Controllers;

use Massango\Services\AlbumService;
use Massango\Models\AlbumPartner;
use Massango\Models\Notification;

class AlbumController
{
    private \PDO $pdo;
    private int  $userId;
    private AlbumService $albumService;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo          = $pdo;
        $this->userId       = $userId;
        $this->albumService = new AlbumService($pdo, $userId);
    }

    public function handle(array $post, array $files): array
    {
        $action = $post['action'] ?? '';
        if ($action === 'delete_album') {
            $albumId = (int)($post['album_id'] ?? 0);
            return $this->albumService->deleteAlbum($albumId);
        }
        return $this->albumService->createAlbum($post, $files['images'] ?? []);
    }

    public function handlePartnership(array $post): array
    {
        $action    = $post['action']      ?? '';
        $partnerId = (int)($post['partner_id'] ?? 0);

        if ($partnerId <= 0) {
            return ['success' => false, 'message' => 'ID de parceria invalido.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT ap.id, ap.album_id, ap.user_id,
                   a.user_id as creator_id, a.name as album_name
            FROM album_partners ap
            JOIN albums a ON ap.album_id = a.id
            WHERE ap.id = ?
        ");
        $stmt->execute([$partnerId]);
        $partnership = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$partnership) {
            return ['success' => false, 'message' => 'Parceria nao encontrada.'];
        }
        if ((int)$partnership['user_id'] !== $this->userId) {
            return ['success' => false, 'message' => 'Sem permissao.'];
        }

        $albumId   = (int)$partnership['album_id'];
        $creatorId = (int)$partnership['creator_id'];
        $albumName = $partnership['album_name'];

        $stmtUser = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtUser->execute([$this->userId]);
        $username = $stmtUser->fetchColumn() ?: 'Alguem';

        if ($action === 'accept') {
            if (!AlbumPartner::acceptPartnership($this->pdo, $partnerId)) {
                return ['success' => false, 'message' => 'Erro ao aceitar a parceria.'];
            }
            Notification::createNotification(
                $this->pdo, $creatorId,
                "@{$username} aceitou a parceria do album '{$albumName}'.",
                BASE_URL . "view_album.php?id={$albumId}",
                $this->userId, 'album_partnership_accepted', $partnerId
            );
            return ['success' => true, 'message' => "Voce aceitou a parceria do album '{$albumName}'!"];
        }

        if ($action === 'reject') {
            if (!AlbumPartner::rejectPartnership($this->pdo, $partnerId)) {
                return ['success' => false, 'message' => 'Erro ao recusar a parceria.'];
            }
            Notification::createNotification(
                $this->pdo, $creatorId,
                "@{$username} recusou a parceria do album '{$albumName}'.",
                BASE_URL . "view_album.php?id={$albumId}",
                $this->userId, 'album_partnership_rejected', $partnerId
            );
            return ['success' => true, 'message' => "Voce recusou a parceria do album '{$albumName}'."];
        }

        return ['success' => false, 'message' => 'Acao invalida.'];
    }
}
