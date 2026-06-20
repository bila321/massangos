<?php
/**
 * @var array $partners
 * @var bool  $is_owner
 */
?>
<!-- ── Lista de parceiros ── -->
<div class="partners-list">
    <h3>Lista de Parceiros</h3>

    <?php if (empty($partners)): ?>
        <div class="empty-state">Nenhum parceiro adicionado ainda.</div>
    <?php else: ?>
        <?php foreach ($partners as $partner): ?>
            <div class="partner-item">
                <div class="partner-info">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($partner['profile_picture'] ?? 'default_profile.png') ?>"
                        alt="<?= htmlspecialchars($partner['username']) ?>" class="partner-avatar">
                    <div class="partner-details">
                        <p class="partner-name">
                            @<?= htmlspecialchars($partner['username']) ?>
                            <span class="partner-status status-<?= htmlspecialchars($partner['status']) ?>">
                                <?= ucfirst($partner['status']) ?>
                            </span>
                        </p>
                        <p class="partner-percentage">
                            Participação: <?= number_format($partner['percentage'], 2) ?>%
                        </p>
                    </div>
                </div>

                <?php if ($is_owner): ?>
                    <div class="partner-actions">
                        <button class="btn-action btn-edit"
                            onclick="openEditPartnerModal(<?= (int)$partner['id'] ?>, <?= (float)$partner['percentage'] ?>)">
                            Editar
                        </button>
                        <button class="btn-action btn-delete"
                            onclick="removePartner(<?= (int)$partner['id'] ?>)">
                            Remover
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
