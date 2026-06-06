<?php
/**
 * DEPRECATED - Usa public/api/photo_interactions.php
 * Data: 20260606_175439
 */
// public/process_like.php


define('SECURE_ACCESS', true);

// **2. DEFINE O AMBIENTE AQUI (apenas uma vez)!**
define('ENVIRONMENT', 'development'); // Usa 'development' durante o desenvolvimento
// OR
// define('ENVIRONMENT', 'production'); // Usa 'production' quando fores para o servidor real

// 3. Inclui o arquivo de configuração.
require_once __DIR__ . '/../includes/config.php';

// 4. Inclui outros arquivos essenciais (db, functions, se tiveres).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 5. Inclui o arquivo de segurança.
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
// 3. Incluir o autoloader do Composer. Isso carregará automaticamente as classes.
// É crucial que seja feito APÓS as configurações básicas e o DB, mas ANTES de usar as classes.
require_once __DIR__ . '/../vendor/autoload.php';

// 4. Usar as classes do Core através dos "use" statements
// Estas classes já devem estar no namespace massangos\Core\
use Massango\Models\Like;
use Massango\Models\FeedItem; // Necessário para Like::toggleFeedItemLikeDislike obter o dono do post
use Massango\Models\User;     // Necessário para Like::toggleFeedItemLikeDislike obter o username do sender
use Massango\Models\Notification; // Necessário para Like::toggleFeedItemLikeDislike criar/deletar notificações

// 5. Definir o cabeçalho para garantir que a resposta é JSON.
// É crucial que esta linha seja executada antes de QUALQUER saída HTML ou texto.
header('Content-Type: application/json');

// Inicializa a array de resposta padrão
$response = ['success' => false, 'message' => ''];

try {
    // 1. Verificação de Autenticação
    $current_user_id = get_current_user_id();
    if (!is_logged_in() || !$current_user_id) {
        $response['message'] = 'Você precisa estar logado para interagir.';
        echo json_encode($response);
        exit; // Termina a execução imediatamente
    }

    // 2. Coleta e Validação dos Dados da Requisição
    $feed_item_id = filter_input(INPUT_POST, 'feed_item_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING); // 'like' ou 'dislike'

    // Garante que o action é válido
    if (!in_array($action, ['like', 'dislike'])) {
        $response['message'] = 'Ação inválida. Apenas "like" ou "dislike" são permitidos.';
        echo json_encode($response);
        exit;
    }

    if (!$feed_item_id) { // Verifica se feed_item_id é um inteiro válido e não 0 ou nulo
        $response['message'] = 'ID do item de feed inválido fornecido.';
        echo json_encode($response);
        exit; // Termina a execução imediatamente
    }

    // 3. Processamento da Interação (Like/Dislike)
    // O método toggleFeedItemLikeDislike já lida com adicionar, remover ou trocar o voto,
    // e internamente chamará FeedItem, User, e Notification.
    $toggle_success = Like::toggleFeedItemLikeDislike($pdo, $feed_item_id, $current_user_id, $action);

    if ($toggle_success) {
        // 4. Obter Contagens e Estado do Voto Atualizados
        $like_info = Like::getFeedItemLikesDislikesCount($pdo, $feed_item_id);
        $user_vote = Like::getUserFeedItemVote($pdo, $feed_item_id, $current_user_id);

        $response['success'] = true;
        $response['likes'] = $like_info['likes'];
        $response['dislikes'] = $like_info['dislikes'];
        $response['user_vote'] = $user_vote; // 'like', 'dislike', ou null
        $response['message'] = 'Interação registrada com sucesso.';
    } else {
        // Se a operação no banco de dados falhou por algum motivo (ex: erro SQL silencioso)
        // Isso pode indicar um problema com a query SQL ou conexão DB
        $response['message'] = 'Falha ao processar a interação com o banco de dados. Tente novamente.';
        error_log("DB Operation Failed in process_like.php for feed_item_id: $feed_item_id, user_id: $current_user_id, action: $action");
    }
} catch (PDOException $e) {
    // Captura erros específicos do PDO (problemas com a base de dados)
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    // Registra o erro para depuração no log do servidor
    error_log("PDO Error in process_like.php: " . $e->getMessage() . " | Feed Item ID: " . ($feed_item_id ?? 'N/A') . " | User ID: " . ($current_user_id ?? 'N/A'));
} catch (Exception $e) {
    // Captura quaisquer outros erros inesperados (lógica, classes não encontradas, etc.)
    $response['message'] = 'Ocorreu um erro inesperado no servidor: ' . $e->getMessage();
    // Registra o erro para depuração no log do servidor
    error_log("General Error in process_like.php: " . $e->getMessage() . " | Feed Item ID: " . ($feed_item_id ?? 'N/A') . " | User ID: " . ($current_user_id ?? 'N/A'));
}

// Garante que qualquer buffer de saída anterior seja limpo
// Isso é crucial para evitar saída HTML/texto antes do JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Envia a resposta JSON final para o cliente
echo json_encode($response);
exit; // Garante que nenhuma outra coisa seja impressa depois