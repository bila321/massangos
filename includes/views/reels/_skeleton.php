<?php

/**
 * Partial: _skeleton.php
 * Sobrepõe-se ao .reels-grid real (position:absolute, mesmo espaço)
 * enquanto as imagens/thumbnails dos primeiros cards ainda não
 * terminaram de carregar.
 *
 * Diferente do _card-skeleton.php do feed: ali o HTML do feed real
 * só existe depois de um certo ponto, então "skeleton no DOM = grid
 * vazio" já era suficiente para decidir quando esconder. Aqui o grid
 * real já vem completo no MESMO request — não há "espera de dados",
 * só espera de RENDERIZAÇÃO VISUAL (imagens/vídeos a carregar). Por
 * isso o hide() é decidido por eventos de load das imagens, não pela
 * presença de elementos no DOM.
 *
 * Uso em reels.view.php, logo antes do bloco que decide grid/empty —
 * o wrapper pai precisa de position:relative (ver reels-skeleton.css).
 *
 *   @var int $count  Número de skeletons a gerar (default: 12, mesmo
 *                     valor de $per_page no ReelsController)
 */

$count = $count ?? 12;
?>
<div class="skel-reels-grid skel-reels-grid--overlay" id="reelsSkeleton" aria-hidden="true">
    <?php for ($s = 0; $s < $count; $s++): ?>
        <?php require __DIR__ . '/_skeleton_card.php'; ?>
    <?php endfor; ?>
</div>

<script>
    /* ── Skeleton hide logic ──────────────────────────────────────────────
   O skeleton fica visível por DEFEITO (via CSS, position:absolute sobre
   o grid real) até que:
     a) não exista .reels-grid (empty state foi renderizado), OU
     b) as imagens/posters dos cards reais já visíveis no viewport
        tenham terminado de carregar (ou falhado — não bloquear para
        sempre por uma imagem com erro 404).
   Safety net de 3s cobre qualquer caso residual (rede muito lenta,
   imagem que nunca dispara load/error por algum motivo).
──────────────────────────────────────────────────────────────────── */
    (function() {
        var skeleton = document.getElementById('reelsSkeleton');
        if (!skeleton) return;

        var hidden = false;

        function hide() {
            if (hidden) return;
            hidden = true;
            skeleton.style.transition = 'opacity 0.2s';
            skeleton.style.opacity = '0';
            setTimeout(function() {
                skeleton.style.display = 'none';
            }, 200);
        }

        var grid = document.querySelector('.reels-grid');
        var empty = document.querySelector('.reels-empty');

        /* Empty state ou nenhum dos dois -- não há nada a esperar */
        if (!grid || empty) {
            hide();
            return;
        }

        /* Imagens relevantes: avatares + posters dos primeiros cards
           visíveis (não espera por todos os 12+, só pelo que aparece
           no viewport inicial, via slice). */
        var images = Array.from(grid.querySelectorAll('img, video[poster]')).slice(0, 8);

        if (images.length === 0) {
            /* Nenhuma imagem a monitorizar -- esconde de imediato */
            hide();
            return;
        }

        var pending = images.length;

        function onOneLoaded() {
            pending--;
            if (pending <= 0) hide();
        }

        images.forEach(function(el) {
            var isImg = el.tagName === 'IMG';
            var alreadyDone = isImg ? el.complete : false;

            if (alreadyDone) {
                onOneLoaded();
            } else {
                el.addEventListener('load', onOneLoaded, { once: true });
                el.addEventListener('error', onOneLoaded, { once: true }); /* não bloquear por 404 */
            }
        });

        /* Safety net -- nunca deixar o skeleton preso indefinidamente */
        setTimeout(hide, 3000);
    })();
</script>
