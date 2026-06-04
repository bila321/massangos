<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Você precisa estar logado.']);
    exit;
}

$current_user_id = get_current_user_id();
$feed_item_id = isset($_POST['feed_item_id']) ? (int)$_POST['feed_item_id'] : 0;

if ($feed_item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de publicação inválido.']);
    exit;
}

try {
    // Primeiro verifica se a tabela hidden_posts existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS hidden_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        feed_item_id INT NOT NULL,
        hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_feed_item (user_id, feed_item_id)
    )");

    $stmt = $pdo->prepare("INSERT IGNORE INTO hidden_posts (user_id, feed_item_id) VALUES (?, ?)");
    $result = $stmt->execute([$current_user_id, $feed_item_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Publicação ocultada com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao ocultar publicação.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
