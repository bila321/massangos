<?php
/**
 * Helper para integração com a API FastAPI de Verificação Facial
 */

if (!defined('SECURE_ACCESS')) {
    die('Acesso direto não permitido');
}

/**
 * Chama a API FastAPI para verificar a face
 * 
 * @param string $selfie_path Caminho absoluto da selfie ou vídeo
 * @param string $document_path Caminho absoluto da foto do BI (frente)
 * @return array Resultado da API
 */
function call_face_verification_api($selfie_path, $document_path) {
    // URL da API FastAPI (ajustar conforme o ambiente)
    $api_url = 'http://localhost:8000/identity/verify-face';
    
    $post_data = [
        'selfie_path' => $selfie_path,
        'document_path' => $document_path
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout de 60 segundos para processamento pesado
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Face API Error (cURL): " . $error);
        return [
            'success' => false,
            'error' => 'Erro de conexão com a API de verificação facial'
        ];
    }

    if ($http_code !== 200) {
        error_log("Face API Error (HTTP $http_code): " . $response);
        return [
            'success' => false,
            'error' => "A API retornou um erro (HTTP $http_code)"
        ];
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Face API Error (JSON): " . json_last_error_msg());
        return [
            'success' => false,
            'error' => 'Resposta inválida da API'
        ];
    }

    return $result;
}

/**
 * Mapeia o status da IA para o status de verificação do sistema
 * 
 * @param string $ai_status Status retornado pela IA
 * @return string Status interno do sistema
 */
function map_ai_status_to_system($ai_status) {
    switch ($ai_status) {
        case 'approved':
            return 'approved';
        case 'manual_review':
            return 'pending'; // Mantém pendente para revisão humana
        case 'rejected':
        default:
            return 'rejected';
    }
}
