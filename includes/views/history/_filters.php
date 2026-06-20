<?php /** @var string $filter */ ?>
<!-- ── Filtros ── -->
<nav class="history-filters" aria-label="Filtro de reações">
    <?php
    $tabs = ['all' => 'Tudo', 'posts' => 'Publicações', 'photos' => 'Fotos', 'comments' => 'Comentários'];
    foreach ($tabs as $key => $label):
        $active = $filter === $key;
    ?>
        <a href="?filter=<?= urlencode($key) ?>&page=1"
            class="history-filter <?= $active ? 'history-filter--active' : '' ?>"
            <?= $active ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
