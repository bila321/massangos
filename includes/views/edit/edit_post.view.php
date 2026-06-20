<?php
/**
 * View: edit_post.view.php
 *
 * Variáveis disponíveis (definidas em AbstractEditController::render):
 *   @var array  $item          dados do post
 *   @var int|null $feed_item_id
 *   @var bool   $is_ajax
 *   @var string $redirect_to
 */

$shell_icon          = 'fa-pen-to-square';
$shell_title          = 'Editar Publicação';
$shell_section_class  = 'edit-post-section';

require __DIR__ . '/_shell_open.php';
?>

<form action="<?= BASE_URL ?>actions/post.php" method="POST" enctype="multipart/form-data" class="edit-form">
    <input type="hidden" name="action" value="edit_post">
    <input type="hidden" name="post_id" value="<?= htmlspecialchars((string)$item['id']) ?>">
    <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars((string)$feed_item_id) ?>">
    <input type="hidden" name="old_image_path" value="<?= htmlspecialchars($item['image_path'] ?? '') ?>">
    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">

    <div class="form-group">
        <label for="content">Conteúdo da Publicação:</label>
        <textarea id="content" name="content" rows="6" required><?= htmlspecialchars($item['content']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="image">Alterar Imagem (opcional):</label>
        <?php if (!empty($item['image_path'])): ?>
            <p>Imagem atual:</p>
            <img src="<?= UPLOAD_URL . htmlspecialchars($item['image_path']) ?>" alt="Imagem atual" class="current-image-preview">
            <br>
            <input type="checkbox" id="remove_image" name="remove_image" value="1">
            <label for="remove_image">Remover imagem atual</label>
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/*">
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
    <h4>Dicas de Edição</h4>
    <p>Mantenha suas publicações claras e concisas.</p>
    <p>Use imagens de alta qualidade para um melhor impacto.</p>
';

require __DIR__ . '/_shell_close.php';
