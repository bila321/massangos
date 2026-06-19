<?php

namespace Massango\Services;

use PDO;
use Exception;
use Massango\Models\Post;
use Massango\Models\FeedItem;
use Massango\Models\User;
use Massango\Models\Notification;

class PostService
{
    private PDO $pdo;
    private int $currentUserId;

    public function __construct(PDO $pdo, int $currentUserId)
    {
        $this->pdo = $pdo;
        $this->currentUserId = $currentUserId;
    }

    /**
     * Cria um novo post com upload de imagem opcional.
     */
    public function createPost(array $data, array $files = []): array
    {
        try {
            $content = $data['content'] ?? '';
            $postType = $data['post_type'] ?? 'text';
            $categoria = !empty($data['categoria']) ? $data['categoria'] : 'normal';
            $subcategoria = $data['subcategoria'] ?? '';
            $isForSale = !empty($data['is_for_sale']);
            $price = (float)($data['price'] ?? 0);
            $showInFeed = !isset($data['show_in_feed']) || $data['show_in_feed'];

            // Correção de Regra de Negócio: Garante consistência de preços
            if ($isForSale && $price <= 0) {
                throw new Exception("O preço deve ser superior a 0 MT para itens à venda.");
            }
            if (!$isForSale) {
                $price = 0.0;
            }

            // Validação de categoria 18+
            if ($categoria === '18+' && empty($subcategoria)) {
                throw new Exception("A subcategoria é obrigatória para conteúdo 18+.");
            }

            $this->pdo->beginTransaction();

            // Upload de imagem se fornecida
            $imagePath = null;
            $thumbnailPath = null;
            $uploadResult = [];

            if (!empty($files['post_image']['tmp_name'])) {
                $uploadResult = $this->uploadImage($files['post_image']);
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }
                $imagePath = $uploadResult['image_path'];
                $thumbnailPath = $uploadResult['thumbnail_path'];
            }

            $isApproved = $categoria !== '18+' ? 1 : 0;

            $stmt = $this->pdo->prepare("
                INSERT INTO posts
                (user_id, content, image_path, thumbnail_path, post_type, is_approved, show_in_feed, is_for_sale, price, categoria, subcategoria)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $this->currentUserId,
                $content,
                $imagePath,
                $thumbnailPath,
                $postType,
                $isApproved,
                $showInFeed ? 1 : 0,
                $isForSale ? 1 : 0,
                $price,
                $categoria,
                $subcategoria
            ]);

            $postId = (int)$this->pdo->lastInsertId();

            if (!FeedItem::createFeedItem($this->pdo, $this->currentUserId, 'post', $postId, $showInFeed ? 1 : 0)) {
                throw new Exception("Erro ao adicionar ao feed.");
            }

            $this->pdo->commit();

            // Adicionar à fila de IA para processamento
            if ($imagePath) {
                $this->queueForAIProcessing($postId, $imagePath);
            }

            return [
                'success' => true,
                'post_id' => $postId,
                'message' => $isApproved ? "Postagem criada com sucesso!" : "Postagem enviada para aprovação."
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Proteção I/O: Silencia avisos do sistema operacional no unlink
            if (!empty($uploadResult['uploaded_file']) && file_exists($uploadResult['uploaded_file'])) {
                @unlink($uploadResult['uploaded_file']);
            }
            if (!empty($uploadResult['thumbnail_file']) && file_exists($uploadResult['thumbnail_file'])) {
                @unlink($uploadResult['thumbnail_file']);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cria um repost e notifica o autor original.
     */
    public function repost(int $originalPostId): array
    {
        try {
            $originalPost = Post::getPostById($this->pdo, $originalPostId);

            if (!$originalPost) {
                return ['success' => false, 'message' => 'Publicação original não encontrada.'];
            }

            // Se já for um repost, usar o original para evitar herança infinita
            if (!empty($originalPost['is_repost']) && !empty($originalPost['original_post_id'])) {
                $originalPostId = (int)$originalPost['original_post_id'];
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO posts
                (user_id, content, post_type, is_approved, show_in_feed, is_repost, original_post_id)
                VALUES (?, '', 'text', 1, 1, 1, ?)
            ");

            if (!$stmt->execute([$this->currentUserId, $originalPostId])) {
                throw new Exception("Erro ao criar repost.");
            }

            $newPostId = (int)$this->pdo->lastInsertId();

            if (!FeedItem::createFeedItem($this->pdo, $this->currentUserId, 'post', $newPostId, 1)) {
                throw new Exception("Erro ao adicionar repost ao feed.");
            }

            // Notificar autor original
            $this->notifyRepost($originalPostId, $newPostId);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'Repost realizado com sucesso!'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Faz upload de imagem e gera thumbnail.
     */
    private function uploadImage(array $file): array
    {
        $uploadDir = UPLOAD_DIR;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erro no upload da imagem.'];
        }

        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Tipo de imagem não suportado.'];
        }

        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Imagem muito grande (máx 10MB).'];
        }

        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $ext = $mimeToExt[$file['type']] ?? 'jpg';
        $filename = uniqid('img_', true) . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Falha ao mover imagem uploadada.'];
        }

        // Gerar thumbnail
        $thumbFilename = 'thumbnails/' . $filename;
        $thumbPath = $uploadDir . $thumbFilename;

        $thumbResult = MediaProcessor::generateImageThumbnail($filepath, $thumbPath, 800, 800, 85);

        if (!$thumbResult) {
            $thumbFilename = $filename;
        }

        return [
            'success' => true,
            'image_path' => $filename,
            'thumbnail_path' => $thumbFilename,
            'uploaded_file' => $filepath,
            'thumbnail_file' => $thumbPath
        ];
    }

    /**
     * Adiciona imagem à fila de processamento IA.
     */
    private function queueForAIProcessing(int $postId, string $imagePath): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO media_queue (post_id, file_path, item_type, status)
                VALUES (?, ?, 'image', 'pending')
            ");
            $stmt->execute([$postId, 'uploads/' . $imagePath]);
        } catch (Exception $e) {
            error_log("Erro ao adicionar à fila IA: " . $e->getMessage());
        }
    }


    // -----------------------------------------------------------------------
    // Suporte ao formulário de criação (PostController::showCreate)
    // -----------------------------------------------------------------------

    /**
     * Busca as estatísticas do utilizador relevantes para as regras de venda.
     * Separado do User::getUserById para não misturar concerns no Model.
     */
    public function getUserStats(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT stars, balance, is_verified_creator FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'stars'               => 0,
            'balance'             => 0,
            'is_verified_creator' => 0,
        ];
    }

    /**
     * Calcula as permissões e limites de venda com base nas estrelas do utilizador.
     * Centralizado aqui para não duplicar a lógica na view ou no controller.
     */
    public function getSaleRules(array $userStats): array
    {
        $stars = (int) ($userStats['stars'] ?? 0);

        return [
            'can_sell_post'   => $stars >= 1,
            'can_sell_video'  => $stars >= 2,
            'can_sell_album'  => $stars >= 3,
            'max_post_price'  => 1000.00,
            'max_video_price' => 5000.00,
            'max_album_price' => 10000.00,
        ];
    }

    /**
     * Notifica autor original do repost.
     */
    private function notifyRepost(int $originalPostId, int $newPostId): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt->execute([$originalPostId]);
            $originalPostData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($originalPostData && $originalPostData['user_id'] != $this->currentUserId) {
                $repostUser = User::getUserById($this->pdo, $this->currentUserId);
                $repostUsername = $repostUser ? $repostUser['username'] : 'Utilizador';

                Notification::createNotification(
                    $this->pdo,
                    $originalPostData['user_id'],
                    "@$repostUsername repostou sua publicação!",
                    null,
                    $this->currentUserId,
                    'post_reposted',
                    $newPostId
                );
            }
        } catch (Exception $e) {
            error_log("Erro ao notificar repost: " . $e->getMessage());
        }
    }
}
