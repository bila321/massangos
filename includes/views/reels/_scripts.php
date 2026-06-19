<?php
/**
 * Partial: scripts da página de Reels.
 *
 *   @var array $logged_in_user_data
 */
?>
<!-- ── SCRIPTS ──────────────────────────────────────────────────────────────── -->

<!-- 1. Variáveis globais PRIMEIRO -->
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? (int)get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = null;
    window.IS_POST_OWNER = false;
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png') ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
</script>

<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/reels.js?v=202606161014"></script>
