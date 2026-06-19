<?php

/**
 * View: view_album.view.php
 *
 * Recebe variáveis via extract() do AlbumViewController::render().
 *
 * Variáveis disponíveis (todas definidas em AlbumViewService::loadAlbum):
 *   @var int    $album_id
 *   @var int    $feed_item_id
 *   @var int    $current_user_id
 *   @var bool   $is_owner
 *   @var bool   $has_feed
 *   @var array  $author
 *   @var array  $content_data
 *   @var array  $feed_item
 *   @var array  $photos
 *   @var array|null $ai_analysis
 *   @var float  $album_explicit_pct
 *   @var string $album_risk_level
 *   @var bool   $should_blur
 *   @var array  $comment_tree
 *   @var array  $like_info
 *   @var mixed  $user_vote
 *   @var int    $comment_count
 *   @var array  $photo_comments_for_album
 *   @var array  $photo_likes_map
 *   @var array  $photo_comments_map
 *   @var array  $photo_saves_map
 *   @var array  $photo_saves_count
 *   @var string $me_pic
 */

declare(strict_types=1);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/comments.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/view_album.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<!-- ══════════════════════ PÁGINA ══════════════════════ -->
<div class="va-page">

    <?php require __DIR__ . '/_breadcrumb.php'; ?>
    <?php require __DIR__ . '/_header.php'; ?>
    <?php require __DIR__ . '/_photo_grid.php'; ?>
    <?php require __DIR__ . '/_actions.php'; ?>
    <?php require __DIR__ . '/_comments_section.php'; ?>

</div><!-- /.va-page -->

<?php if ($is_owner): require __DIR__ . '/_upload_modal.php';
endif; ?>

<?php require __DIR__ . '/_scripts.php'; ?>