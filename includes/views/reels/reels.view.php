<?php

/**
 * View: reels.view.php
 *
 * Decisão de produto (2026-06-21): reels.php deixa de mostrar um grid
 * de descoberta próprio. Abre directamente no premium_lightbox (já
 * usado pelo feed principal), reaproveitando a UX de scroll vertical
 * que já existe e funciona bem, e evitando o custo de rede de
 * carregar um grid inteiro de posters/vídeos antes de mostrar nada.
 *
 * Filtros, pesquisa e listagem por preço (úteis para quem procura um
 * vídeo à venda específico) ficam para uma iteração futura DENTRO do
 * próprio lightbox (sidebar lateral) — não voltam a viver aqui.
 *
 * Variáveis disponíveis (via extract no Controller):
 *   @var array  $reels
 *   @var int    $total
 *   @var int    $total_pages
 *   @var int    $page
 *   @var int    $per_page
 *   @var int    $current_user_id
 *   @var bool   $is_admin
 *   @var array  $logged_in_user_data
 *   @var string $csrf_token
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/premium_lightbox.css">

<div class="reels-page reels-page--direct-open">

    <?php if (empty($reels)): ?>
        <?php require __DIR__ . '/_empty.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/_triggers_only.php'; ?>
    <?php endif; ?>

</div><!-- /.reels-page -->

<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? (int) get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = null;
    window.IS_POST_OWNER = false;
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png') ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool) ($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
</script>

<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/reels-direct-open.js"></script>
