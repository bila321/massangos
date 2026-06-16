<?php
// public/edit_video.php

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
require_once __DIR__ . '/../includes/security.php';
// 5. Inicia o sistema de segurança (esta função está em security.php).
SecurityManager::initSecurity();
// 4. Incluir as classes do Core
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Models\Video;
use Massango\Models\FeedItem;

// Check for AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// 6. Incluir o header (only if not AJAX)
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
} else {
    // For AJAX requests, ensure functions are available
    if (!function_exists('display_site_messages')) {
        function display_site_messages(): void
        {
            $messages = get_and_clear_messages();
            if (!empty($messages)) {
                echo '<div class="alert-container" style="position: fixed; top: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px;">';
                foreach ($messages as $message) {
                    $type = htmlspecialchars($message['type'] ?? 'info');
                    $bg = $type === 'danger' ? 'var(--danger)' : ($type === 'success' ? 'var(--success)' : 'var(--info)');
                    echo '<div class="alert" style="background: ' . $bg . '; color: white; padding: 14px 24px; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; min-width: 320px; animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);">';
                    echo '<span style="font-weight: 500; font-size: 0.95rem;">' . htmlspecialchars($message['content'] ?? '') . '</span>';
                    echo '<button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;font-size:1.5rem;line-height:1;">&times;</button>';
                    echo '</div>';
                }
                echo '</div>';
                echo '<style>@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }</style>';
                echo '<script>setTimeout(() => { document.querySelectorAll(".alert-container .alert").forEach(a => { a.style.opacity = "0"; a.style.transform = "translateX(20px)"; a.style.transition = "all 0.5s ease"; }); setTimeout(() => document.querySelector(".alert-container")?.remove(), 500); }, 5000);</script>';
            }
        }
    }
}

// Declara a variável $pdo como global
$pdo = \Massango\Core\Database::getInstance();

// Verifica se o utilizador está logado
if (!is_logged_in()) {
    set_message("Você precisa estar logado para editar vídeos.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();
$video_id = $_GET['id'] ?? null;

if (!$video_id) {
    set_message("ID do vídeo não especificado.", "danger");
    redirect(BASE_URL);
}

// Obter os dados do vídeo
$video = Video::getVideoById($pdo, $video_id);

if (!$video) {
    set_message("Vídeo não encontrado.", "danger");
    redirect(BASE_URL);
}

// Verificar se o utilizador logado é o autor do vídeo
if ($video['user_id'] != $current_user_id) {
    set_message("Você não tem permissão para editar este vídeo.", "danger");
    redirect(BASE_URL);
}

// Obter o feed_item_id associado a este vídeo
$feed_item = FeedItem::getFeedItemById($pdo, $video_id, 'video');
$feed_item_id = $feed_item['feed_item_id'] ?? null;

// Informações do usuário logado para a foto de perfil no formulário
$logged_in_user_profile_pic = $_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png';

?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/edit-modals.css">

<?php if ($is_ajax): ?>
    <div class="edit-modal-header">
        <h2><i class="fa-solid fa-video"></i> Editar Vídeo</h2>
        <button type="button" class="edit-modal-close" onclick="closeEditModal()">&times;</button>
    </div>
    <div class="edit-modal-body">
    <?php else: ?>
        <div class="main-layout-container">
            <main class="main-content-area">
                <section class="edit-video-section card">
                    <h2>Editar Vídeo</h2>
                <?php endif; ?>

                <?php display_site_messages(); // Exibe mensagens de sucesso/erro 
                ?>

                <form action="<?= BASE_URL ?>actions/video.php" method="POST" enctype="multipart/form-data" class="edit-form">
                    <input type="hidden" name="action" value="edit_video">
                    <input type="hidden" name="video_id" value="<?= htmlspecialchars($video['id']) ?>">
                    <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars($feed_item_id) ?>">
                    <input type="hidden" name="old_video_path" value="<?= htmlspecialchars($video['video_path'] ?? '') ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_GET['redirect_to'] ?? 'index.php') ?>">

                    <div class="form-group">
                        <label for="caption">Legenda do Vídeo:</label>
                        <textarea id="caption" name="caption" rows="4" required><?= htmlspecialchars($video['caption']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="video_file">Alterar Arquivo de Vídeo (opcional):</label>
                        <?php if (!empty($video['video_path'])): ?>
                            <p>Vídeo atual:</p>
                            <video src="<?= UPLOAD_URL . htmlspecialchars($video['video_path']) ?>" controls class="current-video-preview" style="max-width: 100%; height: auto; border-radius: 8px;"></video>
                            <br>
                            <input type="checkbox" id="remove_video" name="remove_video" value="1">
                            <label for="remove_video">Remover vídeo atual</label>
                        <?php endif; ?>
                        <input type="file" id="video_file" name="video" accept="video/*">
                    </div>

                    <div class="form-actions <?= $is_ajax ? 'edit-modal-footer' : '' ?>">
                        <button type="submit" class="btn btn-primary <?= $is_ajax ? 'btn-modal-save' : '' ?>">Salvar Alterações</button>
                        <?php if ($is_ajax): ?>
                            <button type="button" class="btn btn-secondary btn-modal-cancel" onclick="closeEditModal()">Cancelar</button>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?><?= htmlspecialchars($_GET['redirect_to'] ?? 'index.php') ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($is_ajax): ?>
        </div> <!-- .edit-modal-body -->
    <?php else: ?>
        </section>
        </main>
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>Dicas de Edição de Vídeo</h4>
                <p>Mantenha as legendas claras e descritivas.</p>
                <p>Vídeos curtos e impactantes geralmente têm mais engajamento.</p>
            </div>
        </aside>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>