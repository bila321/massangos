<?php
/**
 * public/media-proxy.php
 *
 * Serve ficheiros de media (imagens/vídeos) com controlo de acesso.
 *
 * Lógica de resolução/segurança → app/Services/MediaProxyResolver.php
 * Streaming de bytes (headers, readfile) → fica aqui de propósito,
 * para não misturar side-effects de output com lógica de decisão.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\MediaProxyResolver;

$storageBase       = UPLOAD_DIR;
$storageBaseLegacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

$resolver = new MediaProxyResolver($storageBase, $storageBaseLegacy);

// ── Resolver qual ficheiro foi pedido (Modo 1: ?id=&t= | Modo 2: ?file=) ──────
if (isset($_GET['id'])) {
    $mediaIdClean = $_GET['id'] ?? '';
    $token        = $_GET['t']  ?? '';

    $file = $resolver->decodeMediaId($mediaIdClean);

    if (!$resolver->isTokenValid($token, $mediaIdClean)) {
        http_response_code(403);
        exit('Forbidden');
    }
} else {
    $file = $_GET['file'] ?? '';
}

// ── Sanitizar e validar path (rejeita path traversal) ─────────────────────────
try {
    $file = $resolver->sanitizePath($file);
} catch (\RuntimeException $e) {
    http_response_code($e->getCode() ?: 403);
    exit('Forbidden');
}

// ── Ficheiro vazio: serve imagem de perfil por omissão ────────────────────────
if (empty($file)) {
    $default = $resolver->resolveDefaultProfilePath();
    if ($default !== null) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($default));
        header('Cache-Control: public, max-age=86400');
        readfile($default);
    } else {
        http_response_code(404);
        echo 'Not found';
    }
    exit;
}

// ── verifications/ exige autenticação (dono ou admin) ──────────────────────────
if (str_starts_with($file, 'verifications/')) {
    require_once __DIR__ . '/../includes/auth.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!$resolver->canAccessVerification($file)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// ── Normalizar segmentos duplicados de thumbnail ──────────────────────────────
$file = $resolver->normalizeDuplicateSegments($file);

// ── Resolver caminho físico final (com todos os fallbacks) ─────────────────────
$path = $resolver->resolvePhysicalPath($file);

if ($path === null) {
    http_response_code(404);
    exit('Not found');
}

// ── CORS condicional (permite canvas JS ler via crossOrigin="anonymous") ──────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $_SERVER['HTTP_HOST']   ?? '';
if ($origin && (parse_url($origin, PHP_URL_HOST) === $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

// ── Streaming final ────────────────────────────────────────────────────────────
$mime = $resolver->resolveMimeType($path);

header("Content-Type: $mime");
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
