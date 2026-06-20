<?php /** @var string $query */ ?>
<!-- ── Cabeçalho ── -->
<div class="search-header">
    <h2>Resultados da Pesquisa</h2>
    <?php if ($query !== ''): ?>
        <p class="results-count">
            Mostrando resultados para "<strong><?= htmlspecialchars($query) ?></strong>"
        </p>
    <?php endif; ?>
</div>
