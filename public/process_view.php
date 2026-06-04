<?php
// public/process_view.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

$item_type = $_POST['item_type'] ?? '';
$item_id   = (int) ($_POST['item_id'] ?? 0);

// Validação de entrada
if ($item_id <= 0 || !in_array($item_type, ['video', 'album'], true)) {
    $response['error'] = 'Parâmetros inválidos.';
    echo json_encode($response);
    exit();
}

$table_name = ($item_type === 'video') ? 'videos' : 'albums';

// --- Identificação do visualizador ---
$user_id    = (int) ($_SESSION['user_id'] ?? 0);
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';
// Normaliza para o primeiro IP em caso de lista (proxy chain)
$ip_address = trim(explode(',', $ip_address)[0]);

$one_hour    = 3600;
$now         = time();
$session_key = "last_view_{$item_type}_{$item_id}";

try {
    // 1. Verificar se o item existe e está aprovado
    $stmt = $pdo->prepare(
        "SELECT id FROM {$table_name} WHERE id = ? AND is_approved = 1 LIMIT 1"
    );
    $stmt->execute([$item_id]);

    if (!$stmt->fetch()) {
        $response['error'] = 'Conteúdo não encontrado ou não aprovado.';
        echo json_encode($response);
        exit();
    }

    // 2. Verificação rápida via sessão (evita queries desnecessárias)
    if (
        isset($_SESSION[$session_key]) &&
        ($now - $_SESSION[$session_key]) <= $one_hour
    ) {
        $response['success']         = true;
        $response['message']         = 'Visualização já contada recentemente.';
        $response['already_counted'] = true;
        echo json_encode($response);
        exit();
    }

    // 3. Verificação persistente no DB (cobre modo privado, browsers diferentes)
    $viewer_clause = $user_id > 0
        ? 'user_id = ? AND item_type = ? AND item_id = ?'
        : 'user_id = 0 AND ip_address = ? AND item_type = ? AND item_id = ?';

    $viewer_param = $user_id > 0 ? $user_id : $ip_address;

    $log_stmt = $pdo->prepare(
        "SELECT id FROM view_logs
          WHERE {$viewer_clause}
            AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
          LIMIT 1"
    );
    $log_stmt->execute([$viewer_param, $item_type, $item_id]);

    if ($log_stmt->fetch()) {
        // Já contado no DB — sincroniza a sessão para evitar futuras queries
        $_SESSION[$session_key]      = $now;
        $response['success']         = true;
        $response['message']         = 'Visualização já contada recentemente.';
        $response['already_counted'] = true;
        echo json_encode($response);
        exit();
    }

    // 4. Registar view: incrementar contador + inserir log (transação atómica)
    $pdo->beginTransaction();

    $update = $pdo->prepare(
        "UPDATE {$table_name} SET views_count = views_count + 1 WHERE id = ?"
    );
    $update->execute([$item_id]);

    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        $response['error'] = 'Não foi possível registar a visualização.';
        echo json_encode($response);
        exit();
    }

    $insert = $pdo->prepare(
        "INSERT INTO view_logs (user_id, ip_address, item_type, item_id)
         VALUES (?, ?, ?, ?)"
    );
    $insert->execute([$user_id, $ip_address, $item_type, $item_id]);

    $pdo->commit();

    $_SESSION[$session_key] = $now;

    // Buscar o novo total para actualizar a UI no cliente
    $count_stmt = $pdo->prepare("SELECT views_count FROM {$table_name} WHERE id = ? LIMIT 1");
    $count_stmt->execute([$item_id]);
    $new_count = (int) ($count_stmt->fetchColumn() ?: 0);

    $response['success']   = true;
    $response['message']   = 'Visualização contada.';
    $response['new_count'] = $new_count;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao processar visualização [{$item_type}:{$item_id}]: " . $e->getMessage());
    $response['error'] = 'Erro interno. Tente novamente.';
}

echo json_encode($response);
exit();
