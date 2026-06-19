<?php
/**
 * Partial: grid de Reels.
 *
 * Requerido apenas quando $reels não está vazio — o caso vazio é tratado
 * pelo orquestrador (reels.view.php), tal como em saved.view.php.
 *
 *   @var array $reels
 */
?>
<!-- ── GRID DE REELS ───────────────────────────────────────────────── -->
<div class="reels-grid">
    <?php foreach ($reels as $reel): ?>
        <?php require __DIR__ . '/_card.php'; ?>
    <?php endforeach; ?>
</div><!-- /.reels-grid -->
