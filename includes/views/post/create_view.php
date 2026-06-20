<?php

/**
 * View: Criar Publicação
 *
 * Coordenador dos partials por tipo de conteúdo.
 *
 * Variáveis esperadas (injetadas pelo PostController::showCreate):
 *   @var array $user_data   Dados do utilizador autenticado
 *   @var array $user_stats  ['stars', 'balance', 'is_verified_creator']
 *   @var array $rules       Regras de venda calculadas pelo PostService
 */

// Segurança: esta view nunca deve ser acedida diretamente
if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado.');
}
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/create-post.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<?php
$extra_head_js        = [];
$hide_feed_container   = true;
require_once __DIR__ . '/../../../includes/header.php';
?>

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

    </div><!-- /.create-post-body -->
</div><!-- /.create-post-container -->

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/pages/create-post.js"></script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>