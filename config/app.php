<?php
defined('ENVIRONMENT') || define('ENVIRONMENT', 'development');

define('APP_ROOT', dirname(__DIR__));

// ============================================================
// DETECÇÃO AUTOMÁTICA DE BASE_URL (localhost, ngrok, produção)
// ============================================================

// Protocolo (http/https)
$__protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    ? 'https' : 'http';

// Host (localhost, ngrok, domínio real)
$__host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Pasta base do projeto (ex: /massangos/public)
// Detecta automaticamente se o app está em subpasta
$__script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$__basePath = '';

if (strpos($__script, '/massangos/public/') !== false) {
    $__basePath = '/massangos/public';
} elseif (strpos($__script, '/public/') !== false) {
    $__basePath = dirname($__script); // fallback
    $__basePath = str_replace('\\', '/', $__basePath);
    $__basePath = rtrim($__basePath, '/');
} else {
    $__basePath = '';
}

// Monta BASE_URL dinâmico
$__baseUrl = $__protocol . '://' . $__host . $__basePath . '/';

define('BASE_URL', $__baseUrl);

// Limpa variáveis temporárias
unset($__protocol, $__host, $__script, $__basePath, $__baseUrl);

// ============================================================
define('UPLOAD_URL', BASE_URL . 'media-proxy.php?file=');
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('MAX_FILE_COUNT_ALBUM', 10);
define('MAX_POST_LENGTH', 5000);
define('MAX_COMMENT_LENGTH', 1000);
define('MAX_BIO_LENGTH', 500);
define('MIN_PASSWORD_LENGTH', 8);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);
define('FFMPEG_PATH', 'C:\ffmpeg\bin\ffmpeg.exe');
define('FFPROBE_PATH', 'C:\ffmpeg\bin\ffprobe.exe');
date_default_timezone_set('Africa/Maputo');
