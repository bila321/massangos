<!-- ══ 4. Utilizadores Bloqueados ═══════════════════════════════ -->
<div class="stg-card" data-stg-card>
    <div class="stg-card-head" data-stg-toggle>
        <div class="stg-card-head-left">
            <div class="stg-card-head-icon"><i class="fa-solid fa-user-slash"></i></div>
            <div>
                <h3>Utilizadores Bloqueados</h3>
                <p>
                    <?= count($blocked_users) ?>
                    bloqueado<?= count($blocked_users) !== 1 ? 's' : '' ?>
                </p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-down stg-chevron"></i>
    </div>

    <div class="stg-card-body">
        <?php if (empty($blocked_users)): ?>
            <div class="stg-empty">
                <i class="fa-solid fa-user-check"></i>
                Não bloqueaste nenhum utilizador ainda.
            </div>
        <?php else: ?>
            <div class="blocked-grid">
                <?php foreach ($blocked_users as $bu): ?>
                    <div class="blocked-item">
                        <div class="blocked-user-info">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($bu['profile_picture'] ?? 'default_profile.png') ?>"
                                alt="<?= htmlspecialchars($bu['username']) ?>">
                            <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$bu['id'] ?>">
                                <?= htmlspecialchars($bu['username']) ?>
                            </a>
                        </div>
                        <form action="<?= BASE_URL ?>actions/block.php" method="POST"
                            onsubmit="return confirm('Deseja desbloquear este utilizador?');">
                            <input type="hidden" name="user_id" value="<?= (int)$bu['id'] ?>">
                            <input type="hidden" name="action" value="unblock">
                            <button type="submit" class="btn-danger-outline">
                                Desbloquear
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- /Bloqueados -->
