<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';

// Localização principal (nova estrutura): massangos/uploads/
$storageBase = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Localização antiga (antes da reestruturação): massangos/storage/uploads/
$storageBaseLegacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

// --- Modo 1: ?id=&t= (sistema seguro com HMAC) ---
if (isset($_GET['id'])) {
    $mediaIdClean = $_GET['id'] ?? '';
    $token        = $_GET['t']  ?? '';

    // Padding do base64
    $padded = $mediaIdClean . str_repeat('=', (4 - strlen($mediaIdClean) % 4) % 4);
    $file   = base64_decode($padded);

    // Validar token se SECURITY_SALT definido
    if (defined('SECURITY_SALT') && !empty($token)) {
        $tokenDecoded = base64_decode($token);
        $parts = explode(':', $tokenDecoded);
        if (count($parts) >= 4) {
            [$tId, $tTime, $tRandom, $tHash] = $parts;
            $expectedHash = hash_hmac('sha256', "$tId:$tTime:$tRandom", SECURITY_SALT);
            if (!hash_equals($expectedHash, $tHash)) {
                http_response_code(403);
                exit('Forbidden');
            }
        }
    }

    // --- Modo 2: ?file= (sistema simples) ---
} else {
    $file = $_GET['file'] ?? '';
}

// Sanitizar e validar path com realpath
$file = str_replace('\\', '', $file);
$file = ltrim($file, '/\\');
if (empty($file)) {
    // caminho vazio tratado abaixo
} else {
    $resolvedStorageBase = realpath($storageBase);
    $resolvedPath = realpath($storageBase . $file);
    if ($resolvedPath === false || !str_starts_with($resolvedPath, $resolvedStorageBase)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if (empty($file)) {
    $default = $storageBase . 'default_profile.png';
    if (file_exists($default)) {
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($default));
        header("Cache-Control: public, max-age=86400");
        readfile($default);
    } else {
        http_response_code(404);
        echo 'Not found';
    }
    exit;
}

// Verifications requerem autenticacao
if (str_starts_with($file, 'verifications/')) {
    require_once __DIR__ . '/../includes/auth.php';
    session_start();
    $parts = explode('/', $file);
    $user_folder = $parts[1] ?? '';
    $is_admin = isset($_SESSION['admin_id']);
    $is_owner = isset($_SESSION['user_id']) && $user_folder == $_SESSION['user_id'];
    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        exit('Forbidden');
    }
}



// Procurar primeiro na localização nova, depois na antiga
$path = $storageBase . $file;
if (!file_exists($path)) {
    $path = $storageBaseLegacy . $file;
}

// Fallback: se thumbnail de album nao existe, tenta o original
if (!file_exists($path) && str_contains($file, 'albums/thumbnails/')) {
    $original = str_replace('albums/thumbnails/', 'albums/', $file);
    $tryPath = $storageBase . $original;
    if (!file_exists($tryPath)) $tryPath = $storageBaseLegacy . $original;
    if (file_exists($tryPath)) $path = $tryPath;
}
// Fallback: se thumbnail de video nao existe, tenta o ficheiro de video original
if (!file_exists($path) && str_contains($file, 'videos/thumbnails/')) {
    $original = str_replace('videos/thumbnails/', 'videos/', $file);
    // Tenta encontrar o vídeo original com qualquer uma das extensões suportadas
    $videoName = preg_replace('/_thumb\.jpg$/', '', $original);
    $found = false;
    foreach(['.mp4', '.webm', '.ogg'] as $ext) {
        $tryPath = $storageBase . $videoName . $ext;
        if (!file_exists($tryPath)) $tryPath = $storageBaseLegacy . $videoName . $ext;
        
        if (file_exists($tryPath)) {
            $path = $tryPath;
            break;
        }
    }
}
// Fallbacks para imagem por omissão
if (!file_exists($path)) {
    if (str_contains($file, 'profile')) {
        $path = $storageBase . 'default_profile.png';
        if (!file_exists($path)) $path = $storageBaseLegacy . 'default_profile.png';
    } elseif (str_contains($file, 'post')) {
        $path = $storageBase . 'default_post.png';
        if (!file_exists($path)) $path = $storageBaseLegacy . 'default_post.png';
    } else {
        http_response_code(404);
        exit('Not found');
    }
    if (!file_exists($path)) {
        http_response_code(404);
        exit('Not found');
    }
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_map = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

header("Content-Type: $mime");
header("Content-Length: " . filesize($path));
header("Cache-Control: private, max-age=3600");
readfile($path);
exit;
