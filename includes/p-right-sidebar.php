<aside class="widgets-container">
    <?php
    // Widget 1 — Informações do perfil
    include_once __DIR__ . '/../public/components/profile/widget-info.php';



    // Após incluir $profile_data, $is_owner, $current_user_id, $pdo:
    require_once __DIR__ . '/../public/components/profile/widget-album-premium.php';
    ?>
</aside>