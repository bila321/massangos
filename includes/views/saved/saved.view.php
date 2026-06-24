<?php
/**
 * View: saved.view.php
 *
 * Coordenador dos partials da página de guardados.
 *
 * Variáveis disponíveis (via extract no Controller):
 * @var array $items
 * @var int $total
 * @var int $total_pages
 * @var string $filter
 * @var int $page
 * @var array $ai_map
 * @var bool $is_admin
 * @var string $csrf_token
 */
use Massango\Services\SavedService;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/repost-header.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/saved.css">

<div class="saved-page">
    <?php require __DIR__ . '/_header.php'; ?>
    <?php require __DIR__ . '/_filters.php'; ?>

    <?php if (empty($items)): ?>
        <?php require __DIR__ . '/_empty.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/_grid.php'; ?>
        <?php require __DIR__ . '/_pagination.php'; ?>
    <?php endif; ?>
</div>



<!-- ✅ Container HTML do Premium Lightbox (obrigatório) -->
<?php require __DIR__ . '/../home/_feed-lightbox.php'; ?>

<!-- Scripts essenciais -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/home.js"></script>
