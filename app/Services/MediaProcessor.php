<?php

namespace Massango\Services;

use Exception;

class MediaProcessor
{
    /**
     * Redimensiona e comprime uma imagem, gerando uma thumbnail.
     */
    public static function generateImageThumbnail($sourcePath, $destPath, $maxWidth = 800, $maxHeight = 800, $quality = 100)
    {
        // Normalizar caminhos para o sistema operacional atual
        $sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
        $destPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destPath);

        if (!file_exists($sourcePath)) {
            error_log("MediaProcessor: Arquivo de origem não encontrado: $sourcePath");
            return false;
        }

        // Verificar se a extensão GD estÃ¡ carregada
        if (!extension_loaded('gd')) {
            error_log("MediaProcessor: Extensão GD não estÃ¡ carregada no PHP. Verifique seu php.ini no XAMPP.");
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            error_log("MediaProcessor: Não foi possÃ­vel obter informaçÃµes da imagem: $sourcePath");
            return false;
        }

        list($width, $height, $type) = $imageInfo;

        $ratio = $width / $height;
        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newHeight = $maxWidth / $ratio;
            $newWidth = $maxWidth;
        }

        $newWidth = max(1, (int)$newWidth);
        $newHeight = max(1, (int)$newHeight);

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        if (!$thumb) {
            error_log("MediaProcessor: Falha ao criar imagem true color.");
            return false;
        }

        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        $source = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = @imagecreatefromwebp($sourcePath);
                break;
            default:
                error_log("MediaProcessor: Tipo de imagem não suportado ($type): $sourcePath");
                imagedestroy($thumb);
                return false;
        }

        if (!$source) {
            error_log("MediaProcessor: Falha ao carregar imagem de origem (provavelmente corrompida ou memÃ³ria insuficiente): $sourcePath");
            imagedestroy($thumb);
            return false;
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0777, true)) {
                error_log("MediaProcessor: Falha ao criar diretÃ³rio de destino: $destDir");
            }
        }

        $result = false;
        if (@imagejpeg($thumb, $destPath, $quality)) {
            $result = true;
        } else {
            error_log("MediaProcessor: Falha ao salvar thumbnail em: $destPath. Verifique se o XAMPP tem permissão de escrita na pasta.");
        }

        imagedestroy($thumb);
        imagedestroy($source);

        return $result;
    }

    /**
     * Extrai um frame de um vÃ­deo para usar como thumbnail.
     */
    public static function generateVideoThumbnail($videoPath, $destPath, $second = 1)
    {
        $ffmpegPath = defined('FFMPEG_PATH') ? FFMPEG_PATH : 'ffmpeg';

        $videoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $videoPath);
        $destPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destPath);

        $time = sprintf('%02d:%02d:%02d', intdiv($second, 3600), intdiv($second, 60) % 60, $second % 60);

        // No Windows, caminhos com espaços precisam de aspas duplas extras
        $cmd = "\"$ffmpegPath\" -i " . escapeshellarg($videoPath) . " -ss $time -vframes 1 " . escapeshellarg($destPath) . " 2>&1";

        exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && file_exists($destPath)) {
            self::generateImageThumbnail($destPath, $destPath);
            return true;
        }

        error_log("MediaProcessor: Erro FFmpeg: " . implode("\n", $output));
        return false;
    }

    /**
     * Valida o arquivo enviado.
     */
    public static function validateUpload($file, $allowedTypes, $maxSize)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload: CÃ³digo " . $file['error']);
        }

        if ($file['size'] > $maxSize) {
            throw new Exception("Arquivo muito grande.");
        }

        if (!extension_loaded('fileinfo')) {
            // Fallback se fileinfo não estiver ativo no XAMPP
            $mimeType = $file['type'];
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Tipo não permitido: " . $mimeType);
        }

        return true;
    }
}
