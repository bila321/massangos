<?php
/**
 * View: settings.view.php
 *
 * Coordenador dos partials da página de configurações.
 *
 * Variáveis disponíveis (extract de FeedController + settings.php):
 *   @var array  $user_data
 *   @var int    $current_user_id
 *   @var array  $blocked_users
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/settings.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<div class="stg-wrap">

    <h1 class="stg-page-title">
        <i class="fa-solid fa-gear"></i> Configurações
    </h1>

    <?php require __DIR__ . '/_card_edit_profile.php'; ?>
    <?php require __DIR__ . '/_card_privacy.php'; ?>
    <?php require __DIR__ . '/_card_verification.php'; ?>
    <?php require __DIR__ . '/_card_blocked.php'; ?>

    <a href="<?= BASE_URL ?>logout.php" class="stg-logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair da conta
    </a>

</div><!-- /stg-wrap -->

<?php require __DIR__ . '/_modal_verification.php'; ?>

<script src="<?= BASE_URL ?>assets/js/pages/settings.js"></script>
