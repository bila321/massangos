<?php

/**
 * @var int   $profile_user_id
 * @var array $logged_in_user_data
 */
?>
<!-- ── 1. Variáveis globais para o JS ── -->
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = <?= json_encode((int)$profile_user_id) ?>;
    window.IS_POST_OWNER = (
        window.CURRENT_USER_ID !== null &&
        window.POST_OWNER_ID !== null &&
        window.CURRENT_USER_ID == window.POST_OWNER_ID
    );
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars(
                                                $_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png'
                                            ) ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
    window.CSRF_TOKEN = "<?= htmlspecialchars($csrf_token ?? '') ?>";
</script>

<!-- ── 2. Dependências ── -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>

<!-- ── 3. Core ── -->
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>

<!-- ── 4. Componentes ── -->
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>

<!-- ── 5. Página ── -->
<script src="<?= BASE_URL ?>assets/js/pages/profile.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/save.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>