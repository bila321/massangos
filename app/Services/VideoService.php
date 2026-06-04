<?php

namespace Massango\Services;

use PDO;
use Exception;
use Massango\Models\Video;
use Massango\Models\FeedItem;
use Massango\Models\Photo;
use Massango\Models\Notification;
use Massango\Models\User;

/**
 * VideoService
 * 
 * Centraliza a lógica de negócio para vídeos:
 * - Criação de vídeos com upload e thumbnail
 * - Apagar vídeos com notificação
 */
class VideoService
{
    private PDO $pdo;
    private int $currentUserId;
    private bool $isAdmin;

    public function __construct(PDO $pdo, int $currentUserId)
    {
        $this->pdo = $pdo;
        $this->currentUserId = $currentUserId;
        $this->isAdmin = isset($_SESSION['admin_id']);
    }

    /**
     * Cria um novo vídeo com upload e thumbnail.
     */
    public function createVideo(array $data, array $videoFile): array
    {
        $caption = trim($data['caption'] ?? $data['video_description'] ?? '');
        $isForSale = isset($data['is_for_sale']) && $data['is_for_sale'] == '1';
        $price = isset($data['price']) ? (float)$data['price'] : 0.00;
        $categoria = trim($data['categoria'] ?? 'normal');
        $subcategoria = trim($data['subcategoria'] ?? '');

        if ($categoria === '18+' && empty($subcategoria)) {
            return ['success' => false, 'message' => 'A subcategoria é obrigatória para conteúdo 18+.'];
        }

        $this->pdo->beginTransaction();

        $uploadedVideoFile = null;
        $videoPath = null;
        $thumbnailPath = null;
        $durationSeconds = null;

        try {
            // Upload de vídeo
            if (isset($videoFile['tmp_name']) && $videoFile['error'] === UPLOAD_ERR_OK) {
                MediaProcessor::validateUpload($videoFile, ALLOWED_VIDEO_TYPES, MAX_UPLOAD_SIZE * 5);

                $uploadDir = UPLOAD_DIR . 'videos/';
                $thumbnailDir = UPLOAD_DIR . 'videos/thumbnails/'; // Confirmado: storage/uploads/videos/thumbnails/

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0775, true);

                $fileExtension = pathinfo($videoFile['name'], PATHINFO_EXTENSION);
                $uniqueId = uniqid('video_');
                $videoFileName = $uniqueId . '.' . $fileExtension;
                $videoDestination = $uploadDir . $videoFileName;

                if (!move_uploaded_file($videoFile['tmp_name'], $videoDestination)) {
                    throw new Exception("Falha ao mover vídeo uploadado.");
                }

                $videoPath = 'videos/' . $videoFileName;
                $uploadedVideoFile = $videoDestination;

                // Gerar thumbnail
                $thumbName = $uniqueId . '_thumb.jpg';
                $thumbDest = $thumbnailDir . $thumbName;

                try {
                    if (MediaProcessor::generateVideoThumbnail($videoDestination, $thumbDest)) {
                        $thumbnailPath = 'videos/thumbnails/' . $thumbName;
                    } else {
                        error_log("VideoService: Falha ao gerar thumbnail para $videoDestination, mas continuando o post.");
                    }
                } catch (Exception $thumbEx) {
                    error_log("VideoService: Exceção na thumbnail: " . $thumbEx->getMessage());
                }

                // Extrair duração
                $durationSeconds = $this->extractVideoDuration($videoDestination);
            }

            // Se não houver legenda, usar título
            if (empty($caption)) {
                $caption = trim($data['video_title'] ?? 'Vídeo sem título');
            }

            // Validação de venda
            $isApproved = $categoria !== '18+' ? 1 : 0;
            $showInFeed = 1;

            if ($isForSale) {
                $userStars = $this->getUserStars();
                $validation = PricingRuleService::validateForSale($this->pdo, 'video', $userStars, $price, []);

                if (!$validation['is_valid']) {
                    throw new Exception(implode(" ", $validation['errors']));
                }

                if ($userStars >= 3 && isset($data['show_in_feed'])) {
                    $showInFeed = (int)$data['show_in_feed'];
                }
            }

            // Criar vídeo
            $videoId = Video::createVideo(
                $this->pdo,
                $this->currentUserId,
                $videoPath,
                $thumbnailPath,
                $caption,
                $durationSeconds,
                $isForSale,
                $price,
                $isApproved,
                $showInFeed,
                $categoria,
                $subcategoria
            );

            if (!$videoId) {
                throw new Exception("Erro ao salvar o vídeo no banco de dados.");
            }

            // Adicionar ao feed
            if (!FeedItem::createFeedItem($this->pdo, $this->currentUserId, 'video', $videoId, $showInFeed)) {
                throw new Exception("Falha ao adicionar vídeo ao feed global.");
            }

            $this->pdo->commit();

            // Queue IA
            if ($videoPath) {
                $this->queueForAIProcessing($videoId, $videoPath);
            }

            return [
                'success' => true,
                'video_id' => $videoId,
                'message' => $isApproved ? "Vídeo publicado com sucesso!" : "Vídeo enviado para aprovação."
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Limpar ficheiros uploadados
            if ($uploadedVideoFile && file_exists($uploadedVideoFile)) {
                unlink($uploadedVideoFile);
            }
            if ($thumbnailPath && file_exists(UPLOAD_DIR . $thumbnailPath)) {
                unlink(UPLOAD_DIR . $thumbnailPath);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Apaga um vídeo se o utilizador tiver permissão.
     */
    public function deleteVideo(int $videoId): array
    {
        $videoData = Video::getVideoById($this->pdo, $videoId);

        if (!$videoData) {
            return ['success' => false, 'message' => 'Vídeo não encontrado.'];
        }

        $ownerId = (int)$videoData['user_id'];
        $canDelete = $this->isAdmin || $ownerId === $this->currentUserId;

        if (!$canDelete) {
            return ['success' => false, 'message' => 'Você não tem permissão para apagar este vídeo.'];
        }

        if (Video::deleteVideo($this->pdo, $videoId, $ownerId)) {
            // Notificar dono se admin apagou
            if ($this->isAdmin && $ownerId !== $this->currentUserId) {
                $msg = "O seu vídeo foi bloqueado porque infringiu algumas regras da rede social.";
                Notification::createNotification(
                    $this->pdo,
                    $ownerId,
                    $msg,
                    null,
                    null,
                    'video_blocked',
                    $videoId
                );
            }

            return ['success' => true, 'message' => 'Vídeo apagado com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao apagar o vídeo.'];
    }

    /**
     * Extrai a duração de um vídeo em segundos.
     */
    private function extractVideoDuration(string $videoPath): ?int
    {
        try {
            // Tentar usar ffprobe se disponível
            $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
            $output = shell_exec($cmd);

            if ($output !== null) {
                return (int)round((float)trim($output));
            }
        } catch (Exception $e) {
            error_log("Erro ao extrair duração do vídeo: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtém as estrelas do utilizador atual.
     */
    private function getUserStars(): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT stars FROM users WHERE id = ?");
            $stmt->execute([$this->currentUserId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Adiciona vídeo à fila de processamento IA.
     */
    private function queueForAIProcessing(int $videoId, string $videoPath): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO media_queue (post_id, file_path, item_type, status) 
                VALUES (?, ?, 'video', 'pending')
            ");
            $stmt->execute([$videoId, 'uploads/' . $videoPath]);
        } catch (Exception $e) {
            error_log("Erro ao adicionar à fila IA: " . $e->getMessage());
        }
    }
}
