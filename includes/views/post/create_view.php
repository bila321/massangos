<?php
/**
 * View: Criar Publicação
 * includes/views/post/create_view.php
 *
 * Variáveis esperadas (injectadas pelo CreatePostController):
 *   @var array $user_data   Dados do utilizador autenticado
 *   @var array $user_stats  ['stars', 'balance', 'is_verified_creator']
 *   @var array $rules       Regras de venda calculadas pelo PostService
 */
if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado.');
}
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/create-post.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="create-post-container">

    <?php require __DIR__ . '/_post_header.php'; ?>
    <?php require __DIR__ . '/_tabs_nav.php'; ?>

    <div class="create-post-body">
        <?php require __DIR__ . '/_user_info.php'; ?>
        <?php require __DIR__ . '/_progress_bar.php'; ?>

        <?php require __DIR__ . '/tabs/_tab_text.php'; ?>
        <?php require __DIR__ . '/tabs/_tab_photo.php'; ?>
        <?php require __DIR__ . '/tabs/_tab_video.php'; ?>
        <?php require __DIR__ . '/tabs/_tab_album.php'; ?>
    </div>

</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/create-post.js"></script>
