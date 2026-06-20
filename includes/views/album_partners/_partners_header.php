<?php
/**
 * @var array      $album
 * @var string     $owner_username
 * @var bool       $is_owner
 * @var bool       $is_partner
 * @var array|null $user_partner_info
 * @var float      $total_percentage
 * @var float      $available_percentage
 */
?>
<!-- ── Cabeçalho: info do álbum + barra de percentagem ── -->
<div class="partners-header">

    <div class="album-info">
        <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>"
            alt="<?= htmlspecialchars($album['album_name']) ?>">
        <div class="album-info-text">
            <h2><?= htmlspecialchars($album['album_name']) ?></h2>
            <p><?= htmlspecialchars($album['album_description'] ?? 'Sem descrição') ?></p>
            <p><strong>Dono:</strong> <?= htmlspecialchars($owner_username) ?></p>
        </div>
    </div>

    <div class="percentage-status">
        <div class="percentage-bar">
            <div class="percentage-fill" style="width:<?= $total_percentage ?>%"></div>
        </div>
        <div class="percentage-text">
            Percentagem total distribuída: <strong><?= number_format($total_percentage, 2) ?>%</strong>
            <?php if ($is_owner): ?>
                | Disponível: <strong><?= number_format($available_percentage, 2) ?>%</strong>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_owner && $available_percentage > 0): ?>
        <button class="add-partner-btn" onclick="openAddPartnerModal()">+ Adicionar Parceiro</button>
    <?php endif; ?>

    <?php if ($is_partner && $user_partner_info['status'] === 'pending'): ?>
        <div class="partnership-invite-box">
            <h4>Convite de Parceria Pendente</h4>
            <p>
                Você foi convidado para ser parceiro deste álbum com
                <strong><?= $user_partner_info['percentage'] ?>%</strong> de participação.
            </p>
            <div class="partnership-invite-actions">
                <button class="btn-action btn-accept"
                    onclick="respondPartnership(<?= (int)$user_partner_info['id'] ?>, 'accept')">
                    Aceitar Convite
                </button>
                <button class="btn-action btn-reject"
                    onclick="respondPartnership(<?= (int)$user_partner_info['id'] ?>, 'reject')">
                    Recusar
                </button>
            </div>
        </div>
    <?php endif; ?>

</div>
