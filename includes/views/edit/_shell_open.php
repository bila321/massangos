<?php
/**
 * Partial: _shell_open.php
 *
 * Abre o "casco" da página de edição — modal AJAX ou layout de página completa.
 * Usado por edit_post.view.php, edit_video.view.php e edit_album.view.php.
 *
 * Variáveis esperadas:
 *   @var bool   $is_ajax
 *   @var string $shell_icon    classe fa-solid, ex: 'fa-pen-to-square'
 *   @var string $shell_title   ex: 'Editar Publicação'
 *   @var string $shell_section_class  ex: 'edit-post-section'
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/edit-modals.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/edit-modals-extra.css">

<?php if ($is_ajax): ?>
    <div class="edit-modal-header">
        <h2><i class="fa-solid <?= $shell_icon ?>"></i> <?= htmlspecialchars($shell_title) ?></h2>
        <button type="button" class="edit-modal-close" onclick="closeEditModal()">&times;</button>
    </div>
    <div class="edit-modal-body">
<?php else: ?>
    <div class="main-layout-container">
        <main class="main-content-area">
            <section class="<?= htmlspecialchars($shell_section_class) ?> card">
                <h2><?= htmlspecialchars($shell_title) ?></h2>
<?php endif; ?>

<?php display_site_messages_modal(); ?>
