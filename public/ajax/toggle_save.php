<?php

/**
 * ajax/toggle_save.php — Toggle bookmark unificado
 *
 * Formato A — save.js / saved.js (feed, perfil, página de guardados)
 *   item_type   enum: post|video|album|reel|photo
 *   item_id     int
 *   csrf_token  string
 *
 * Formato B — view_album.js
 *   feed_item_id  int  → resolve item_type e item_id via feed_items
 *   csrf_token    string (opcional — view_album.js envia só se CSRF_TOKEN existir)
 *
 * Resposta: { success, saved, saves_count?, message }
 */

define('SECURE_ACCESS', true);

// Captura qualquer output (warnings, notices) antes do header JSON
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';

ob_end_clean();

// ── Headers ───────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

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
    // hash_equals directo contra a sessão — não destrói o token (permite múltiplos
    // saves na mesma página sem invalidar os botões seguintes).
    // view_album.js envia csrf_token apenas se window.CSRF_TOKEN existir,
    // por isso só validamos quando o token é enviado.
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token   = $_SESSION['csrf_token'] ?? '';

    if (!empty($submitted_token)) {
        if (empty($session_token) || !hash_equals($session_token, $submitted_token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página.']);
            exit;
        }
    }

    // ── Resolver item_type + item_id ──────────────────────────────────────
    $allowed_types = ['post', 'video', 'album', 'reel', 'photo'];
    $item_type     = null;
    $item_id       = null;

    if (!empty($_POST['feed_item_id'])) {
        // Formato B: view_album.js → resolve via feed_items
        $feed_item_id = (int)$_POST['feed_item_id'];
        $stmt = $pdo->prepare(
            "SELECT item_type, item_id FROM feed_items WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$feed_item_id]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fi) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Feed item não encontrado.']);
            exit;
        }
        $item_type = $fi['item_type'];
        $item_id   = (int)$fi['item_id'];
    } elseif (!empty($_POST['item_type']) && !empty($_POST['item_id'])) {
        // Formato A: save.js / saved.js → directo
        $item_type = trim($_POST['item_type']);
        $item_id   = (int)$_POST['item_id'];
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes.']);
        exit;
    }

    // ── Validação ─────────────────────────────────────────────────────────
    if (!in_array($item_type, $allowed_types, true) || $item_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }

    // ── Verificar se o item existe na tabela correcta ─────────────────────
    $table_map = [
        'post'  => 'posts',
        'video' => 'videos',
        'album' => 'albums',
        'reel'  => 'videos',
        'photo' => 'album_photos',
    ];
    $table = $table_map[$item_type];

    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$item_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Conteúdo não encontrado.']);
        exit;
    }

    // ── Normalizar tipo para o DB (reel guarda como 'video') ──────────────
    $user_id      = get_current_user_id();
    $db_item_type = ($item_type === 'reel') ? 'video' : $item_type;

    // ── Toggle ────────────────────────────────────────────────────────────
    $check = $pdo->prepare(
        "SELECT id FROM saved_posts WHERE user_id = ? AND item_type = ? AND item_id = ? LIMIT 1"
    );
    $check->execute([$user_id, $db_item_type, $item_id]);
    $existing = $check->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM saved_posts WHERE id = ? LIMIT 1")
            ->execute([$existing['id']]);
        $saved = false;
    } else {
        $pdo->prepare(
            "INSERT INTO saved_posts (user_id, item_type, item_id, created_at) VALUES (?, ?, ?, NOW())"
        )->execute([$user_id, $db_item_type, $item_id]);
        $saved = true;
    }

    // Total de saves (usado pelo view_album.js)
    $cnt = $pdo->prepare(
        "SELECT COUNT(*) FROM saved_posts WHERE item_type = ? AND item_id = ?"
    );
    $cnt->execute([$db_item_type, $item_id]);

    echo json_encode([
        'success'     => true,
        'saved'       => $saved,
        'saves_count' => (int)$cnt->fetchColumn(),
        'message'     => $saved ? 'Guardado com sucesso.' : 'Removido dos guardados.',
    ]);
} catch (PDOException $e) {
    error_log('[toggle_save] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na base de dados. Tente novamente.']);
} catch (Throwable $e) {
    error_log('[toggle_save] Throwable: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}
