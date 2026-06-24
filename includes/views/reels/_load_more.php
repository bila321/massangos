<?php
/**
 * Partial: gatilho de infinite scroll dos Reels.
 *
 * Quando $total_pages <= 1 não há nada a carregar — não renderiza nada.
 * O JS (reels-load-more.js) observa #reels-sentinel com IntersectionObserver
 * e, ao fim de cada batch, mostra o banner de confirmação em vez de
 * continuar a carregar automaticamente (comportamento tipo Facebook).
 *
 * Durante o fetch, em vez do spinner de texto simples, são clonados
 * skeletons reais (a partir de #reels-skeleton-template) dentro do
 * grid, dando feedback visual consistente com o carregamento inicial.
 *
 *   @var int    $total_pages
 *   @var int    $page
 *   @var int    $total
 *   @var string $filter_search
 *   @var string $filter_sale
 *   @var string $filter_sensitive
 *   @var string $filter_duration
 *   @var float|string $filter_price_min
 *   @var float|string $filter_price_max
 *   @var string $filter_quality
 *   @var string $filter_sort
 */
if ($total_pages <= 1) return;

// URL base para a próxima página — preserva todos os filtros activos.
// O JS substitui o parâmetro `page` em cada carregamento.
$filter_params = array_filter([
    'q'         => $filter_search,
    'sale'      => $filter_sale,
    'sensitive' => $filter_sensitive,
    'duration'  => $filter_duration ?? '',
    'price_min' => $filter_price_min !== '' ? $filter_price_min : null,
    'price_max' => $filter_price_max !== '' ? $filter_price_max : null,
    'quality'   => $filter_quality,
    'sort'      => $filter_sort !== 'recent' ? $filter_sort : null,
], fn($v) => $v !== null && $v !== '');

$next_page_url = 'reels.php?' . http_build_query(array_merge($filter_params, ['page' => '__PAGE__']));
?>

<!-- ── Infinite scroll sentinel ── -->
<div id="reels-load-more-wrapper"
    data-current-page="<?= $page ?>"
    data-total-pages="<?= $total_pages ?>"
    data-total="<?= $total ?>"
    data-url-template="<?= htmlspecialchars($next_page_url) ?>">

    <!-- Template do skeleton — o JS clona o conteúdo deste <template>
         (que nunca é renderizado por si só) para inserir no grid real
         durante o fetch. Mantém o HTML do skeleton num único sítio. -->
    <template id="reels-skeleton-template">
        <?php require __DIR__ . '/_skeleton_card.php'; ?>
    </template>

    <!-- Estado: banner de confirmação (aparece ao fim de cada batch) -->
    <div class="reels-continue-banner" id="reels-continue-banner" hidden>
        <div class="continue-inner">
            <p class="continue-msg">
                <i class="fa-solid fa-layer-group"></i>
                Já viste <strong id="reels-seen-count"><?= count($GLOBALS['reels'] ?? []) ?></strong> de <strong><?= $total ?></strong> reels
            </p>
            <div class="continue-actions">
                <button class="btn-continue-yes" id="btn-continue-yes">
                    <i class="fa-solid fa-arrow-down"></i> Continuar a ver
                </button>
                <a href="#reels-top" class="btn-continue-no">
                    <i class="fa-solid fa-arrow-up"></i> Voltar ao topo
                </a>
            </div>
        </div>
    </div>

    <!-- Fim do conteúdo -->
    <div class="reels-end-msg" id="reels-end-msg" hidden>
        <i class="fa-solid fa-check-circle"></i>
        Viste todos os <?= $total ?> reels
    </div>

    <!-- Sentinel observado pelo IntersectionObserver -->
    <div id="reels-sentinel"></div>
</div>
