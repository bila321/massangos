<?php

/**
 * View: reels.view.php
 *
 * Coordenador dos partials da página de reels. Toda a lógica de negócio
 * já foi resolvida pelo ReelsController::load() — esta View e os seus
 * partials apenas leem dados já prontos (has_access, should_blur, *_url, etc.).
 *
 * Variáveis disponíveis (via extract no Controller):
 *   @var array  $reels
 *   @var int    $total
 *   @var int    $total_pages
 *   @var int    $page
 *   @var int    $per_page
 *   @var int    $current_user_id
 *   @var bool   $is_admin
 *   @var array  $logged_in_user_data
 *   @var string $filter_search
 *   @var string $filter_sale
 *   @var string $filter_sensitive
 *   @var string $filter_duration
 *   @var float|string $filter_price_min
 *   @var float|string $filter_price_max
 *   @var string $filter_quality
 *   @var string $filter_sort
 *   @var string $active_chip
 *   @var string $csrf_token
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/reels.css">

<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="reels-page">
    <?php require __DIR__ . '/_header.php'; ?>
    <?php require __DIR__ . '/_filter_bar.php'; ?>

    <?php if (empty($reels)): ?>
        <?php require __DIR__ . '/_empty.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/_grid.php'; ?>
        <?php require __DIR__ . '/_load_more.php'; ?>
    <?php endif; ?>
</div><!-- /.reels-page -->

<?php require __DIR__ . '/_scripts.php'; ?>