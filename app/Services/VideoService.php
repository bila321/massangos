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
    public function createVideo(array $data, array $videoFile, ?array $thumbnailFile = null): array
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
                // Limite do arquivo ORIGINAL enviado (antes do corte/compressão).
                // Alinhado ao upload_max_filesize/post_max_size do .htaccess (160M).
                // O tamanho FINAL (após corte/compressão) é limitado a 100MB mais abaixo.
                $maxOriginalSize = 150 * 1024 * 1024;
                MediaProcessor::validateUpload($videoFile, ALLOWED_VIDEO_TYPES, $maxOriginalSize);

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

                // ===== Cortar / comprimir vídeo via FFmpeg (se o utilizador editou) =====
                $trimStart = isset($data['trim_start']) && $data['trim_start'] !== '' ? (float)$data['trim_start'] : null;
                $trimEnd   = isset($data['trim_end']) && $data['trim_end'] !== '' ? (float)$data['trim_end'] : null;
                $compressLevel = $data['compress_level'] ?? 'none';

                $hasTrim = ($trimStart !== null && $trimEnd !== null && $trimEnd > $trimStart);
                $hasCompress = in_array($compressLevel, ['medium', 'high'], true);

                if (($hasTrim || $hasCompress) && file_exists(FFMPEG_PATH)) {
                    $processedFileName = $uniqueId . '_edit.' . $fileExtension;
                    $processedDestination = $uploadDir . $processedFileName;

                    if ($this->processVideoFfmpeg($videoDestination, $processedDestination, $trimStart, $trimEnd, $compressLevel)) {
                        // Substitui o vídeo original pelo editado
                        unlink($videoDestination);
                        rename($processedDestination, $videoDestination);
                    } else {
                        error_log("VideoService: falha ao cortar/comprimir vídeo, mantendo original.");
                        if (file_exists($processedDestination)) {
                            unlink($processedDestination);
                        }
                    }
                }

                // ===== Limite final do vídeo (após corte/compressão) =====
                // Mesmo limite mostrado ao utilizador no editor (VIDEO_MAX_SIZE_MB = 100MB).
                $maxFinalSize = 100 * 1024 * 1024;

                if (filesize($videoDestination) > $maxFinalSize && file_exists(FFMPEG_PATH)) {
                    // Tenta escalar a compressão automaticamente para caber no limite
                    $escalationLevels = ['high'];
                    if ($compressLevel === 'high') {
                        $escalationLevels = []; // já está no nível máximo
                    } elseif ($compressLevel === 'medium') {
                        $escalationLevels = ['high'];
                    } else {
                        $escalationLevels = ['medium', 'high'];
                    }

                    foreach ($escalationLevels as $level) {
                        $retryFileName = $uniqueId . '_retry_' . $level . '.' . $fileExtension;
                        $retryDestination = $uploadDir . $retryFileName;

                        if ($this->processVideoFfmpeg($videoDestination, $retryDestination, null, null, $level)) {
                            if (filesize($retryDestination) <= $maxFinalSize) {
                                unlink($videoDestination);
                                rename($retryDestination, $videoDestination);
                                break;
                            } else {
                                // Ainda grande, guarda este resultado como melhor opção até agora
                                unlink($videoDestination);
                                rename($retryDestination, $videoDestination);
                            }
                        } elseif (file_exists($retryDestination)) {
                            unlink($retryDestination);
                        }
                    }

                    // Se mesmo após a maior compressão ainda exceder o limite, rejeita
                    if (filesize($videoDestination) > $maxFinalSize) {
                        $finalSizeMB = round(filesize($videoDestination) / (1024 * 1024), 1);
                        $maxSizeMB = round($maxFinalSize / (1024 * 1024));
                        throw new Exception("O vídeo ainda está muito grande mesmo após a compressão máxima ({$finalSizeMB}MB de {$maxSizeMB}MB permitidos). Corte mais o vídeo ou envie um arquivo menor.");
                    }
                } elseif (filesize($videoDestination) > $maxFinalSize) {
                    // FFmpeg não disponível para reduzir — rejeita diretamente
                    $finalSizeMB = round(filesize($videoDestination) / (1024 * 1024), 1);
                    $maxSizeMB = round($maxFinalSize / (1024 * 1024));
                    throw new Exception("O vídeo é muito grande ({$finalSizeMB}MB de {$maxSizeMB}MB permitidos) e não foi possível comprimir automaticamente.");
                }

                // Gerar thumbnail
                $thumbName = $uniqueId . '_thumb.jpg';
                $thumbDest = $thumbnailDir . $thumbName;
                $thumbTime = isset($data['thumb_time']) && $data['thumb_time'] !== '' ? (float)$data['thumb_time'] : null;

                try {
                    if ($thumbnailFile && isset($thumbnailFile['tmp_name']) && $thumbnailFile['error'] === UPLOAD_ERR_OK) {
                        // Capa enviada pelo utilizador (já recortada/escolhida no editor)
                        if (move_uploaded_file($thumbnailFile['tmp_name'], $thumbDest)) {
                            $thumbnailPath = 'videos/thumbnails/' . $thumbName;
                        } else {
                            error_log("VideoService: falha ao mover thumbnail enviada pelo utilizador.");
                        }
                    } elseif ($thumbTime !== null && file_exists(FFMPEG_PATH)) {
                        // Extrai o frame escolhido pelo utilizador na linha do tempo
                        if ($this->extractThumbnailFrame($videoDestination, $thumbDest, $thumbTime, $trimStart)) {
                            $thumbnailPath = 'videos/thumbnails/' . $thumbName;
                        } else {
                            error_log("VideoService: falha ao extrair frame de capa no tempo {$thumbTime}, gerando automaticamente.");
                        }
                    }

                    if (!$thumbnailPath) {
                        if (MediaProcessor::generateVideoThumbnail($videoDestination, $thumbDest)) {
                            $thumbnailPath = 'videos/thumbnails/' . $thumbName;
                        } else {
                            error_log("VideoService: Falha ao gerar thumbnail para $videoDestination, mas continuando o post.");
                        }
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
            $ffprobe = defined('FFPROBE_PATH') && file_exists(FFPROBE_PATH) ? FFPROBE_PATH : 'ffprobe';
            $cmd = escapeshellarg($ffprobe) . " -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
            $output = shell_exec($cmd . ' 2>&1');

            if ($output !== null) {
                $value = (float)trim($output);
                if ($value > 0) {
                    return (int)round($value);
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao extrair duração do vídeo: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Corta (trim) e/ou comprime um vídeo usando FFmpeg.
     *
     * @param string $inputPath   Caminho do vídeo original
     * @param string $outputPath  Caminho de destino do vídeo processado
     * @param ?float $trimStart   Segundo inicial do corte (null = início do vídeo)
     * @param ?float $trimEnd     Segundo final do corte (null = fim do vídeo)
     * @param string $compressLevel 'none' | 'medium' | 'high'
     */
    private function processVideoFfmpeg(string $inputPath, string $outputPath, ?float $trimStart, ?float $trimEnd, string $compressLevel): bool
    {
        if (!file_exists(FFMPEG_PATH)) {
            return false;
        }

        $args = [escapeshellarg(FFMPEG_PATH), '-y'];

        // Corte: -ss antes do -i é mais rápido (seek por keyframe)
        if ($trimStart !== null && $trimStart > 0) {
            $args[] = '-ss ' . escapeshellarg((string)$trimStart);
        }

        $args[] = '-i ' . escapeshellarg($inputPath);

        if ($trimStart !== null && $trimEnd !== null && $trimEnd > $trimStart) {
            $duration = $trimEnd - $trimStart;
            $args[] = '-t ' . escapeshellarg((string)$duration);
        }

        if ($compressLevel === 'high') {
            // 480p, qualidade reduzida, ficheiro bem menor
            $args[] = '-vf scale=-2:480';
            $args[] = '-c:v libx264 -preset fast -crf 30';
            $args[] = '-c:a aac -b:a 96k';
        } elseif ($compressLevel === 'medium') {
            // 720p, redução moderada
            $args[] = '-vf scale=-2:720';
            $args[] = '-c:v libx264 -preset fast -crf 26';
            $args[] = '-c:a aac -b:a 128k';
        } else {
            // Sem compressão: apenas corta, copiando os streams (rápido, sem perda)
            $args[] = '-c copy';
        }

        $args[] = '-movflags +faststart';
        $args[] = escapeshellarg($outputPath);

        $cmd = implode(' ', $args) . ' 2>&1';
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            error_log("VideoService FFmpeg trim/compress falhou (code $returnCode): " . implode("\n", $output));

            // Fallback: se o corte com '-c copy' falhar (keyframe incompatível), tenta reencode leve
            if ($compressLevel === 'none') {
                return $this->processVideoFfmpeg($inputPath, $outputPath, $trimStart, $trimEnd, 'medium');
            }

            return false;
        }

        return true;
    }

    /**
     * Extrai um frame específico do vídeo para usar como thumbnail.
     *
     * @param string $videoPath  Caminho do vídeo (já cortado/comprimido, se aplicável)
     * @param string $thumbPath  Caminho de destino da imagem (jpg)
     * @param float  $absoluteTime Tempo absoluto (no vídeo original) escolhido pelo utilizador
     * @param ?float $trimStart    Início do corte aplicado (para calcular tempo relativo)
     */
    private function extractThumbnailFrame(string $videoPath, string $thumbPath, float $absoluteTime, ?float $trimStart): bool
    {
        if (!file_exists(FFMPEG_PATH)) {
            return false;
        }

        // Se o vídeo foi cortado, o tempo precisa ser relativo ao novo início
        $relativeTime = $trimStart !== null ? max(0, $absoluteTime - $trimStart) : $absoluteTime;

        $cmd = escapeshellarg(FFMPEG_PATH) . ' -y -ss ' . escapeshellarg((string)$relativeTime)
            . ' -i ' . escapeshellarg($videoPath)
            . ' -frames:v 1 -q:v 2 '
            . escapeshellarg($thumbPath) . ' 2>&1';

        exec($cmd, $output, $returnCode);

        return $returnCode === 0 && file_exists($thumbPath) && filesize($thumbPath) > 0;
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
