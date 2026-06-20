<?php

/**
 * View: search.view.php
 *
 * Coordenador dos partials da página de pesquisa.
 *
 * Variáveis disponíveis (definidas no SearchController):
 *   @var string $query
 *   @var string $type
 *   @var string $price_filter
 *   @var array  $user_results
 *   @var array  $results
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/search.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="search-page-container">

    <?php require __DIR__ . '/_header.php'; ?>
    <?php require __DIR__ . '/_filter_bar.php'; ?>

    <div class="results-list">
        <?php require __DIR__ . '/_user_results.php'; ?>
        <?php require __DIR__ . '/_content_results.php'; ?>

        <?php if (empty($user_results) && empty($results)): ?>
            <?php require __DIR__ . '/_no_results.php'; ?>
        <?php endif; ?>
    </div>

</div><!-- /.search-page-container -->