<?php
// public/index.php ou outro script principal

// **1. DEFINE SECURE_ACCESS BEM NO TOPO!**
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
require_once __DIR__ . '/../includes/header.php';
// 5. Inclui o arquivo de segurança.
require_once __DIR__ . '/../includes/security.php';

// 5. Inicia o sistema de segurança (esta função está em security.php).

use Massango\Models\User;

// Declara a variável $pdo como global
$pdo = \Massango\Core\Database::getInstance();
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    set_message("ID de usuário não especificado.", "danger");
    redirect(BASE_URL);
}

$profile_user_data = User::getUserById($pdo, $user_id);

if (!$profile_user_data) {
    set_message("Usuário não encontrado.", "danger");
    redirect(BASE_URL);
}

$current_user_id = get_current_user_id();

// Verificar bloqueio
if ($current_user_id && $current_user_id != $user_id) {
    $is_blocked_by_me = User::isBlocking($pdo, $current_user_id, $user_id);
    $am_i_blocked = User::isBlocking($pdo, $user_id, $current_user_id);
    if ($is_blocked_by_me || $am_i_blocked) {
        set_message("Acesso restrito.", "danger");
        redirect(BASE_URL);
    }
}

$followers = User::getFollowersList($pdo, $user_id);

?>

<style>
    .user-list-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .user-list-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .user-list-item:last-child {
        border-bottom: none;
    }

    .user-list-item img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
    }

    .user-list-item .user-info {
        flex-grow: 1;
    }

    .user-list-item .user-info a {
        font-weight: bold;
        color: var(--primary-color);
        text-decoration: none;
    }

    .user-list-item .user-info a:hover {
        text-decoration: underline;
    }

    .user-list-item .follow-button {
        padding: 8px 15px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9em;
        transition: background-color 0.2s ease;
    }

    .user-list-item .btn-primary {
        background-color: var(--primary-color);
        color: #fff;
        border: 1px solid var(--primary-color);
    }

    .user-list-item .btn-primary:hover {
        background-color: var(--primary-dark-color);
    }

    .user-list-item .btn-danger {
        background-color: #dc3545;
        color: #fff;
        border: 1px solid #dc3545;
    }

    .user-list-item .btn-danger:hover {
        background-color: #c82333;
    }
</style>

<div class="user-list-container">
    <h2>Seguidores de <?= htmlspecialchars($profile_user_data['username']) ?></h2>

    <?php if (!empty($followers)): ?>
        <?php foreach ($followers as $follower): ?>
            <div class="user-list-item">
                <img src="<?= UPLOAD_URL . htmlspecialchars($follower['profile_picture'] ?? 'default_profile.png') ?>" alt="Foto de perfil de <?= htmlspecialchars($follower['username']) ?>">
                <div class="user-info">
                    <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($follower['id']) ?>"><?= htmlspecialchars($follower['username']) ?></a>
                </div>
                <?php if ($current_user_id && $current_user_id != $follower['id']): ?>
                    <?php
                    $is_following_this_follower = User::isFollowing($pdo, $current_user_id, $follower['id']);
                    ?>
                    <form action="" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="<?= $is_following_this_follower ? 'unfollow' : 'follow' ?>">
                        <input type="hidden" name="target_user_id" value="<?= htmlspecialchars($follower['id']) ?>">
                        <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button type="submit" class="follow-button <?= $is_following_this_follower ? 'btn-danger' : 'btn-primary' ?>">
                            <?= $is_following_this_follower ? 'Deixar de Seguir' : 'Seguir' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Este usuário ainda não tem seguidores.</p>
    <?php endif; ?>
</div>

<?php
// Lógica para processar follow/unfollow nesta página
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['target_user_id'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $redirect_url = $_POST['redirect_url'] ?? BASE_URL . 'followers.php?id=' . $user_id;

    if ($current_user_id && $current_user_id != $target_user_id) {
        if ($_POST['action'] === 'follow') {
            if (User::followUser($pdo, $current_user_id, $target_user_id)) {
                set_message("Você começou a seguir " . htmlspecialchars(User::getUserById($pdo, $target_user_id)['username']) . "!", "success");
            } else {
                set_message("Erro ao seguir usuário.", "danger");
            }
        } elseif ($_POST['action'] === 'unfollow') {
            if (User::unfollowUser($pdo, $current_user_id, $target_user_id)) {
                set_message("Você deixou de seguir " . htmlspecialchars(User::getUserById($pdo, $target_user_id)['username']) . ".", "info");
            } else {
                set_message("Erro ao deixar de seguir usuário.", "danger");
            }
        }
    }
    redirect($redirect_url);
}

require_once __DIR__ . '/../includes/footer.php';
?>