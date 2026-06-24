<?php
/**
 * View: profile.view.php
 *
 * Coordenador dos partials da página de perfil.
 * Não contém lógica de negócio — apenas inclui os blocos HTML.
 *
 * Variáveis disponíveis (desempacotadas em profile.php):
 *   @var int    $profile_user_id
 *   @var array  $profile_data
 *   @var int    $current_user_id
 *   @var array  $logged_in_user_data
 *   @var bool   $is_admin
 *   @var bool   $is_owner
 *   @var bool   $is_following
 *   @var bool   $has_pending_request
 *   @var bool   $can_view_content
 *   @var int    $followers_count
 *   @var int    $following_count
 *   @var int    $total_visits
 *   @var float  $star_rating
 *   @var array  $enriched_feed
 *   @var string $account_type
 *   @var string $follow_label
 *   @var string $follow_class
 *   @var string $follow_icon
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/profile_layout.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/cards.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/repost-header.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="main-layout-container profile-page-container">
    <div class="main-content-area">
        <section class="feed-section">
            <div class="posts-list-scrollable">

                <?php require __DIR__ . '/_profile_header.php'; ?>
                <?php require __DIR__ . '/_content_tabs.php'; ?>
                <?php require __DIR__ . '/_feed_grid.php'; ?>

            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/_lightbox.php'; ?>

<?php if ($is_owner): ?>
    <?php require_once __DIR__ . '/../../../includes/publication-modals.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/_verification_modals.php'; ?>
<?php require __DIR__ . '/_scripts.php'; ?>
