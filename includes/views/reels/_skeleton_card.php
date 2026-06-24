<?php
/**
 * Partial: um único card skeleton de Reel.
 *
 * Mantido como ficheiro separado (em vez de inline em _skeleton.php)
 * para que a mesma estrutura HTML possa ser descrita uma única vez.
 * É reaproveitado de duas formas:
 *   1. Aqui, diretamente, no carregamento inicial (via _skeleton.php).
 *   2. Como <template> em _load_more.php, clonado pelo JS
 *      (reels-load-more.js) durante o infinite scroll — sem duplicar
 *      o markup numa string JS separada.
 */
?>
<div class="skel-reel-card">
    <div class="skel-reel-card__media"></div>
    <div class="skel-reel-card__badge"></div>
    <div class="skel-reel-card__badge skel-reel-card__badge--duration"></div>
    <div class="skel-reel-card__play"></div>
    <div class="skel-reel-card__overlay">
        <div class="skel-reel-card__avatar"></div>
        <div class="skel-reel-card__line skel-reel-card__line--name"></div>
    </div>
</div>
