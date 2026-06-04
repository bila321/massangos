<?php


define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development'); // Mudar para 'production' no servidor real

// Captura qualquer output (warnings, notices) antes do header JSON
ob_start();

// ─── Includes (caminho correto: public/ajax/ → 3 níveis acima) ────────────
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Descarta qualquer output espúrio (notices/warnings PHP) antes de enviar JSON
ob_end_clean();

// ─── Headers ──────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
// Impede que proxies ou browsers façam cache de respostas de ações
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// ─── Wrapper global de erros ──────────────────────────────────────────────
try {

    // Só aceita POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // ── Autenticação ──────────────────────────────────────────────────────
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado. Faça login para guardar.']);
        exit;
    }

    // ── CSRF ──────────────────────────────────────────────────────────────
    // NOTA: Usamos hash_equals direto contra a sessão em vez de
    // SecurityManager::verifyCSRFToken() porque esse método destrói o token
    // após o primeiro uso, o que invalida os botões seguintes na mesma página.
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token   = $_SESSION['csrf_token'] ?? '';

    if (
        empty($submitted_token) ||
        empty($session_token) ||
        !hash_equals($session_token, $submitted_token)
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página.']);
        exit;
    }

    // ── Dados da requisição ───────────────────────────────────────────────
    $user_id   = get_current_user_id();
    $item_type = trim($_POST['item_type'] ?? '');
    $item_id   = (int)($_POST['item_id'] ?? 0);

    // ── Validação básica ──────────────────────────────────────────────────
    $allowed_types = ['post', 'video', 'album', 'reel'];

    if (!in_array($item_type, $allowed_types, true) || $item_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }

    // ── Mapeamento tipo → tabela ──────────────────────────────────────────
    $table_map = [
        'post'  => 'posts',
        'video' => 'videos',
        'album' => 'albums',
        'reel'  => 'videos', // reels são armazenados na tabela videos
    ];
    $table = $table_map[$item_type];

    // ── Verificar se o item existe ────────────────────────────────────────
    // Não interpolamos $table diretamente (é um valor controlado internamente,
    // mas usamos um allowlist acima para garantir segurança).
    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$item_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Conteúdo não encontrado.']);
        exit;
    }

    // ── Toggle: guardar ou remover ────────────────────────────────────────
    // Para 'reel', guardamos com item_type = 'video' para consistência no DB
    $db_item_type = ($item_type === 'reel') ? 'video' : $item_type;

    $check = $pdo->prepare(
        "SELECT id FROM saved_posts WHERE user_id = ? AND item_type = ? AND item_id = ? LIMIT 1"
    );
    $check->execute([$user_id, $db_item_type, $item_id]);
    $existing = $check->fetch();

    if ($existing) {
        // ── Remover dos guardados ─────────────────────────────────────────
        $pdo->prepare("DELETE FROM saved_posts WHERE id = ? LIMIT 1")
            ->execute([$existing['id']]);

        echo json_encode([
            'success' => true,
            'saved'   => false,
            'message' => 'Removido dos guardados.',
        ]);
    } else {
        // ── Adicionar aos guardados ───────────────────────────────────────
        $pdo->prepare(
            "INSERT INTO saved_posts (user_id, item_type, item_id, created_at)
             VALUES (?, ?, ?, NOW())"
        )->execute([$user_id, $db_item_type, $item_id]);

        echo json_encode([
            'success' => true,
            'saved'   => true,
            'message' => 'Guardado com sucesso.',
        ]);
    }
} catch (PDOException $e) {
    // Erro de base de dados — log interno, resposta genérica ao cliente
    error_log('[toggle_save] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na base de dados. Tente novamente.']);
} catch (Throwable $e) {
    // Qualquer outro erro inesperado
    error_log('[toggle_save] Throwable: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}
