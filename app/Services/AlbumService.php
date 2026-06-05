<?php

namespace Massango\Services;

use PDO;
use Exception;
use Massango\Models\Album;
use Massango\Models\Photo;
use Massango\Models\FeedItem;
use Massango\Models\Notification;
use Massango\Models\User;

/**
 * AlbumService
 * 
 * Centraliza a lógica de negócio para álbuns:
 * - Criação de álbuns com upload de múltiplas fotos
 * - Apagar álbuns com notificação
 */
class AlbumService
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
     * Cria um novo álbum com upload de fotos.
     */
    public function createAlbum(array $data, array $uploadedPhotos): array
    {
        $albumName = trim($data['album_name'] ?? '');
        $albumDescription = trim($data['album_description'] ?? '');
        $categoria = trim($data['categoria'] ?? 'normal');
        $subcategoria = trim($data['subcategoria'] ?? '');
        $isForSale = !empty($data['is_for_sale']);
        $price = (float)($data['price'] ?? 0);
        $coverIndex = isset($data['cover_index']) ? (int)$data['cover_index'] : 0;

        if (empty($albumName)) {
            return ['success' => false, 'message' => 'O nome do álbum não pode estar vazio.'];
        }

        if ($categoria === '18+' && empty($subcategoria)) {
            return ['success' => false, 'message' => 'A subcategoria é obrigatória para conteúdo 18+.'];
        }

        $this->pdo->beginTransaction();

        $uploadedFilesPaths = [];
        $photosToDb = [];
        $coverPhotoUrl = null;
        $thumbnailPath = null;

        try {
            $uploadDir = UPLOAD_DIR . 'albums/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Processar upload de fotos
            // Processar upload de fotos
            if (isset($uploadedPhotos['tmp_name']) && is_array($uploadedPhotos['tmp_name'])) {
                // Criar pasta de thumbnails se não existir
                $thumbsDir = $uploadDir . 'thumbnails/';
                if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

                foreach ($uploadedPhotos['tmp_name'] as $key => $tmpName) {
                    if ($uploadedPhotos['error'][$key] === UPLOAD_ERR_OK) {
                        $fileMock = [
                            'name' => $uploadedPhotos['name'][$key],
                            'type' => $uploadedPhotos['type'][$key],
                            'tmp_name' => $tmpName,
                            'error' => $uploadedPhotos['error'][$key],
                            'size' => $uploadedPhotos['size'][$key]
                        ];

                        MediaProcessor::validateUpload($fileMock, ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE);

                        $fileExtension = pathinfo($uploadedPhotos['name'][$key], PATHINFO_EXTENSION);
                        $uniqueFileName = uniqid('album_photo_') . '.' . $fileExtension;
                        $filePath = $uploadDir . $uniqueFileName;

                        if (!move_uploaded_file($tmpName, $filePath)) {
                            throw new Exception("Falha ao mover foto uploadada.");
                        }

                        $uploadedFilesPaths[] = $filePath;

                        // Caminho para BD: albums/foto.jpg
                        $dbPhotoPath = 'albums/' . $uniqueFileName;
                        $photosToDb[] = $dbPhotoPath;

                        // ── Gerar thumbnail para TODAS as fotos ──
                        $thumbFileName = 'thumbnails/' . $uniqueFileName;
                        $thumbPath = $uploadDir . $thumbFileName;

                        $thumbResult = MediaProcessor::generateImageThumbnail($filePath, $thumbPath, 400, 400, 85);

                        // Guardar caminho do thumbnail para BD (mesmo que falhe, continua)
                        $dbThumbPath = $thumbResult ? 'albums/thumbnails/' . $uniqueFileName : null;

                        // Definir capa e thumbnail do álbum
                        if ($key === $coverIndex || ($coverIndex === 0 && $key === 0)) {
                            $coverPhotoUrl = $dbPhotoPath;
                            $thumbnailPath = $dbThumbPath;
                        }
                    }
                }
            }

            // Validação de venda
            $isApproved = $categoria !== '18+' ? 1 : 0;
            $showInFeed = 1;

            if ($isForSale) {
                $userStars = $this->getUserStars();
                $validation = PricingRuleService::validateForSale($this->pdo, 'album', $userStars, $price, ['photo_count' => count($photosToDb)]);

                if (!$validation['is_valid']) {
                    throw new Exception(implode(" ", $validation['errors']));
                }

                if ($userStars >= 3 && isset($data['show_in_feed'])) {
                    $showInFeed = (int)$data['show_in_feed'];
                }
            }

            // Criar álbum
            $albumId = Album::createAlbum(
                $this->pdo,
                $this->currentUserId,
                $albumName,
                $albumDescription,
                $coverPhotoUrl,
                $thumbnailPath,
                $isForSale,
                $price,
                $isApproved,
                $showInFeed,
                $categoria,
                $subcategoria
            );

            if (!$albumId) {
                throw new Exception("Erro ao criar o álbum no banco de dados.");
            }

            // Adicionar fotos ao álbum (com thumbnail)
            foreach ($photosToDb as $idx => $path) {
                $thumbForDb = 'albums/thumbnails/' . basename($path);

                // Verificar se o thumbnail foi realmente criado
                $thumbFullPath = UPLOAD_DIR . $thumbForDb;
                if (!file_exists($thumbFullPath)) {
                    $thumbForDb = null;
                }

                if (!Photo::addPhotoToAlbum($this->pdo, $albumId, $this->currentUserId, $path, $thumbForDb)) {
                    throw new Exception("Erro ao adicionar uma foto ao álbum.");
                }
            }

            // Adicionar ao feed
            if (!FeedItem::createFeedItem($this->pdo, $this->currentUserId, 'album', $albumId, $showInFeed)) {
                throw new Exception("Falha ao adicionar o álbum ao feed global.");
            }

            $this->pdo->commit();

            // Queue IA
            if ($coverPhotoUrl) {
                $this->queueForAIProcessing($albumId, $coverPhotoUrl);
            }

            return [
                'success' => true,
                'album_id' => $albumId,
                'message' => $isApproved ? "Álbum criado com sucesso!" : "Álbum enviado para aprovação."
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Limpar ficheiros uploadados
            foreach ($uploadedFilesPaths as $filePath) {
                if (file_exists($filePath)) unlink($filePath);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Apaga um álbum se o utilizador tiver permissão.
     */
    public function deleteAlbum(int $albumId): array
    {
        $albumData = Album::getAlbumById($this->pdo, $albumId);

        if (!$albumData) {
            return ['success' => false, 'message' => 'Álbum não encontrado.'];
        }

        $ownerId = (int)$albumData['user_id'];
        $canDelete = $this->isAdmin || $ownerId === $this->currentUserId;

        if (!$canDelete) {
            return ['success' => false, 'message' => 'Você não tem permissão para apagar este álbum.'];
        }

        if (Album::deleteAlbum($this->pdo, $albumId, $ownerId)) {
            // Notificar dono se admin apagou
            if ($this->isAdmin && $ownerId !== $this->currentUserId) {
                $msg = "O seu álbum foi bloqueado porque infringiu algumas regras da rede social.";
                Notification::createNotification(
                    $this->pdo,
                    $ownerId,
                    $msg,
                    null,
                    null,
                    'album_blocked',
                    $albumId
                );
            }

            return ['success' => true, 'message' => 'Álbum apagado com sucesso!'];
        }

        return ['success' => false, 'message' => 'Erro ao apagar o álbum.'];
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
     * Adiciona imagem à fila de processamento IA.
     */
    private function queueForAIProcessing(int $albumId, string $coverPhotoUrl): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO media_queue (post_id, file_path, item_type, status) 
                VALUES (?, ?, 'album', 'pending')
            ");
            $stmt->execute([$albumId, 'uploads/' . $coverPhotoUrl]);
        } catch (Exception $e) {
            error_log("Erro ao adicionar à fila IA: " . $e->getMessage());
        }
    }
}
