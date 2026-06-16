<?php
// public/settings.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
header('Permissions-Policy: camera=(self), microphone=(self)');

require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar as configurações.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();
$user_data       = User::getUserById($pdo, $current_user_id);
$blocked_users   = User::getBlockedUsers($pdo, $current_user_id);

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}
?>


<style>
    /* ═══════════════════════════════════════════════════════
   SETTINGS — Design compacto v2
   Paleta: usa vars do tema global; acentos via --primary
═══════════════════════════════════════════════════════ */

    /* Layout */
    .stg-wrap {
        min-height: 110vh;
        max-width: 950px;
        margin: 28px auto;
        padding: 0 16px 60px;
        width: 100%;
    }

    /* Título de página */
    .stg-page-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: var(--font-display, 'Sora', sans-serif);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 24px;
        letter-spacing: -.02em;
    }

    .stg-page-title i {
        color: var(--primary-color);
        font-size: 1rem;
    }

    /* Card base das secções */
    .stg-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light, rgba(0, 0, 0, .07));
        border-radius: 14px;
        margin-bottom: 12px;
        overflow: hidden;
        transition: box-shadow .2s;
    }

    .stg-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
    }

    /* Cabeçalho de cada card (clicável para colapsar) */
    .stg-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        cursor: pointer;
        user-select: none;
        gap: 10px;
    }

    .stg-card-head-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stg-card-head-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: color-mix(in srgb, var(--primary-color, #07c95b) 14%, transparent);
        color: var(--primary-color, #07c95b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .8rem;
        flex-shrink: 0;
    }

    .stg-card-head h3 {
        margin: 0;
        font-size: .9rem;
        font-weight: 600;
        color: var(--text-main);
    }

    .stg-card-head p {
        margin: 2px 0 0;
        font-size: .75rem;
        color: var(--text-secondary, #888);
    }

    .stg-chevron {
        color: var(--text-secondary, #aaa);
        font-size: .75rem;
        transition: transform .25s;
        flex-shrink: 0;
    }

    .stg-card.is-open .stg-chevron {
        transform: rotate(180deg);
    }

    /* Corpo colapsável */
    .stg-card-body {
        display: none;
        padding: 0 18px 18px;
        border-top: 1px solid var(--border-light, rgba(0, 0, 0, .06));
    }

    .stg-card.is-open .stg-card-body {
        display: block;
    }

    /* Divisor interno */
    .stg-divider {
        height: 1px;
        background: var(--border-light, rgba(0, 0, 0, .06));
        margin: 14px 0;
    }

    /* Sub-título dentro do body */
    .stg-sub {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-secondary, #aaa);
        margin: 14px 0 10px;
    }

    /* ── Grupos de formulário ── */
    .fg {
        margin-bottom: 14px;
    }

    .fg:last-child {
        margin-bottom: 0;
    }

    .fg label {
        display: block;
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-secondary, #888);
        margin-bottom: 5px;
        letter-spacing: .01em;
    }

    .fg input[type="text"],
    .fg input[type="email"],
    .fg input[type="date"],
    .fg textarea,
    .fg select {
        width: 100%;
        padding: 9px 12px;
        font-size: .88rem;
        font-family: inherit;
        background: var(--surface-bg, rgba(0, 0, 0, .04));
        border: 1px solid var(--border-light, rgba(0, 0, 0, .1));
        border-radius: 8px;
        color: var(--text-main);
        transition: border-color .15s, box-shadow .15s;
        outline: none;
    }

    .fg input:focus,
    .fg textarea:focus,
    .fg select:focus {
        border-color: var(--primary-color, #07c95b);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color, #07c95b) 15%, transparent);
    }

    .fg textarea {
        resize: vertical;
        min-height: 80px;
        line-height: 1.5;
    }

    .fg select {
        cursor: pointer;
    }

    /* Toggle "Mostrar no perfil" */
    .fg-toggle {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 6px;
        font-size: .75rem;
        color: var(--text-secondary, #999);
        cursor: pointer;
        user-select: none;
    }

    .fg-toggle input[type="checkbox"] {
        accent-color: var(--primary-color, #07c95b);
        width: 13px;
        height: 13px;
        cursor: pointer;
        flex-shrink: 0;
    }

    /* Layout 2 colunas */
    .fg-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    @media (max-width: 520px) {
        .fg-row {
            grid-template-columns: 1fr;
        }
    }

    /* ── Foto de perfil ── */
    .photo-upload-box {
        border: 1.5px dashed var(--border-light, rgba(0, 0, 0, .12));
        border-radius: 10px;
        padding: 16px;
        background: var(--surface-bg, rgba(0, 0, 0, .02));
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .photo-preview {
        position: relative;
        flex-shrink: 0;
    }

    .photo-preview img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary-color, #07c95b);
    }

    .photo-preview-badge {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 20px;
        height: 20px;
        background: var(--primary-color, #07c95b);
        color: #fff;
        border-radius: 50%;
        border: 2px solid var(--bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .6rem;
    }

    .photo-info {
        flex: 1;
        min-width: 0;
    }

    .photo-info strong {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .photo-info span {
        font-size: .72rem;
        color: var(--text-secondary, #aaa);
    }

    .photo-file-input {
        margin-top: 7px;
        font-size: .78rem;
        width: 100%;
        cursor: pointer;
    }

    /* Cropper */
    .cropper-wrapper {
        width: 100%;
        height: 280px;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        display: none;
        margin-bottom: 12px;
    }

    #image-to-crop {
        display: block;
        max-width: 100%;
    }

    /* ── Privacidade ── */
    .privacy-opt {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background .15s;
        margin-bottom: 6px;
    }

    .privacy-opt:hover {
        background: var(--surface-bg, rgba(0, 0, 0, .03));
    }

    .privacy-opt input[type="radio"] {
        accent-color: var(--primary-color);
        margin-top: 2px;
        flex-shrink: 0;
    }

    .privacy-opt-info strong {
        display: block;
        font-size: .88rem;
        font-weight: 600;
    }

    .privacy-opt-info span {
        font-size: .77rem;
        color: var(--text-secondary, #999);
    }

    /* ── Verificação ── */
    .verify-status {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 10px;
        font-size: .85rem;
    }

    .verify-status.ok {
        background: color-mix(in srgb, #07c95b 10%, transparent);
        color: #07c95b;
    }

    .verify-status.wait {
        background: color-mix(in srgb, #f59e0b 10%, transparent);
        color: #f59e0b;
    }

    .verify-status i {
        font-size: 1.2rem;
    }

    /* ── Bloqueados ── */
    .blocked-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 8px;
    }

    .blocked-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        background: var(--surface-bg, rgba(0, 0, 0, .03));
        border: 1px solid var(--border-light, rgba(0, 0, 0, .07));
        border-radius: 10px;
        gap: 10px;
    }

    .blocked-user-info {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }

    .blocked-user-info img {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .blocked-user-info a {
        font-size: .84rem;
        font-weight: 500;
        color: var(--text-main);
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .blocked-user-info a:hover {
        color: var(--primary-color);
    }

    /* ── Botões ── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: var(--primary-color, #07c95b);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: .85rem;
        font-weight: 600;
        cursor: pointer;
        transition: filter .15s, transform .1s;
    }

    .btn-primary:hover {
        filter: brightness(1.08);
    }

    .btn-primary:active {
        transform: scale(.97);
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 16px;
        background: transparent;
        color: var(--text-secondary, #777);
        border: 1px solid var(--border-light, rgba(0, 0, 0, .12));
        border-radius: 8px;
        font-size: .85rem;
        font-weight: 500;
        cursor: pointer;
        transition: background .15s;
    }

    .btn-secondary:hover {
        background: var(--surface-bg, rgba(0, 0, 0, .04));
    }

    .btn-danger-outline {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        background: transparent;
        color: var(--danger-color, #ef4444);
        border: 1px solid var(--danger-color, #ef4444);
        border-radius: 7px;
        font-size: .78rem;
        font-weight: 500;
        cursor: pointer;
        transition: background .15s;
        white-space: nowrap;
    }

    .btn-danger-outline:hover {
        background: color-mix(in srgb, var(--danger-color, #ef4444) 8%, transparent);
    }

    .btn-success {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: #10b981;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: .85rem;
        font-weight: 600;
        cursor: pointer;
        transition: filter .15s;
    }

    .btn-success:hover {
        filter: brightness(1.08);
    }

    .btn-save-row {
        display: flex;
        gap: 10px;
        padding-top: 14px;
        border-top: 1px solid var(--border-light, rgba(0, 0, 0, .06));
        margin-top: 4px;
    }

    /* ── Logout ── */
    .stg-logout {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 16px;
        margin-top: 4px;
        border-radius: 10px;
        font-size: .88rem;
        font-weight: 600;
        color: var(--danger-color, #ef4444);
        text-decoration: none;
        border: 1px solid var(--border-light, rgba(0, 0, 0, .07));
        background: var(--bg-card);
        transition: background .15s;
    }

    .stg-logout:hover {
        background: color-mix(in srgb, var(--danger-color, #ef4444) 6%, var(--bg-card));
    }

    /* ── Empty state ── */
    .stg-empty {
        text-align: center;
        padding: 24px;
        color: var(--text-secondary, #aaa);
        font-size: .84rem;
    }

    .stg-empty i {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 8px;
        opacity: .4;
    }

    /* ── Modal ── */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        inset: 0;
        background: rgba(0, 0, 0, .75);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: var(--bg-card);
        width: 100%;
        max-width: 560px;
        max-height: 92vh;
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: modalIn .25s ease;
        margin: 0 16px;
    }

    .modal-head {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-light, rgba(0, 0, 0, .07));
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .modal-head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: none;
        background: var(--surface-bg, rgba(0, 0, 0, .06));
        color: var(--text-secondary);
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }

    .modal-close:hover {
        background: var(--border-light);
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color, #07c95b) transparent;
    }

    .modal-body::-webkit-scrollbar {
        width: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    /* Passos de verificação */
    .v-step {
        display: none;
    }

    .v-step.is-active {
        display: block;
    }

    /* Step indicator */
    .v-steps-bar {
        display: flex;
        gap: 6px;
        margin-bottom: 18px;
    }

    .v-steps-bar span {
        flex: 1;
        height: 3px;
        border-radius: 2px;
        background: var(--border-light, rgba(0, 0, 0, .1));
        transition: background .3s;
    }

    .v-steps-bar span.done {
        background: var(--primary-color, #07c95b);
    }

    .v-step h4 {
        font-size: .92rem;
        font-weight: 700;
        margin: 0 0 14px;
    }

    .v-step p {
        font-size: .8rem;
        color: var(--text-secondary);
        margin: 0 0 14px;
    }

    /* Captura de documento */
    .capture-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    @media (max-width: 420px) {
        .capture-grid {
            grid-template-columns: 1fr;
        }
    }

    .capture-box label {
        display: block;
        font-size: .75rem;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-secondary);
    }

    .media-preview {
        width: 100%;
        height: 120px;
        background: #111;
        border-radius: 8px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .media-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Vídeo de verificação */
    .video-box {
        width: 100%;
        max-width: 360px;
        height: 240px;
        background: #000;
        border-radius: 10px;
        margin: 0 auto 14px;
        overflow: hidden;
        position: relative;
    }

    .video-box video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .rec-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(0, 0, 0, .6);
        color: #ef4444;
        font-size: .72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        display: none;
        gap: 5px;
        align-items: center;
    }

    .rec-badge.on {
        display: flex;
    }

    /* Camera overlay */
    #cameraOverlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .95);
        z-index: 3000;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
    }

    #cameraOverlay video {
        max-width: 90%;
        max-height: 65vh;
        border: 2px solid #fff;
        border-radius: 8px;
    }

    .btn-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    @keyframes modalIn {
        from {
            opacity: 0;
            transform: translateY(16px) scale(.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stg-wrap {
        animation: fadeUp .35s ease;
    }
</style>

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


<script>
    /* ═══════════════════════════════════════════════
   Accordion das secções de settings
═══════════════════════════════════════════════ */
    document.querySelectorAll('[data-stg-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('[data-stg-card]');
            card.classList.toggle('is-open');
        });
    });

    /* ═══════════════════════════════════════════════
       Cropper de foto de perfil
    ═══════════════════════════════════════════════ */
    const BASE_URL = "<?php echo BASE_URL; ?>";
    let cropper;
    const fileInput = document.getElementById('file-input');
    const imageToCrop = document.getElementById('image-to-crop');
    const cropperWrapper = document.getElementById('cropper-wrapper');
    const previewImg = document.getElementById('preview-img');
    const croppedInput = document.getElementById('cropped_image');

    fileInput.addEventListener('change', e => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            imageToCrop.src = ev.target.result;
            cropperWrapper.style.display = 'block';
            if (cropper) cropper.destroy();
            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                ready() {
                    updateImageData();
                },
                cropend() {
                    updateImageData();
                },
                zoom() {
                    updateImageData();
                }
            });
        };
        reader.readAsDataURL(file);
    });

    function updateImageData() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 500,
            height: 500
        });
        const b64 = canvas.toDataURL('image/jpeg', .9);
        previewImg.src = b64;
        croppedInput.value = b64;
    }

    /* ═══════════════════════════════════════════════
       Passos de verificação
    ═══════════════════════════════════════════════ */
    function nextStep(n) {
        document.querySelectorAll('.v-step').forEach(s => s.classList.remove('is-active'));
        document.getElementById('v-step-' + n)?.classList.add('is-active');
        updateBars(n);
    }

    function prevStep(n) {
        nextStep(n);
    }

    function updateBars(active) {
        for (let i = 1; i <= 3; i++) {
            const bar = document.getElementById('bar-' + i);
            bar?.classList.toggle('done', i <= active);
        }
    }

    /* ═══════════════════════════════════════════════
       Sistema de câmera e verificação
    ═══════════════════════════════════════════════ */
    let currentStream = null;
    let currentCaptureType = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let videoRecordingStream = null;
    let capturedMedia = {
        front: null,
        back: null,
        video: null
    };

    function translateCameraError(err) {
        const m = {
            NotAllowedError: 'Permissão para câmera negada.',
            NotFoundError: 'Câmera não encontrada.',
            NotReadableError: 'Câmera em uso por outra aplicação.'
        };
        return m[err.name] || ('Erro: ' + err.message);
    }

    async function startCapture(type) {
        currentCaptureType = type;
        try {
            currentStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: {
                        ideal: 'environment'
                    }
                }
            });
            document.getElementById('camera_stream').srcObject = currentStream;
            document.getElementById('cameraOverlay').style.display = 'flex';
        } catch (err) {
            alert(translateCameraError(err));
        }
    }

    function takeSnapshot() {
        const video = document.getElementById('camera_stream');
        const canvas = document.getElementById('camera_canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataURL = canvas.toDataURL('image/jpeg', .9);
        capturedMedia[currentCaptureType] = dataURL;

        const preview = document.getElementById('preview_' + currentCaptureType);
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = dataURL;
        preview.appendChild(img);
        closeCamera();
    }

    function closeCamera() {
        currentStream?.getTracks().forEach(t => t.stop());
        currentStream = null;
        document.getElementById('cameraOverlay').style.display = 'none';
    }

    async function startVideoRecording() {
        const btn = document.getElementById('btn_start_video');
        try {
            const constraints = {
                video: {
                    facingMode: {
                        ideal: 'user'
                    },
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                },
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            };
            try {
                videoRecordingStream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch {
                constraints.audio = false;
                videoRecordingStream = await navigator.mediaDevices.getUserMedia(constraints);
            }

            const videoEl = document.getElementById('video_stream');
            videoEl.srcObject = videoRecordingStream;
            videoEl.style.display = 'block';
            document.getElementById('video_playback').style.display = 'none';
            videoEl.onloadedmetadata = () => videoEl.play().catch(console.error);

            const mimeTypes = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
            const mimeType = mimeTypes.find(t => MediaRecorder.isTypeSupported(t)) || '';
            mediaRecorder = new MediaRecorder(videoRecordingStream, {
                mimeType,
                videoBitsPerSecond: 2_500_000
            });
            recordedChunks = [];

            mediaRecorder.ondataavailable = e => {
                if (e.data.size > 0) recordedChunks.push(e.data);
            };
            mediaRecorder.onstop = async () => {
                try {
                    const blob = new Blob(recordedChunks, {
                        type: mediaRecorder.mimeType
                    });
                    const reader = new FileReader();
                    reader.readAsDataURL(blob);
                    reader.onloadend = () => {
                        capturedMedia.video = reader.result;
                        document.getElementById('btn_submit_verification').style.display = 'flex';
                    };
                    const url = URL.createObjectURL(blob);
                    const playback = document.getElementById('video_playback');
                    playback.src = url;
                    playback.style.display = 'block';
                    videoEl.style.display = 'none';
                    videoRecordingStream.getTracks().forEach(t => t.stop());
                    videoRecordingStream = null;
                } catch (err) {
                    alert('Erro ao processar gravação: ' + err.message);
                }
            };
            mediaRecorder.onerror = e => alert('Erro na gravação: ' + e.error);

            mediaRecorder.start();
            const badge = document.getElementById('recording_indicator');
            badge.classList.add('on');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-stop"></i> Gravando...';

            let timeLeft = 10;
            const timer = setInterval(() => {
                timeLeft--;
                document.getElementById('timer').textContent = timeLeft + 's';
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    mediaRecorder.stop();
                    badge.classList.remove('on');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-redo"></i> Gravar novamente';
                }
            }, 1000);

        } catch (err) {
            alert(translateCameraError(err));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-video"></i> Iniciar gravação';
            videoRecordingStream?.getTracks().forEach(t => t.stop());
            videoRecordingStream = null;
        }
    }

    async function submitVerification() {
        if (!capturedMedia.front || !capturedMedia.back || !capturedMedia.video) {
            alert('Capture todas as mídias obrigatórias antes de enviar.');
            return;
        }
        const fields = ['v_full_name', 'v_nickname', 'v_birth_date', 'v_province', 'v_contact'];
        if (fields.some(id => !document.getElementById(id).value.trim())) {
            alert('Preencha todos os campos obrigatórios.');
            return;
        }

        const btn = document.getElementById('btn_submit_verification');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        const fd = new FormData();
        fd.append('full_name', document.getElementById('v_full_name').value.trim());
        fd.append('nickname', document.getElementById('v_nickname').value.trim());
        fd.append('birth_date', document.getElementById('v_birth_date').value.trim());
        fd.append('province', document.getElementById('v_province').value.trim());
        fd.append('contact_phone', document.getElementById('v_contact').value.trim());
        fd.append('id_front', capturedMedia.front);
        fd.append('id_back', capturedMedia.back);
        fd.append('video', capturedMedia.video);

        try {
            const res = await fetch('actions/verification.php', {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const result = await res.json();
            if (result.success) {
                alert(result.message || 'Verificação enviada com sucesso!');
                capturedMedia = {
                    front: null,
                    back: null,
                    video: null
                };
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Erro: ' + (result.message || 'Erro desconhecido'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Enviar para análise';
            }
        } catch (err) {
            alert('Erro na conexão: ' + (err.message || 'Erro desconhecido'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Enviar para análise';
        }
    }

    window.addEventListener('beforeunload', () => {
        currentStream?.getTracks().forEach(t => t.stop());
        videoRecordingStream?.getTracks().forEach(t => t.stop());
        if (mediaRecorder?.state !== 'inactive') mediaRecorder?.stop();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>