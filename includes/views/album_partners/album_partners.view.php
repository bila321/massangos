<?php
/**
 * View: album_partners.view.php
 *
 * Coordenador dos partials da página de gestão de parceiros de álbum.
 *
 * Variáveis disponíveis (via extract no Controller):
 *   @var array      $album
 *   @var string     $owner_username
 *   @var array      $partners
 *   @var bool       $is_owner
 *   @var bool       $is_partner
 *   @var array|null $user_partner_info
 *   @var float      $total_percentage
 *   @var float      $available_percentage
 *   @var int        $album_id_var
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modal.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/album_partners.css">

<div class="main-layout-container">
    <main class="main-content-area">
        <?php get_and_clear_messages(); ?>

        <div class="manage-partners-container">

            <?php require __DIR__ . '/_partners_header.php'; ?>
            <?php require __DIR__ . '/_partners_list.php'; ?>

        </div>
    </main>
</div>

<?php require __DIR__ . '/_modal_add_partner.php'; ?>
<?php require __DIR__ . '/_modal_edit_partner.php'; ?>

<script>
    window.ALBUM_PARTNERS_ALBUM_ID = <?= (int)$album_id_var ?>;
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= BASE_URL ?>assets/js/pages/album_partners.js"></script>
