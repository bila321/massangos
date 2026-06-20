<?php
/**
 * View: history.view.php
 *
 * Coordenador dos partials da página de histórico de reações.
 *
 * Variáveis disponíveis (via extract no Controller):
 *   @var array    $reactions
 *   @var int      $total
 *   @var int      $total_pages
 *   @var bool     $db_error
 *   @var string|null $db_error_detail
 *   @var string   $filter   (vem de $_GET, definido no Controller antes do extract)
 *   @var int      $page
 */

use Massango\Services\HistoryService;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/history.css">

<div class="history-page">

    <?php require __DIR__ . '/_header.php'; ?>
    <?php require __DIR__ . '/_db_error.php'; ?>
    <?php require __DIR__ . '/_filters.php'; ?>

    <?php if (empty($reactions)): ?>
        <?php require __DIR__ . '/_empty.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/_reaction_list.php'; ?>
        <?php require __DIR__ . '/_pagination.php'; ?>
    <?php endif; ?>

</div><!-- /.history-page -->
