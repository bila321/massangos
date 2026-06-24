<?php
// public/album.php

// **1. DEFINE SECURE_ACCESS BEM NO TOPO!**
define('SECURE_ACCESS', true);

// **2. DEFINE O AMBIENTE AQUI (apenas uma vez)!**
define('ENVIRONMENT', 'development'); // Usa 'development' durante o desenvolvimento

// 3. Inclui o arquivo de configuração.
require_once __DIR__ . '/../includes/config.php';

// 4. Inclui outros arquivos essenciais (db, functions, se tiveres).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

use Massango\Models\User;
use Massango\Models\Album;

if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar os álbuns.", "danger");
    redirect(BASE_URL . 'login.php');
}
// --- LÓGICA DE DADOS ---

// Obter o ID do utilizador cuja página de álbuns está a ser visitada
$profile_user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Se nenhum ID de usuário for fornecido na URL, usa o ID do usuário logado como padrão
if (!$profile_user_id) {
    if (is_logged_in()) {
        $profile_user_id = get_current_user_id();
    } else {
        set_message("Faça login para ver seus álbuns.", "info");
        redirect(BASE_URL . 'login.php');
    }
}

$author = User::getUserById($pdo, $profile_user_id);

if (!$author) {
    set_message("Usuário de álbuns não encontrado.", "danger");
    redirect(BASE_URL);
}

$is_owner = (is_logged_in() && $profile_user_id == get_current_user_id());

// Lógica para criar novo álbum (apenas se for o proprietário)
if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['album_name'])) {
    $album_name = sanitize_input($_POST['album_name']);
    $album_description = sanitize_input($_POST['album_description'] ?? '');

    if (!empty($album_name)) {
        if (Album::createAlbum($pdo, $profile_user_id, $album_name, $album_description, null)) {
            set_message("Álbum criado com sucesso!", "success");
            redirect(BASE_URL . 'album.php?id=' . $profile_user_id);
        } else {
            set_message("Erro ao criar álbum.", "danger");
        }
    } else {
        set_message("O nome do álbum é obrigatório.", "warning");
    }
}

// Obter todos os álbuns do utilizador
$albums = Album::getAlbumsByUserId($pdo, $profile_user_id);

// --- FIM DA LÓGICA DE DADOS ---

require_once __DIR__ . '/../includes/header.php';
?>

<div class="main-layout-container">
    <main class="main-content-area">
        <section class="albums-section">
            <h2>Álbuns de Fotos de <?= htmlspecialchars($author['username']) ?></h2>

            <?php if ($is_owner): ?>
                <div class="add-album-btn-container">
                    <button class="btn btn-add-new" onclick="document.getElementById('createAlbumModal').style.display='block'">+ Novo Álbum</button>
                </div>
            <?php endif; ?>

            <div class="albums-grid">
                <?php if (!empty($albums)): ?>
                    <?php foreach ($albums as $album): ?>
                        <div class="album-card card">
                            <a href="<?= BASE_URL ?>view_album.php?id=<?= $album['id'] ?>">
                                <?php if (!empty($album['cover_photo_url'])): ?>
                                    <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>" alt="Capa do Álbum" class="album-cover">
                                <?php else: ?>
                                    <div class="album-placeholder">
                                        <i class="fa-solid fa-images"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="album-info">
                                    <h3><?= htmlspecialchars($album['name']) ?></h3>
                                    <p><?= htmlspecialchars($album['description'] ?? '') ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nenhum álbum encontrado.</p>
                <?php endif; ?>
            </div>

        </section>
    </main>
</div>

<?php if ($is_owner): ?>
    <!-- Create Album Modal -->
    <div id="createAlbumModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="document.getElementById('createAlbumModal').style.display='none'">&times;</span>
            <h2>Criar Novo Álbum</h2>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="album_name">Nome do Álbum:</label>
                    <input type="text" name="album_name" id="album_name" required>
                </div>
                <div class="form-group">
                    <label for="album_description">Descrição:</label>
                    <textarea name="album_description" id="album_description" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Criar Álbum</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>