<?php
/**
 * Partial: barra de pesquisa e filtros de Reels.
 *
 *   @var array  $reels
 *   @var string $filter_search
 *   @var string $filter_sale
 *   @var string $filter_sensitive
 *   @var float|string $filter_price_min
 *   @var float|string $filter_price_max
 *   @var string $filter_quality
 *   @var string $filter_sort
 *   @var string $active_chip
 */

// Definição dos chips de filtro rápido (mesmo padrão de $filter_defs em saved/_filters.php)
$chip_defs = [
    ''      => ['icon' => 'fa-th',     'label' => 'Todos',      'data_chip' => 'all'],
    'free'  => ['icon' => 'fa-unlock', 'label' => 'Gratuitos',  'data_chip' => 'free'],
    'paid'  => ['icon' => 'fa-lock',   'label' => 'Pagos',      'data_chip' => 'paid'],
    'adult' => ['icon' => 'fa-fire',   'label' => '+18',        'data_chip' => 'adult', 'extra_class' => 'adult'],
];
?>
<div class="reels-filter-bar">

    <form id="filterForm" method="get" action="reels.php">

        <!-- Campos ocultos para chips (sale + sensitive) -->
        <input type="hidden" name="sale" id="input_sale" value="<?= htmlspecialchars($filter_sale) ?>">
        <input type="hidden" name="sensitive" id="input_sensitive" value="<?= htmlspecialchars($filter_sensitive) ?>">

        <!-- ── Linha sempre visível: pesquisa + botão filtros (mobile) ── -->
        <div class="filters-top-row">

            <div class="filter-search">
                <i class="fa fa-search"></i>
                <input type="text" name="q"
                    placeholder="Pesquisar reels…"
                    value="<?= htmlspecialchars($filter_search) ?>">
            </div>

            <!-- Botão toggle — só aparece em mobile via CSS -->
            <button type="button" class="btn-filters-toggle" id="btnFiltersToggle"
                aria-expanded="false" aria-controls="filtersPanel">
                <i class="fa-solid fa-sliders icon-sliders"></i>
                Filtros
                <span class="filters-badge" id="filtersBadge"></span>
            </button>

        </div><!-- /.filters-top-row -->

        <!-- ── Painel colapsável (desktop: sempre visível; mobile: toggle) ── -->
        <div class="filters-panel" id="filtersPanel">
            <div class="filters-panel-inner">

                <!-- Chips -->
                <div class="filter-chips">
                    <?php foreach ($chip_defs as $key => $chip): ?>
                        <span class="chip <?= $chip['extra_class'] ?? '' ?> <?= $active_chip === $key ? 'active' : '' ?>"
                            data-chip="<?= $chip['data_chip'] ?>" onclick="setChip('<?= $key ?>')">
                            <i class="fa <?= $chip['icon'] ?>"></i> <?= $chip['label'] ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Selects + Preço -->
                <div class="filters-row-secondary">

                    <div class="filter-group">
                        <label><i class="fa fa-sort"></i> Ordenar</label>
                        <div class="filter-select-wrapper">
                            <select name="sort">
                                <option value="recent" <?= $filter_sort === 'recent'     ? 'selected' : '' ?>>Recentes</option>
                                <option value="popular" <?= $filter_sort === 'popular'    ? 'selected' : '' ?>>Populares</option>
                                <option value="price_asc" <?= $filter_sort === 'price_asc'  ? 'selected' : '' ?>>Preço ↑</option>
                                <option value="price_desc" <?= $filter_sort === 'price_desc' ? 'selected' : '' ?>>Preço ↓</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label><i class="fa fa-film"></i> Qualidade</label>
                        <div class="filter-select-wrapper">
                            <select name="quality">
                                <option value="">Todas</option>
                                <option value="sd" <?= $filter_quality === 'sd'  ? 'selected' : '' ?>>SD</option>
                                <option value="hd" <?= $filter_quality === 'hd'  ? 'selected' : '' ?>>HD</option>
                                <option value="fhd" <?= $filter_quality === 'fhd' ? 'selected' : '' ?>>Full HD</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label><i class="fa fa-tag"></i> Preço</label>
                        <div class="price-range-inputs">
                            <input type="number" name="price_min" placeholder="Min"
                                value="<?= htmlspecialchars((string)$filter_price_min) ?>" min="0">
                            <span class="price-sep">–</span>
                            <input type="number" name="price_max" placeholder="Max"
                                value="<?= htmlspecialchars((string)$filter_price_max) ?>" min="0">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn-apply">
                            <i class="fa fa-check"></i> Aplicar
                        </button>
                        <a href="reels.php" class="btn-reset" title="Limpar filtros">
                            <i class="fa fa-rotate-left"></i>
                        </a>
                    </div>

                </div><!-- /.filters-row-secondary -->

                <span class="results-count">
                    <?= number_format(count($reels)) ?> reels encontrados
                </span>

            </div><!-- /.filters-panel-inner -->
        </div><!-- /.filters-panel -->

    </form>

</div><!-- /.reels-filter-bar -->
