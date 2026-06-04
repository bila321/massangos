<?php
/**
 * public/api/ai-health-check.php
 *
 * ⚠️  FICHEIRO DE DIAGNÓSTICO — REMOVER EM PRODUÇÃO ⚠️
 *
 * Aceder via browser ou curl para confirmar que o pipeline PHP → FastAPI funciona.
 * URL: http://localhost/massango/public/api/ai-health-check.php
 *
 * Resposta esperada quando tudo está OK:
 * {
 *   "fastapi_online": true,
 *   "fastapi_url": "http://127.0.0.1:8000",
 *   "curl_available": true,
 *   "identity_endpoint": "http://127.0.0.1:8000/identity/verify",
 *   "health_response": { "status": "healthy", ... },
 *   "php_version": "8.2.x",
 *   "allow_url_fopen": true
 * }
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../services/ai/identity/php_trigger.php';

// Só admins podem aceder a este endpoint de diagnóstico
SecurityManager::initSecurity();
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Testar disponibilidade
$fastapi_online = is_ai_service_available();

// Tentar obter resposta do health endpoint
$health_response = null;
if (function_exists('curl_init')) {
    $ch = curl_init(AI_SERVICE_URL . '/identity/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp && $httpCode === 200) {
        $health_response = json_decode($resp, true);
    }
}

// Verificar pasta de uploads
$upload_dir          = dirname(dirname(dirname(__DIR__))) . '/storage/uploads/verifications';
$upload_dir_exists   = is_dir($upload_dir);
$upload_dir_writable = $upload_dir_exists && is_writable($upload_dir);

echo json_encode([
    'fastapi_online'        => $fastapi_online,
    'fastapi_url'           => AI_SERVICE_URL,
    'identity_endpoint'     => AI_IDENTITY_ENDPOINT,
    'health_response'       => $health_response,
    'curl_available'        => function_exists('curl_init'),
    'allow_url_fopen'       => (bool) ini_get('allow_url_fopen'),
    'php_version'           => PHP_VERSION,
    'upload_dir_exists'     => $upload_dir_exists,
    'upload_dir_writable'   => $upload_dir_writable,
    'upload_dir_path'       => realpath($upload_dir) ?: $upload_dir,
    'ai_identity_timeout'   => AI_IDENTITY_TIMEOUT,
    'diagnosis'             => [
        'status'   => $fastapi_online ? 'OK' : 'FALHA',
        'message'  => $fastapi_online
            ? 'FastAPI está online e acessível. O pipeline deve funcionar.'
            : 'FastAPI está OFFLINE. Execute: cd ai && uvicorn main:app --reload --port 8000',
        'checklist' => [
            'fastapi_running'  => $fastapi_online
                ? '✅ FastAPI em execução'
                : '❌ FastAPI não responde em ' . AI_SERVICE_URL,
            'curl_enabled'     => function_exists('curl_init')
                ? '✅ cURL disponível'
                : '❌ cURL não instalado — activar php_curl no php.ini',
            'upload_dir'       => $upload_dir_writable
                ? '✅ Pasta de uploads OK'
                : '❌ Pasta uploads/verifications/ não existe ou sem permissões de escrita',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
