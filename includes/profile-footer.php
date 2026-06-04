<?php if (is_logged_in() && !isset($hide_verification_modal)) {
    require_once __DIR__ . '/verificationmodal.php';
} ?>
<?php

/**
 * includes/footer.php
 * Right Sidebar + Scripts + fechamento do layout
 * Depende de: header.php (garante $pdo, $user_data via $GLOBALS)
 */

// ── Dados do footer ───────────────────────────────────────────────────
$current_user_id = is_logged_in() ? get_current_user_id() : 0;
$is_admin        = isset($_SESSION['admin_id']);

// $user_data pode não estar no scope local do footer — puxar dos globals
$user_data = $GLOBALS['_app_user_data'] ?? [];

if (!isset($suggested_users) && is_logged_in()) {
    $suggested_users = \Massango\Models\User::getSuggestedUsers($pdo, $current_user_id, 3);
}

if (!isset($recent_albums)) {
    $recent_albums = \Massango\Models\Album::getRecentAlbums($pdo, 3);
}

// PaymentService — autoload já o registou, só instanciar
$paymentService = new \Massango\Services\PaymentService($pdo);
?>

</div><!-- /.content-wrapper -->
</main>

<?php include_once __DIR__ . '/p-right-sidebar.php'; ?>
</div><!-- /.app-container -->

<!-- ── Scripts ────────────────────────────────────────────────────────── -->

<!-- Dropdown global (mantido aqui — não tem ficheiro dedicado) -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Dropdown genérico
        document.querySelectorAll('.dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                btn.nextElementSibling?.classList.toggle('show');
            });
        });
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
        });
    });

    // Wrapper de like — lógica real em modern-interactions.js
    function handleLike(type, id, btn) {
        console.warn('handleLike não interceptado pelo modern-interactions.js', {
            type,
            id
        });
    }
</script>

<!-- Sidebar toggle — ficheiro dedicado substitui o bloco inline anterior -->
<script src="<?= BASE_URL ?>assets/js/core/sidebar-toggle.js" defer></script>

<!-- Scripts do projeto -->

<script src="<?= BASE_URL ?>assets/js/protection/adult-content.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/components/modern-interactions.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/protection/click-handler.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/components/edit-modals.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js" defer></script>

</body>

</html>