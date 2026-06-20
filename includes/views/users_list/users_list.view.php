<?php
/**
 * View: users_list.view.php
 *
 * Variáveis disponíveis (definidas no UsersListController):
 *   @var array $users
 *   @var bool  $is_ajax
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/users-list.css">

<div class="users-list-container">

    <div class="modal-header-custom">
        <h2>Utilizadores em Destaque</h2>
        <p>Os perfis com mais estrelas na plataforma.</p>
    </div>

    <div class="users-grid">
        <?php if (empty($users)): ?>
            <p class="empty-msg">Nenhum utilizador encontrado.</p>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php require __DIR__ . '/_user_card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
