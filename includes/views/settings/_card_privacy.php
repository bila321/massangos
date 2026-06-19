<!-- ══ 2. Privacidade ═══════════════════════════════════════════ -->
<div class="stg-card" data-stg-card>
    <div class="stg-card-head" data-stg-toggle>
        <div class="stg-card-head-left">
            <div class="stg-card-head-icon"><i class="fa-solid fa-lock"></i></div>
            <div>
                <h3>Privacidade</h3>
                <p>Controle quem vê o seu conteúdo</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-down stg-chevron"></i>
    </div>

    <div class="stg-card-body">
        <form action="<?= BASE_URL ?>actions/settings.php" method="POST">
            <input type="hidden" name="action" value="update_privacy">

            <label class="privacy-opt">
                <input type="radio" name="profile_privacy" value="public"
                    <?= ($user_data['profile_privacy'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                <div class="privacy-opt-info">
                    <strong><i class="fa-solid fa-earth-africa"></i> Perfil público</strong>
                    <span>Qualquer pessoa na plataforma pode ver as suas publicações e fotos.</span>
                </div>
            </label>

            <label class="privacy-opt">
                <input type="radio" name="profile_privacy" value="followers"
                    <?= ($user_data['profile_privacy'] ?? 'public') === 'followers' ? 'checked' : '' ?>>
                <div class="privacy-opt-info">
                    <strong><i class="fa-solid fa-user-lock"></i> Privado</strong>
                    <span>Apenas os seus seguidores podem ver o seu conteúdo completo.</span>
                </div>
            </label>

            <div class="btn-save-row">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar privacidade
                </button>
            </div>
        </form>
    </div>
</div>
<!-- /Privacidade -->
