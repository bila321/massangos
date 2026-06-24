<?php

/**
 * Partial: _card-skeleton.php
 * Inclui antes de .posts-list para reservar espaço enquanto o feed carrega.
 * O CSS em cards.css esconde-os automaticamente quando .posts-list não está vazio.
 *
 * Uso em index_view.php, logo antes de <div class="posts-list">:
 *   <?php include __DIR__ . '/_card-skeleton.php'; ?>
 *
 * Número de skeletons: 3 (equivale a ~1 ecrã de conteúdo)
 */
?>
<div class="feed-skeletons" id="feedSkeletons" aria-hidden="true">

    <?php for ($s = 0; $s < 3; $s++): ?>
        <div class="skel-card">

            <!-- Header -->
            <div class="skel-card__header">
                <div class="skel-block skel-card__avatar"></div>
                <div class="skel-card__meta">
                    <div class="skel-block skel-card__name"></div>
                    <div class="skel-block skel-card__date"></div>
                </div>
            </div>

            <!-- Caption lines -->
            <div class="skel-card__text">
                <div class="skel-block skel-card__line" style="width:85%;"></div>
                <div class="skel-block skel-card__line" style="width:60%;"></div>
            </div>

            <!-- Media area — alterna entre proporções para parecer natural -->
            <div class="skel-block skel-card__media <?= $s === 1 ? 'skel-card__media--video' : '' ?>"></div>

            <!-- Footer / actions -->
            <div class="skel-card__footer">
                <div class="skel-block skel-card__btn" style="width:72px;"></div>
                <div class="skel-block skel-card__btn" style="width:56px;"></div>
                <div class="skel-block skel-card__btn" style="width:56px;"></div>
                <div class="skel-block skel-card__btn" style="width:72px;margin-left:auto;"></div>
            </div>

        </div>
    <?php endfor; ?>

</div>

<script>
    /* ── Skeleton hide logic ────────────────────────────────────────────
   Estratégia:
   1. O feed PHP é renderizado sincronamente — quando este script corre
      (inline, sem defer) o .posts-list já está no DOM com os cards.
   2. Usamos requestAnimationFrame para esperar o 1.º paint do skeleton
      antes de o esconder — garante que o utilizador vê o shimmer
      pelo menos 1 frame, evitando flash branco.
   3. Se o feed vier vazio (sem posts), escondemos na mesma para não
      mostrar skeletons infinitos.
   4. Safety net de 3s para casos extremos (JS lento, hydration async).
──────────────────────────────────────────────────────────────────── */
    (function() {
        var skeletons = document.getElementById('feedSkeletons');
        if (!skeletons) return;

        function hide() {
            skeletons.style.transition = 'opacity 0.2s';
            skeletons.style.opacity = '0';
            setTimeout(function() {
                skeletons.classList.add('is-hidden');
            }, 200);
        }

        function check() {
            var feed = document.querySelector('.posts-list');
            /* trim() filtra whitespace-only — o PHP pode gerar nós de texto */
            if (!feed) {
                hide();
                return;
            }
            var hasRealContent = Array.from(feed.children).some(function(el) {
                return el.nodeType === 1; /* só elementos, não text nodes */
            });
            if (hasRealContent) {
                hide();
                return;
            }
            /* Feed existe mas está vazio (sem posts) — esconde na mesma */
            if (feed.textContent.trim().length > 0) {
                hide();
            }
        }

        /* Aguarda 1 frame para o skeleton ter sido pintado antes de esconder */
        requestAnimationFrame(function() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', check);
            } else {
                check();
            }
        });

        /* Safety net */
        setTimeout(hide, 3000);
    })();
</script>