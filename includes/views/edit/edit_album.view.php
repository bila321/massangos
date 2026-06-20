<?php
/**
 * View: edit_album.view.php
 *
 * Variáveis disponíveis (definidas em AbstractEditController::render
 * + EditAlbumController::extraViewData):
 *   @var array  $item          dados do álbum
 *   @var array  $photos        fotos do álbum
 *   @var int|null $feed_item_id
 *   @var bool   $is_ajax
 *   @var string $redirect_to
 */

$shell_icon          = 'fa-images';
$shell_title          = 'Editar Álbum: ' . $item['album_name'];
$shell_section_class  = 'edit-album-section';

require __DIR__ . '/_shell_open.php';
?>

<?php if ($item['is_for_sale']): ?>
    <div class="album-partners-banner">
        <a href="<?= BASE_URL ?>manage_album_partners.php?album_id=<?= (int)$item['id'] ?>" class="btn-banner-link btn-banner-partners">
            👥 Gerenciar Parceiros
        </a>
        <a href="<?= BASE_URL ?>album_distribution_history.php?album_id=<?= (int)$item['id'] ?>" class="btn-banner-link btn-banner-history">
            📊 Histórico de Vendas
        </a>
    </div>
<?php endif; ?>

<form action="<?= BASE_URL ?>actions/album.php" method="POST" enctype="multipart/form-data" class="edit-form">
    <input type="hidden" name="action" value="edit_album">
    <input type="hidden" name="album_id" value="<?= htmlspecialchars((string)$item['id']) ?>">
    <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars((string)$feed_item_id) ?>">
    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">

    <div class="form-group">
        <label for="album_name">Nome do Álbum:</label>
        <input type="text" id="album_name" name="album_name"
            value="<?= htmlspecialchars($item['album_name']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Descrição do Álbum:</label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>Fotos Atuais:</label>
        <div class="current-photos-grid">
            <?php if (!empty($photos)): ?>
                <?php foreach ($photos as $photo): ?>
                    <div class="photo-item">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($photo['photo_path']) ?>"
                            alt="Foto do Álbum" class="album-photo-preview">
                        <input type="checkbox" name="remove_photos[]"
                            value="<?= htmlspecialchars((string)$photo['id']) ?>"
                            id="remove_photo_<?= htmlspecialchars((string)$photo['id']) ?>">
                        <label for="remove_photo_<?= htmlspecialchars((string)$photo['id']) ?>">Remover</label>
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
            <a href="<?= BASE_URL . htmlspecialchars($redirect_to) ?>" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<?php
$sidebar_tips_html = '
    <h4>Dicas de Edição de Álbum</h4>
    <p>Você pode remover fotos existentes e adicionar novas.</p>
    <p>A primeira foto adicionada ou a que tiver a menor ID (se não for removida) será a capa do álbum.</p>
';

if ($item['is_for_sale']) {
    $sidebar_tips_html .= '
        <hr class="sidebar-divider">
        <h4>Parceria de Vendas</h4>
        <p>Você pode adicionar parceiros para compartilhar as receitas deste álbum.</p>
        <p>Cada parceiro receberá sua percentagem automaticamente após cada venda.</p>
    ';
}

require __DIR__ . '/_shell_close.php';
