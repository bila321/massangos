<?php
/**
 * Script de Teste para o Media Proxy
 */

function generateTestToken($mediaId) {
    $timestamp = time() * 1000;
    $random = 'test_random';
    return base64_encode("$mediaId:$timestamp:$random");
}

$testMediaId = base64_encode('profiles/default_profile.png');
$testToken = generateTestToken($testMediaId);

echo "--- Teste de Geração de Token ---\n";
echo "Media ID: $testMediaId\n";
echo "Token: $testToken\n";
echo "URL Sugerida: media-proxy.php?id=$testMediaId&t=$testToken\n\n";

// Simular validação interna
require_once __DIR__ . '/../public/media-proxy.php';

echo "--- Teste de Validação Interna ---\n";
if (validateMediaToken($testMediaId, $testToken)) {
    echo "SUCESSO: Token validado corretamente.\n";
} else {
    echo "ERRO: Falha na validação do token.\n";
}

$expiredToken = base64_encode("$testMediaId:" . (time() - 4000) * 1000 . ":random");
if (!validateMediaToken($testMediaId, $expiredToken)) {
    echo "SUCESSO: Token expirado rejeitado corretamente.\n";
} else {
    echo "ERRO: Token expirado foi aceito.\n";
}
