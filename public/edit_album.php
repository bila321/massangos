<?php
// public/edit_album.php

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

// 5. Inclui o arquivo de segurança.
require_once __DIR__ . '/../includes/security.php';


// 4. Incluir as classes do Core
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Photo; // Precisamos da classe Photo para gerir fotos do álbum

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
    set_message("Você precisa estar logado para editar álbuns.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();
$album_id = $_GET['id'] ?? null;

if (!$album_id) {
    set_message("ID do álbum não especificado.", "danger");
    redirect(BASE_URL);
}

// Obter os dados do álbum
$album = Album::getAlbumById($pdo, $album_id);

if (!$album) {
    set_message("Álbum não encontrado.", "danger");
    redirect(BASE_URL);
}

// Verificar se o utilizador logado é o autor do álbum
if ($album['user_id'] != $current_user_id) {
    set_message("Você não tem permissão para editar este álbum.", "danger");
    redirect(BASE_URL);
}

// Obter as fotos do álbum
$photos = Photo::getPhotosByAlbumId($pdo, $album_id);

// Obter o feed_item_id associado a este álbum
$feed_item = FeedItem::getFeedItemById($pdo, $album_id, 'album');
$feed_item_id = $feed_item['feed_item_id'] ?? null;

// Informações do usuário logado para a foto de perfil no formulário
$logged_in_user_profile_pic = $_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png';

?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/edit-modals.css">

<?php if ($is_ajax): ?>
    <div class="edit-modal-header">
        <h2><i class="fa-solid fa-images"></i> Editar Álbum: <?= htmlspecialchars($album['album_name']) ?></h2>
        <button type="button" class="edit-modal-close" onclick="closeEditModal()">&times;</button>
    </div>
    <div class="edit-modal-body">
    <?php else: ?>
        <div class="main-layout-container">
            <main class="main-content-area">
                <section class="edit-album-section card">
                    <h2>Editar Álbum: <?= htmlspecialchars($album['album_name']) ?></h2>
                <?php endif; ?>

                <?php display_site_messages(); // Exibe mensagens de sucesso/erro 
                ?>

                <?php if ($album['is_for_sale']): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: rgba(33, 150, 243, 0.1); border-left: 3px solid #2196F3; border-radius: 4px; display: flex; gap: 10px;">
                        <a href="<?= BASE_URL ?>manage_album_partners.php?album_id=<?= $album['id'] ?>" style="display: inline-block; padding: 8px 16px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">
                            👥 Gerenciar Parceiros
                        </a>
                        <a href="<?= BASE_URL ?>album_distribution_history.php?album_id=<?= $album['id'] ?>" style="display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">
                            📊 Historico de Vendas
                        </a>
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>process_album_post.php" method="POST" enctype="multipart/form-data" class="edit-form">
                    <input type="hidden" name="action" value="edit_album">
                    <input type="hidden" name="album_id" value="<?= htmlspecialchars($album['id']) ?>">
                    <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars($feed_item_id) ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_GET['redirect_to'] ?? 'index.php') ?>">

                    <div class="form-group">
                        <label for="album_name">Nome do Álbum:</label>
                        <input type="text" id="album_name" name="album_name" value="<?= htmlspecialchars($album['album_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição do Álbum:</label>
                        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($album['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Fotos Atuais:</label>
                        <div class="current-photos-grid">
                            <?php if (!empty($photos)): ?>
                                <?php foreach ($photos as $photo): ?>
                                    <div class="photo-item">
                                        <img src="<?= UPLOAD_URL . htmlspecialchars($photo['photo_path']) ?>" alt="Foto do Álbum" class="album-photo-preview">
                                        <input type="checkbox" name="remove_photos[]" value="<?= htmlspecialchars($photo['id']) ?>" id="remove_photo_<?= htmlspecialchars($photo['id']) ?>">
                                        <label for="remove_photo_<?= htmlspecialchars($photo['id']) ?>">Remover</label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Nenhuma foto neste álbum ainda.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_images">Adicionar Novas Fotos (opcional):</label>
                        <input type="file" id="new_images" name="new_images[]" accept="image/*" multiple>
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
                <h4>Dicas de Edição de Álbum</h4>
                <p>Você pode remover fotos existentes e adicionar novas.</p>
                <p>A primeira foto adicionada ou a que tiver a menor ID (se não for removida) será a capa do álbum.</p>

                <?php if ($album['is_for_sale']): ?>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid var(--border-light);">
                    <h4>Parceria de Vendas</h4>
                    <p>Você pode adicionar parceiros para compartilhar as receitas deste álbum.</p>
                    <p>Cada parceiro receberá sua percentagem automaticamente após cada venda.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>