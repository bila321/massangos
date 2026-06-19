<!-- ══ 1. Editar Perfil ══════════════════════════════════════════ -->
<div class="stg-card is-open" data-stg-card>
    <div class="stg-card-head" data-stg-toggle>
        <div class="stg-card-head-left">
            <div class="stg-card-head-icon"><i class="fa-solid fa-user-pen"></i></div>
            <div>
                <h3>Editar Perfil</h3>
                <p>Nome, bio, foto e informações públicas</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-down stg-chevron"></i>
    </div>

    <div class="stg-card-body">
        <form action="<?= BASE_URL ?>actions/settings.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">

            <!-- Informações básicas -->
            <p class="stg-sub">Informações básicas</p>
            <div class="fg-row">
                <div class="fg">
                    <label for="username"><i class="fa-solid fa-at"></i> Usuário</label>
                    <input type="text" name="username" id="username"
                        value="<?= htmlspecialchars($user_data['username']) ?>" required>
                </div>
                <div class="fg">
                    <label for="email"><i class="fa-solid fa-envelope"></i> E-mail</label>
                    <input type="email" name="email" id="email"
                        value="<?= htmlspecialchars($user_data['email']) ?>" required>
                </div>
            </div>
            <div class="fg">
                <label for="bio"><i class="fa-solid fa-pen-fancy"></i> Biografia</label>
                <textarea name="bio" id="bio" rows="3"
                    placeholder="Conta um pouco sobre você..."><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
            </div>

            <div class="stg-divider"></div>

            <!-- Visibilidade pública -->
            <p class="stg-sub">Visibilidade pública</p>
            <div class="fg-row">
                <div class="fg">
                    <label for="location"><i class="fa-solid fa-location-dot"></i> Localização</label>
                    <input type="text" name="location" id="location"
                        value="<?= htmlspecialchars($user_data['location'] ?? '') ?>"
                        maxlength="100" placeholder="Ex: Maputo, Moçambique">
                    <label class="fg-toggle">
                        <input type="checkbox" name="show_location" value="1"
                            <?= !empty($user_data['show_location']) ? 'checked' : '' ?>>
                        Mostrar no perfil
                    </label>
                </div>
                <div class="fg">
                    <label for="website"><i class="fa-solid fa-link"></i> Website</label>
                    <input type="text" name="website" id="website"
                        value="<?= htmlspecialchars($user_data['website'] ?? '') ?>"
                        maxlength="255" placeholder="https://exemplo.com">
                    <label class="fg-toggle">
                        <input type="checkbox" name="show_website" value="1"
                            <?= !empty($user_data['show_website']) ? 'checked' : '' ?>>
                        Mostrar no perfil
                    </label>
                </div>
            </div>
            <div class="fg-row">
                <div class="fg">
                    <label for="profile_birth_date"><i class="fa-solid fa-cake-candles"></i> Aniversário</label>
                    <input type="date" name="profile_birth_date" id="profile_birth_date"
                        value="<?= htmlspecialchars($user_data['birth_date'] ?? '') ?>">
                    <label class="fg-toggle">
                        <input type="checkbox" name="show_birth_date" value="1"
                            <?= !empty($user_data['show_birth_date']) ? 'checked' : '' ?>>
                        Mostrar (só dia e mês)
                    </label>
                </div>
                <div class="fg">
                    <label for="gender"><i class="fa-solid fa-user"></i> Género</label>
                    <select name="gender" id="gender">
                        <option value="">-- Selecionar --</option>
                        <?php
                        $genders = [
                            'male'             => 'Masculino',
                            'female'           => 'Feminino',
                            'other'            => 'Outro',
                            'prefer_not_to_say'=> 'Prefiro não dizer',
                        ];
                        foreach ($genders as $val => $label):
                        ?>
                            <option value="<?= $val ?>"
                                <?= ($user_data['gender'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="fg-toggle">
                        <input type="checkbox" name="show_gender" value="1"
                            <?= !empty($user_data['show_gender']) ? 'checked' : '' ?>>
                        Mostrar no perfil
                    </label>
                </div>
            </div>

            <div class="stg-divider"></div>

            <!-- Foto de perfil -->
            <p class="stg-sub">Foto de perfil</p>

            <div id="cropper-wrapper" class="cropper-wrapper">
                <img id="image-to-crop">
            </div>

            <div class="photo-upload-box">
                <div class="photo-preview">
                    <img id="preview-img"
                        src="<?= UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'default_profile.png') ?>"
                        alt="Foto atual">
                    <div class="photo-preview-badge"><i class="fa-solid fa-check"></i></div>
                </div>
                <div class="photo-info">
                    <strong>Selecionar nova foto</strong>
                    <span>JPG, PNG, GIF · máx. 5 MB · proporção 1:1</span>
                    <input type="file" id="file-input" class="photo-file-input" accept="image/*">
                </div>
            </div>
            <input type="hidden" name="cropped_image" id="cropped_image">

            <div class="btn-save-row">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Guardar alterações
                </button>
            </div>
        </form>
    </div>
</div>
<!-- /Editar Perfil -->
