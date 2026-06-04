<?php
/**
 * ai/identity/php_trigger.php
 *
 * Helper PHP para chamar o serviço de verificação de identidade via FastAPI.
 *
 * FIXES APLICADOS:
 *  - Substituído file_get_contents por cURL (mais fiável no XAMPP/Windows).
 *  - is_ai_service_available() usa cURL com timeout curto.
 *  - trigger_ai_identity_verification() retorna sempre array com chave 'success' (bool).
 *  - Logs detalhados com HTTP code e erro cURL para facilitar diagnóstico.
 *  - get_ai_verification_status() também usa cURL.
 */

define('AI_SERVICE_URL',        'http://127.0.0.1:8000');
define('AI_IDENTITY_ENDPOINT',  AI_SERVICE_URL . '/identity/verify');
define('AI_HEALTH_ENDPOINT',    AI_SERVICE_URL . '/identity/health');
define('AI_IDENTITY_TIMEOUT',   90); // segundos — ArcFace pode demorar na 1.ª execução

// ─────────────────────────────────────────────────────────────────────────────
//  VERIFICAR DISPONIBILIDADE DO SERVIÇO
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verifica se o serviço FastAPI está a correr.
 * Usa cURL com timeout de 3 segundos.
 */
function is_ai_service_available(): bool {
    if (!function_exists('curl_init')) {
        error_log('[AI Identity] cURL não disponível no PHP. Instale a extensão php_curl.');
        return false;
    }

    $ch = curl_init(AI_HEALTH_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_NOBODY         => false,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlError)) {
        error_log('[AI Identity] Health check falhou: ' . $curlError);
        return false;
    }

    return $httpCode === 200;
}

// ─────────────────────────────────────────────────────────────────────────────
//  DISPARAR VERIFICAÇÃO
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Envia os ficheiros de verificação para o serviço Python/FastAPI analisar.
 *
 * IMPORTANTE: retorna sempre um array com 'success' => bool.
 * O caller deve verificar $result['success'], NÃO fazer !$result (array não-vazio é sempre true).
 *
 * @param int    $user_id          ID do utilizador
 * @param int    $verification_id  ID em user_verifications
 * @param string $id_front_path    Caminho ABSOLUTO para a frente do BI
 * @param string $id_back_path     Caminho ABSOLUTO para o verso do BI
 * @param string $video_path       Caminho ABSOLUTO para o vídeo .webm
 * @param bool   $async            Se true, processa em background (recomendado)
 *
 * @return array {
 *   'success' => bool,
 *   'status'  => string,   // 'processing'|'service_unavailable'|'connection_failed'|'http_NNN'|'invalid_response'
 *   'result'  => array|null,
 *   'error'   => string|null
 * }
 */
function trigger_ai_identity_verification(
    int $user_id,
    int $verification_id,
    string $id_front_path,
    string $id_back_path,
    string $video_path,
    bool $async = true
): array {

    // 1. Verificar disponibilidade do serviço
    if (!is_ai_service_available()) {
        error_log(sprintf(
            '[AI Identity] FastAPI indisponível em %s | user=%d | ver=%d',
            AI_SERVICE_URL, $user_id, $verification_id
        ));
        return [
            'success' => false,
            'status'  => 'service_unavailable',
            'result'  => null,
            'error'   => 'Serviço FastAPI não está em execução em ' . AI_SERVICE_URL,
        ];
    }

    // 2. Verificar que os ficheiros existem antes de enviar
    foreach (['id_front' => $id_front_path, 'id_back' => $id_back_path, 'video' => $video_path] as $label => $path) {
        if (!file_exists($path)) {
            error_log("[AI Identity] Ficheiro '$label' não encontrado: $path | user=$user_id");
            return [
                'success' => false,
                'status'  => 'file_not_found',
                'result'  => null,
                'error'   => "Ficheiro '$label' não encontrado: $path",
            ];
        }
    }

    // 3. Construir payload
    $postFields = http_build_query([
        'user_id'         => $user_id,
        'verification_id' => $verification_id,
        'id_front_path'   => $id_front_path,
        'id_back_path'    => $id_back_path,
        'video_path'      => $video_path,
        'async_mode'      => $async ? '1' : '0',
    ]);

    // 4. Executar pedido cURL
    $ch = curl_init(AI_IDENTITY_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postFields),
        ],
        CURLOPT_TIMEOUT        => AI_IDENTITY_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 5. Tratar erros de rede
    if ($response === false || !empty($curlError)) {
        error_log(sprintf(
            '[AI Identity] cURL falhou: %s | user=%d | ver=%d',
            $curlError, $user_id, $verification_id
        ));
        return [
            'success' => false,
            'status'  => 'connection_failed',
            'result'  => null,
            'error'   => 'Erro de rede: ' . $curlError,
        ];
    }

    // 6. Tratar HTTP não-200
    if ($httpCode !== 200) {
        error_log(sprintf(
            '[AI Identity] HTTP %d | user=%d | ver=%d | resposta: %s',
            $httpCode, $user_id, $verification_id, substr($response, 0, 500)
        ));
        return [
            'success' => false,
            'status'  => 'http_' . $httpCode,
            'result'  => null,
            'error'   => "Serviço retornou HTTP $httpCode",
        ];
    }

    // 7. Parsear JSON
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log(sprintf(
            '[AI Identity] JSON inválido | user=%d | ver=%d | resposta: %s',
            $user_id, $verification_id, substr($response, 0, 500)
        ));
        return [
            'success' => false,
            'status'  => 'invalid_response',
            'result'  => null,
            'error'   => 'Resposta JSON inválida do serviço: ' . json_last_error_msg(),
        ];
    }

    error_log(sprintf(
        '[AI Identity] Trigger OK | user=%d | ver=%d | status=%s',
        $user_id, $verification_id, $result['status'] ?? 'unknown'
    ));

    return [
        'success' => true,
        'status'  => $result['status'] ?? 'processing',
        'result'  => $result,
        'error'   => null,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
//  CONSULTAR STATUS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Consulta o status de uma verificação já em processamento via FastAPI.
 * Retorna null se o serviço não responder.
 */
function get_ai_verification_status(int $verification_id): ?array {
    if (!function_exists('curl_init')) return null;

    $ch = curl_init(AI_SERVICE_URL . '/identity/status/' . $verification_id);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return null;

    $data = json_decode($response, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

// ─────────────────────────────────────────────────────────────────────────────
//  DIAGNÓSTICO (remover em produção)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna informação de diagnóstico do sistema de IA.
 * Útil para debug — chamar via ai-health-check.php temporário.
 */
function ai_identity_diagnostics(): array {
    return [
        'fastapi_url'      => AI_SERVICE_URL,
        'fastapi_online'   => is_ai_service_available(),
        'curl_available'   => function_exists('curl_init'),
        'allow_url_fopen'  => (bool) ini_get('allow_url_fopen'),
        'php_version'      => PHP_VERSION,
        'timeout_config'   => AI_IDENTITY_TIMEOUT,
    ];
}
