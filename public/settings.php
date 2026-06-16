<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\FeedController;
use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Notification;

$data = (new FeedController($pdo))->load();
extract($data);

if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar as configurações.", "danger");
    redirect(BASE_URL . 'login.php');
}

$blocked_users   = User::getBlockedUsers($pdo, $current_user_id);


require_once __DIR__ . '/../includes/header.php';
?>



<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/settings.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<div class="stg-wrap">

    <h1 class="stg-page-title">
        <i class="fa-solid fa-gear"></i> Configurações
    </h1>

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

                <!-- Informações do perfil -->
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
                            <option value="male" <?= ($user_data['gender'] ?? '') === 'male'              ? 'selected' : '' ?>>Masculino</option>
                            <option value="female" <?= ($user_data['gender'] ?? '') === 'female'            ? 'selected' : '' ?>>Feminino</option>
                            <option value="other" <?= ($user_data['gender'] ?? '') === 'other'             ? 'selected' : '' ?>>Outro</option>
                            <option value="prefer_not_to_say" <?= ($user_data['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefiro não dizer</option>
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

    <!-- ══ 3. Verificação de Criador ════════════════════════════════ -->
    <div class="stg-card" data-stg-card>
        <div class="stg-card-head" data-stg-toggle>
            <div class="stg-card-head-left">
                <div class="stg-card-head-icon"><i class="fa-solid fa-id-card"></i></div>
                <div>
                    <h3>Verificação de Criador</h3>
                    <p>Venda e monetize o seu conteúdo</p>
                </div>
            </div>
            <i class="fa-solid fa-chevron-down stg-chevron"></i>
        </div>

        <div class="stg-card-body">
            <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                <div class="verify-status ok">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Perfil verificado</strong><br>
                        <span style="font-size:.8rem;">Já pode vender e cobrar acesso ao seu conteúdo.</span>
                    </div>
                </div>
            <?php elseif (($user_data['verification_status'] ?? 'none') === 'pending'): ?>
                <div class="verify-status wait">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                        <strong>Verificação em análise</strong><br>
                        <span style="font-size:.8rem;">Os seus documentos estão a ser revisados pela nossa equipa.</span>
                    </div>
                </div>
            <?php else: ?>
                <p style="font-size:.85rem;color:var(--text-secondary);margin:0 0 12px;">
                    Para vender conteúdo ou cobrar acesso, precisa verificar a sua identidade.
                </p>
                <button class="btn-primary"
                    onclick="document.getElementById('verificationModal').style.display='flex'">
                    <i class="fa-solid fa-shield-halved"></i> Iniciar verificação
                </button>
                <?php if (($user_data['verification_status'] ?? 'none') === 'rejected'): ?>
                    <p style="color:var(--danger-color);margin-top:10px;font-size:.78rem;">
                        <i class="fa-solid fa-circle-xmark"></i> Verificação anterior rejeitada. Tente novamente.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- /Verificação -->

    <!-- ══ 4. Utilizadores Bloqueados ═══════════════════════════════ -->
    <div class="stg-card" data-stg-card>
        <div class="stg-card-head" data-stg-toggle>
            <div class="stg-card-head-left">
                <div class="stg-card-head-icon"><i class="fa-solid fa-user-slash"></i></div>
                <div>
                    <h3>Utilizadores Bloqueados</h3>
                    <p><?= count($blocked_users) ?> bloqueado<?= count($blocked_users) !== 1 ? 's' : '' ?></p>
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
                    <?php foreach ($blocked_users as $user): ?>
                        <div class="blocked-item">
                            <div class="blocked-user-info">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($user['profile_picture'] ?? 'default_profile.png') ?>"
                                    alt="<?= htmlspecialchars($user['username']) ?>">
                                <a href="<?= BASE_URL ?>profile.php?id=<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['username']) ?>
                                </a>
                            </div>
                            <form action="<?= BASE_URL ?>actions/block.php" method="POST"
                                onsubmit="return confirm('Deseja desbloquear este utilizador?');">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
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

    <!-- Logout -->
    <a href="<?= BASE_URL ?>logout.php" class="stg-logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair da conta
    </a>

</div><!-- /stg-wrap -->


<!-- ══ Modal de Verificação ═══════════════════════════════════════ -->
<div id="verificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h2><i class="fa-solid fa-shield-halved"></i> Verificação de Identidade</h2>
            <button class="modal-close" type="button"
                onclick="document.getElementById('verificationModal').style.display='none'"
                aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">

            <!-- Barra de progresso dos passos -->
            <div class="v-steps-bar">
                <span id="bar-1" class="done"></span>
                <span id="bar-2"></span>
                <span id="bar-3"></span>
            </div>

            <div id="verificationSteps">

                <!-- Passo 1: Dados pessoais -->
                <div class="v-step is-active" id="v-step-1">
                    <h4>Passo 1 — Dados pessoais</h4>
                    <div class="fg">
                        <label>Nome e sobrenome</label>
                        <input type="text" id="v_full_name" placeholder="Como no documento" required>
                    </div>
                    <div class="fg-row">
                        <div class="fg">
                            <label>Apelido</label>
                            <input type="text" id="v_nickname" required>
                        </div>
                        <div class="fg">
                            <label>Data de nascimento</label>
                            <input type="date" id="v_birth_date" required>
                        </div>
                    </div>
                    <div class="fg-row">
                        <div class="fg">
                            <label>Província</label>
                            <select id="v_province">
                                <option value="">Selecione...</option>
                                <option>Maputo</option>
                                <option>Gaza</option>
                                <option>Inhambane</option>
                                <option>Sofala</option>
                                <option>Manica</option>
                                <option>Tete</option>
                                <option>Zambézia</option>
                                <option>Nampula</option>
                                <option>Niassa</option>
                                <option>Cabo Delgado</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label>Contacto (WhatsApp/Telef.)</label>
                            <input type="text" id="v_contact" placeholder="+258...">
                        </div>
                    </div>
                    <div class="btn-row">
                        <button class="btn-primary" onclick="nextStep(2)">
                            Próximo <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Passo 2: Documentos -->
                <div class="v-step" id="v-step-2">
                    <h4>Passo 2 — Fotos do documento (BI ou Passaporte)</h4>
                    <p>Use a câmera para capturar a frente e o verso.</p>
                    <div class="capture-grid">
                        <div class="capture-box">
                            <label>Frente do documento</label>
                            <div id="preview_front" class="media-preview">
                                <i class="fa-solid fa-id-card fa-2x" style="color:#444;"></i>
                            </div>
                            <button class="btn-secondary" type="button" onclick="startCapture('front')">
                                <i class="fa-solid fa-camera"></i> Capturar frente
                            </button>
                        </div>
                        <div class="capture-box">
                            <label>Verso do documento</label>
                            <div id="preview_back" class="media-preview">
                                <i class="fa-solid fa-id-card fa-2x" style="color:#444;"></i>
                            </div>
                            <button class="btn-secondary" type="button" onclick="startCapture('back')">
                                <i class="fa-solid fa-camera"></i> Capturar verso
                            </button>
                        </div>
                    </div>
                    <div class="btn-row">
                        <button class="btn-secondary" type="button" onclick="prevStep(1)">
                            <i class="fa-solid fa-arrow-left"></i> Voltar
                        </button>
                        <button class="btn-primary" type="button" onclick="nextStep(3)">
                            Próximo <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Passo 3: Vídeo -->
                <div class="v-step" id="v-step-3">
                    <h4>Passo 3 — Vídeo de segurança (10 s)</h4>
                    <p>Olhe para a esquerda/direita 2×, cima/baixo 2× e segure o documento ao lado do rosto.</p>

                    <div class="video-box">
                        <video id="video_stream" autoplay muted playsinline webkit-playsinline></video>
                        <video id="video_playback" controls playsinline webkit-playsinline style="display:none;"></video>
                        <div id="recording_indicator" class="rec-badge">
                            <i class="fa-solid fa-circle"></i> REC <span id="timer">10s</span>
                        </div>
                    </div>

                    <div style="text-align:center;">
                        <button id="btn_start_video" class="btn-primary" type="button"
                            onclick="startVideoRecording()">
                            <i class="fa-solid fa-video"></i> Iniciar gravação
                        </button>
                    </div>

                    <div class="btn-row" style="margin-top:14px;">
                        <button class="btn-secondary" type="button" onclick="prevStep(2)">
                            <i class="fa-solid fa-arrow-left"></i> Voltar
                        </button>
                        <button id="btn_submit_verification" class="btn-success" type="button"
                            onclick="submitVerification()" style="display:none;">
                            <i class="fa-solid fa-check"></i> Enviar para análise
                        </button>
                    </div>
                </div>

            </div><!-- /verificationSteps -->

            <!-- Camera overlay -->
            <div id="cameraOverlay">
                <video id="camera_stream" autoplay playsinline webkit-playsinline></video>
                <canvas id="camera_canvas" style="display:none;"></canvas>
                <div class="btn-row" style="justify-content:center;">
                    <button class="btn-primary" type="button" onclick="takeSnapshot()">
                        <i class="fa-solid fa-camera"></i> Tirar foto
                    </button>
                    <button class="btn-secondary" type="button" onclick="closeCamera()">Cancelar</button>
                </div>
            </div>

        </div><!-- /modal-body -->
    </div>
</div>
<!-- /Modal de Verificação -->


<script src="<?= BASE_URL ?>assets/js/pages/settings.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>