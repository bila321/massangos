<?php
/**
 * View: edit_video.view.php
 *
 * Variáveis disponíveis (definidas em AbstractEditController::render):
 *   @var array  $item          dados do vídeo
 *   @var int|null $feed_item_id
 *   @var bool   $is_ajax
 *   @var string $redirect_to
 */

$shell_icon          = 'fa-video';
$shell_title          = 'Editar Vídeo';
$shell_section_class  = 'edit-video-section';

require __DIR__ . '/_shell_open.php';
?>

<form action="<?= BASE_URL ?>actions/video.php" method="POST" enctype="multipart/form-data" class="edit-form">
    <input type="hidden" name="action" value="edit_video">
    <input type="hidden" name="video_id" value="<?= htmlspecialchars((string)$item['id']) ?>">
    <input type="hidden" name="feed_item_id" value="<?= htmlspecialchars((string)$feed_item_id) ?>">
    <input type="hidden" name="old_video_path" value="<?= htmlspecialchars($item['video_path'] ?? '') ?>">
    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">

    <div class="form-group">
        <label for="caption">Legenda do Vídeo:</label>
        <textarea id="caption" name="caption" rows="4" required><?= htmlspecialchars($item['caption']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="video_file">Alterar Arquivo de Vídeo (opcional):</label>
        <?php if (!empty($item['video_path'])): ?>
            <p>Vídeo atual:</p>
            <video src="<?= UPLOAD_URL . htmlspecialchars($item['video_path']) ?>" controls
                class="current-video-preview"></video>
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
            <a href="<?= BASE_URL . htmlspecialchars($redirect_to) ?>" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<?php
$sidebar_tips_html = '
    <h4>Dicas de Edição de Vídeo</h4>
    <p>Mantenha as legendas claras e descritivas.</p>
    <p>Vídeos curtos e impactantes geralmente têm mais engajamento.</p>
';

require __DIR__ . '/_shell_close.php';
