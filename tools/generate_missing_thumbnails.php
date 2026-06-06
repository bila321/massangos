<?php

/**
 * Script para gerar thumbnails de fotos de álbuns que ainda não têm.
 * Executar via CLI: php tools/generate_missing_thumbnails.php
 * Ou via browser: http://localhost/massangos/tools/generate_missing_thumbnails.php
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\MediaProcessor;

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════\n";
echo "  Gerador de Thumbnails — Fotos de Álbuns\n";
echo "═══════════════════════════════════════════════════\n\n";

// Buscar todas as fotos sem thumbnail
$stmt = $pdo->query("
    SELECT id, photo_path 
    FROM album_photos 
    WHERE thumbnail_path IS NULL OR thumbnail_path = ''
");
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($photos);
echo "Fotos sem thumbnail encontradas: {$total}\n\n";

if ($total === 0) {
    echo "✅ Nada a fazer. Todas as fotos têm thumbnail.\n";
    exit;
}

$uploadDir = UPLOAD_DIR;
$thumbsDir = $uploadDir . 'albums/thumbnails/';

if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
    echo "📁 Pasta de thumbnails criada: {$thumbsDir}\n\n";
}

$ok = 0;
$fail = 0;
$missing = 0;

foreach ($photos as $i => $photo) {
    $num = $i + 1;
    $photoPath = $uploadDir . $photo['photo_path'];
    $fileName = basename($photo['photo_path']);
    $thumbPath = $thumbsDir . $fileName;
    $dbThumbPath = 'albums/thumbnails/' . $fileName;

    echo "[{$num}/{$total}] {$fileName}... ";

    if (!file_exists($photoPath)) {
        echo "❌ Original não existe\n";
        $missing++;
        continue;
    }

    if (file_exists($thumbPath)) {
        // Thumbnail já existe, só actualizar BD
        $pdo->prepare("UPDATE album_photos SET thumbnail_path = ? WHERE id = ?")
            ->execute([$dbThumbPath, $photo['id']]);
        echo "✅ Thumbnail já existia, BD actualizada\n";
        $ok++;
        continue;
    }

    try {
        $result = MediaProcessor::generateImageThumbnail($photoPath, $thumbPath, 400, 400, 85);

        if ($result && file_exists($thumbPath)) {
            $pdo->prepare("UPDATE album_photos SET thumbnail_path = ? WHERE id = ?")
                ->execute([$dbThumbPath, $photo['id']]);
            echo "✅ Gerado\n";
            $ok++;
        } else {
            echo "❌ Falha na geração\n";
            $fail++;
        }
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\n═══════════════════════════════════════════════════\n";
echo "  Resultado\n";
echo "═══════════════════════════════════════════════════\n";
echo "✅ Sucesso:        {$ok}\n";
echo "❌ Falhas:         {$fail}\n";
echo "⚠️ Originais em falta: {$missing}\n";
echo "📊 Total:          {$total}\n";
