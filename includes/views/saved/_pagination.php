<?php
/**
 * @var int    $total_pages
 * @var int    $page
 * @var string $filter
 */
if ($total_pages <= 1) return;
?>
<!-- ── Paginação ── -->
<div class="saved-pagination">
    <?php if ($page > 1): ?>
        <a href="?type=<?= $filter ?>&page=<?= $page - 1 ?>">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <a href="?type=<?= $filter ?>&page=<?= $i ?>"
            class="<?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?type=<?= $filter ?>&page=<?= $page + 1 ?>">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    <?php endif; ?>
</div>
