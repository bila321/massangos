<?php
// public/process_notification.php


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

// 4. Usar a classe Notification do seu namespace
use Massango\Models\Notification;
use Massango\Models\Like;
// 5. Definir o cabeçalho para indicar que a resposta é JSON.
// É crucial que esta linha seja executada antes de QUALQUER saída HTML ou texto.
header('Content-Type: application/json');

// Inicializar o array de resposta
$response = ['success' => false, 'message' => '', 'unread_count' => 0];

try {
    // 1. Verificação de Autenticação
    $current_user_id = get_current_user_id();
    if (!is_logged_in() || !$current_user_id) {
        $response['message'] = "Você precisa estar logado para realizar esta ação.";
        echo json_encode($response);
        exit();
    }

    // 2. Obter o ID do usuário logado e a ação solicitada
    $action = $_POST['action'] ?? ''; // Pega a ação do POST

    // Usar um switch para lidar com diferentes ações de forma mais limpa
    switch ($action) {
        case 'mark_read':
            $notification_id = (int) ($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                if (Notification::markAsRead($pdo, $notification_id, $current_user_id)) {
                    $response['success'] = true;
                    $response['message'] = "Notificação marcada como lida.";
                } else {
                    $response['message'] = "Erro ao marcar notificação como lida. Verifique se a notificação pertence ao usuário.";
                }
            } else {
                $response['message'] = "ID da notificação inválido.";
            }
            break;

        case 'clear_read':
            if (Notification::clearReadNotifications($pdo, $current_user_id)) {
                $response['success'] = true;
                $response['message'] = "Notificações lidas limpas com sucesso.";
            } else {
                $response['message'] = "Erro ao limpar notificações lidas.";
            }
            break;

        default:
            $response['message'] = "Ação inválida ou não especificada.";
            break;
    }

    // Sempre obter a contagem atual de notificações não lidas e incluí-la na resposta
    // Isso permite que o frontend atualize o badge de notificações em tempo real.
    $response['unread_count'] = Notification::getUnreadNotificationCount($pdo, $current_user_id);
} catch (PDOException $e) {
    // Captura erros específicos do PDO (problemas com a base de dados)
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("PDO Error in process_notification.php: " . $e->getMessage() . " | User ID: " . ($current_user_id ?? 'N/A'));
} catch (Exception $e) {
    // Captura quaisquer outros erros inesperados
    $response['message'] = 'Ocorreu um erro inesperado no servidor: ' . $e->getMessage();
    error_log("General Error in process_notification.php: " . $e->getMessage() . " | User ID: " . ($current_user_id ?? 'N/A'));
}

// Garante que qualquer buffer de saída anterior seja limpo
// Isso é crucial para evitar saída HTML/texto antes do JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Enviar a resposta JSON e sair
echo json_encode($response);
exit();
