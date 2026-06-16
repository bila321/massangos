<?php

require_once __DIR__ . '/_render_comment_html.php';
// includes/functions.php

// --- Importações de Classes ---
// Certifique-se de que o namespace e o nome da classe estão corretos.
// Se sua classe User está em 'core/User.php' e tem "namespace Massango\Models;",
// então o "use" deve ser "use Massango\Models\User;".
use Massango\Models\User;
use Massango\Models\Comment; 
// --- Fim das Importações ---


/**
 * Redireciona o utilizador para uma URL específica.
 * @param string $url A URL para onde redirecionar.
 */
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: " . $url);
        exit();
    }
}

/**
 * Define uma mensagem de notificação na sessão.
 * Permite adicionar múltiplas mensagens para serem exibidas.
 * @param string $message A mensagem a ser exibida.
 * @param string $type O tipo de mensagem (ex: 'success', 'danger', 'warning', 'info').
 */
if (!function_exists('set_message')) {
    function set_message(string $message, string $type = 'info'): void
    {
        // A sessão DEVE ser iniciada uma vez no script principal (ex: public/index.php)
        // antes de qualquer chamada a set_message ou get_and_clear_messages.
        // O check session_status() aqui é uma segurança, mas a inicialização centralizada é melhor.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['site_messages'])) {
            $_SESSION['site_messages'] = [];
        }
        $_SESSION['site_messages'][] = [
            'content' => $message,
            'type' => $type
        ];
    }
}

/**
 * Obtém todas as mensagens de notificação da sessão e as limpa.
 * @return array Um array de mensagens, cada uma com 'content' e 'type'.
 */
if (!function_exists('get_and_clear_messages')) {
    function get_and_clear_messages(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $messages = $_SESSION['site_messages'] ?? [];
        unset($_SESSION['site_messages']); // Limpa as mensagens após obtê-las
        return $messages;
    }
}

/**
 * Verifica se um utilizador está logado.
 * @return bool True se o utilizador estiver logado, False caso contrário.
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Verifica user_id e se a sessão ainda é válida (opcionalmente com login_time)
        return isset($_SESSION['user_id']) && (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] < SESSION_TIMEOUT));
    }
}

/**
 * Retorna o ID do utilizador atualmente logado.
 * @return int|null O ID do utilizador ou null se não estiver logado.
 */
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Formata uma data/hora para exibir "X tempo atrás".
 * @param string $datetime A string de data/hora a ser formatada (ex: 'YYYY-MM-DD HH:MM:SS').
 * @return string A string formatada.
 */
if (!function_exists('format_datetime_ago')) {
    function format_datetime_ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'agora mesmo';
        } elseif ($diff < 3600) {
            $minutes = round($diff / 60);
            return $minutes . ' minuto' . ($minutes > 1 ? 's' : '') . ' atrás';
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' atrás';
        } elseif ($diff < 2592000) { // Menos de 30 dias (aproximadamente um mês)
            $days = round($diff / 86400);
            return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }
}

/**
 * Sanitiza e filtra os dados de entrada para prevenir ataques XSS e SQL Injection básicos.
 * Esta função é para inputs gerais e básicos. Para validações mais complexas, use SecurityManager::validateInput.
 * @param string $data Os dados a serem sanitizados.
 * @return string Os dados sanitizados.
 */
if (!function_exists('sanitize_input')) {
    function sanitize_input(string $data): string
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Exibe uma lista de comentários recursivamente.
 * @param array $comments Array de comentários (já aninhados, como retornado por Comment::getCommentsForFeedItem).
 * @param int|null $currentUserId ID do usuário logado, se houver.
 * @param bool $isPostOwner True se o usuário logado for o dono da publicação principal.
 * @param PDO $pdo O objeto PDO, necessário para funções como votar em comentários.
 * @param int $level Nível de aninhamento atual (para indentação).
 */
if (!function_exists('display_comments')) {
    function display_comments(array $comments, ?int $currentUserId, bool $isPostOwner, PDO $pdo, int $level = 0)
    {
        if (empty($comments)) {
            return;
        }

        // A margem para indentação será aplicada pelo JavaScript no <li>
        // O UL principal não precisa de margin-left aqui.
        // Se for nível 0 (comentário principal), não há display: none.
        // Se for nível > 0 (resposta), adiciona comment-replies e display: none.
        echo '<ul class="comment-list ' . ($level > 0 ? 'comment-replies' : '') . '" ' . ($level > 0 ? 'style="display: none;"' : '') . '>';

        foreach ($comments as $comment) {
            $author = User::getUserById($pdo, $comment['user_id']);
            if (!$author) {
                $author = ['username' => 'Usuário Desconhecido', 'profile_picture' => 'profiles/default_profile.png', 'id' => 0];
            }

            // Garante que UPLOAD_URL é uma constante definida em config.php
            $profile_picture_url = UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'profiles/default_profile.png');

            $is_comment_owner = ($currentUserId && $comment['user_id'] == $currentUserId);
            $can_delete_comment = ($is_comment_owner || $isPostOwner);

            $likes_count = (int)($comment['likes_count'] ?? 0);
            $dislikes_count = (int)($comment['dislikes_count'] ?? 0);
            $user_vote = $comment['user_vote'] ?? null;

            // Calcula a margem para o item do comentário
            // Se for um comentário de nível superior (level 0), sem margem.
            // Se for uma resposta (level > 0), aplica uma margem fixa de 25px.
            $item_margin_left = $level === 0 ? 0 : 25;
?>
            <li class="comment-item" data-comment-id="<?= htmlspecialchars($comment['id']) ?>">
                <img src="<?= $profile_picture_url ?>"
                    alt="Foto de perfil de <?= htmlspecialchars($author['username']) ?>"
                    class="comment-avatar">
                <div class="comment-body">
                    <div class="comment-text-wrapper">
                        <div class="comment-header">
                            <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>" class="comment-author"><?= htmlspecialchars($author['username']) ?></a>
                            <div class="comment-actions-dropdown">
                                <button class="dropdown-toggle" aria-label="Opções do comentário">&#x22EE;</button>
                                <div class="dropdown-menu">
                                    <?php if ($is_comment_owner): ?>
                                        <button class="edit-comment-btn" data-comment-id="<?= htmlspecialchars($comment['id']) ?>" data-content="<?= htmlspecialchars($comment['content']) ?>">Editar</button>
                                    <?php endif; ?>
                                    <?php if ($can_delete_comment): ?>
                                        <button class="delete-comment-btn"
                                            data-comment-id="<?= htmlspecialchars($comment['id']) ?>"
                                            data-feed-item-id="<?= htmlspecialchars($comment['feed_item_id']) ?>">Apagar</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="comment-text">
                            <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                        </div>
                    </div>
                    <div class="comment-actions">
                        <span class="comment-time"><?= format_datetime_ago($comment['created_at']) ?></span>
                        <button class="btn-comment-like <?= ($user_vote === 'like' ? 'active' : '') ?>"
                            data-comment-id="<?= htmlspecialchars($comment['id']) ?>"
                            data-source="photo"
                            data-vote-type="like">
                            <i class="fa-<?= $user_vote ? 'solid' : 'regular' ?> fa-heart"></i>
                            <span class="comment-likes-count"><?= htmlspecialchars($likes_count) ?></span>
                        </button>
                        <?php if ($currentUserId): ?>
                            <button class="btn-reply-comment"
                                data-comment-id="<?= htmlspecialchars($comment['id']) ?>"
                                data-comment-author-username="<?= htmlspecialchars($author['username']) ?>">Responder</button>
                        <?php endif; ?>
                    </div>

                    <?php if ($currentUserId): ?>
                        <div class="reply-form-container" id="replyFormContainer-<?= htmlspecialchars($comment['id']) ?>" style="display: none;">
                            <form action="<?= BASE_URL ?>process_comment.php" method="POST" class="reply-form">
                                <input type="hidden" name="action" value="add_reply">
                                <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars($comment['feed_item_id']) ?>">
                                <input type="hidden" name="parent_comment_id" value="<?= htmlspecialchars($comment['id']) ?>">

                                <div class="reply-input-area">
                                    <?php
                                    $logged_in_user_profile_pic = $_SESSION['user_profile_picture'] ?? BASE_URL . 'assets/img/default_profile.png';
                                    ?>
                                    <img src="<?= htmlspecialchars($logged_in_user_profile_pic) ?>"
                                        alt="Sua foto de perfil"
                                        class="comment-avatar reply-form-avatar">
                                    <textarea name="comment_content" class="reply-textarea" placeholder="Escreva sua resposta..." required></textarea>
                                </div>

                                <div class="reply-form-actions">
                                    <button type="submit" class="reply-btn-send">Responder</button>
                                    <button type="button" class="reply-btn-cancel cancel-reply-btn" data-comment-id="<?= htmlspecialchars($comment['id']) ?>">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Chamada recursiva para exibir as respostas
                    if (!empty($comment['replies'])) {
                        // O UL interno já terá as classes e o estilo display:none;
                        // O nível de indentação é passado para a recursão
                        display_comments($comment['replies'], $currentUserId, $isPostOwner, $pdo, $level + 1);

                        // Adiciona o botão "Ver X respostas" aqui
                        $replies_count = count($comment['replies']);
                        echo '<button class="btn-toggle-replies" data-comment-id="' . htmlspecialchars($comment['id']) . '">';
                        echo 'Ver ' . htmlspecialchars($replies_count) . ' resposta' . ($replies_count > 1 ? 's' : '');
                        echo '</button>';
                    }
                    ?>
                </div>
            </li>
<?php
        }
        echo '</ul>';
    }
}
/**
 * Gera uma URL protegida para mídias usando o media-proxy.php.
 * @param string $relativePath Caminho relativo do arquivo a partir da pasta uploads.
 * @return string URL completa e protegida.
 */
if (!function_exists('get_protected_media_url')) {
    function get_protected_media_url(string $relativePath): string
    {
        if (empty($relativePath)) return '';

        // Limpar o caminho
        $relativePath = ltrim($relativePath, '/');
        $mediaId = base64_encode($relativePath);
        $mediaIdClean = str_replace('=', '', $mediaId);

        $timestamp = time() * 1000; // Milissegundos
        $random = bin2hex(random_bytes(4));

        $hash = '';
        if (defined('SECURITY_SALT')) {
            $hash = hash_hmac('sha256', "$mediaIdClean:$timestamp:$random", SECURITY_SALT);
        }

        $token = base64_encode("$mediaIdClean:$timestamp:$random:$hash");

        return BASE_URL . "media-proxy.php?id=" . urlencode($mediaIdClean) . "&t=" . urlencode($token);
    }
}


// ---------------------------------------------------------------------------
// Helpers de media — movidos de public/profile.php
// ---------------------------------------------------------------------------

if (!function_exists('format_duration')) {
    function format_duration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        $m = floor($seconds / 60);
        $s = $seconds % 60;
        return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
    }
}

if (!function_exists('get_quality_badge')) {
    function get_quality_badge(?int $duration): string
    {
        if ($duration === null) return '';
        if ($duration >= 120) return '<span class="quality-badge fhd">FHD</span>';
        if ($duration >= 30)  return '<span class="quality-badge hd">HD</span>';
        return '<span class="quality-badge sd">SD</span>';
    }
}

/**
 * Retorna o URL completo do thumbnail de um v�deo.
 * Os thumbnails ficam em storage/uploads/videos/thumbnails/.
 * Evita duplicar o prefixo se o caminho j� o incluir.
 */
function get_video_thumb_url(string $thumbnail_path): string
{
    if (empty($thumbnail_path)) return '';
    if (str_starts_with($thumbnail_path, 'videos/thumbnails/')) {
        return UPLOAD_URL . $thumbnail_path;
    }
    return UPLOAD_URL . 'videos/thumbnails/' . ltrim($thumbnail_path, '/');
}
