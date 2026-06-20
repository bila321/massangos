<?php
/**
 * @var int    $total_pages
 * @var int    $page
 * @var string $filter
 */
if ($total_pages <= 1) return;

$build_url = static fn(int $p): string => '?filter=' . urlencode($filter) . '&page=' . $p;
$range     = range(max(1, $page - 2), min($total_pages, $page + 2));
?>
<!-- ── Paginação ── -->
<nav class="history-pagination" aria-label="Paginação">

    <a href="<?= $page > 1 ? htmlspecialchars($build_url($page - 1)) : '#' ?>"
        class="page-btn <?= $page <= 1 ? 'page-btn--disabled' : '' ?>"
        aria-label="Anterior">
        <i class="fa-solid fa-chevron-left"></i>
    </a>

    <?php if (!in_array(1, $range, true)):
        echo '<a href="' . htmlspecialchars($build_url(1)) . '" class="page-btn">1</a>';
        if ($range[0] > 2) echo '<span class="page-btn page-btn--disabled">…</span>';
    endif; ?>

    <?php foreach ($range as $p): ?>
        <a href="<?= htmlspecialchars($build_url($p)) ?>"
            class="page-btn <?= $p === $page ? 'page-btn--active' : '' ?>"
            <?= $p === $page ? 'aria-current="page"' : '' ?>>
            <?= $p ?>
        </a>
    <?php endforeach; ?>

    <?php if (!in_array($total_pages, $range, true)):
        if (end($range) < $total_pages - 1) echo '<span class="page-btn page-btn--disabled">…</span>';
        echo '<a href="' . htmlspecialchars($build_url($total_pages)) . '" class="page-btn">' . $total_pages . '</a>';
    endif; ?>

    <a href="<?= $page < $total_pages ? htmlspecialchars($build_url($page + 1)) : '#' ?>"
        class="page-btn <?= $page >= $total_pages ? 'page-btn--disabled' : '' ?>"
        aria-label="Próxima">
        <i class="fa-solid fa-chevron-right"></i>
    </a>

</nav>
