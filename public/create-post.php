<?php
header('Content-Type: text/html; charset=UTF-8');

/**
 * Massango - Create Post Page
 * Modern UI style Facebook for creating posts, photos, videos and albums.
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado para publicar.", "danger");
    redirect(BASE_URL . 'login.php');
}

$stmt_user = $pdo->prepare("SELECT stars, balance, is_verified_creator FROM users WHERE id = ?");
$stmt_user->execute([get_current_user_id()]);
$user_stats = $stmt_user->fetch(PDO::FETCH_ASSOC);

$current_user_id = get_current_user_id();
$user_data = User::getUserById($pdo, $current_user_id);

// Regras de venda baseadas nas estrelas do usuário
$rules = [
    'can_sell_post' => $user_stats['stars'] >= 1,
    'can_sell_video' => $user_stats['stars'] >= 2,
    'can_sell_album' => $user_stats['stars'] >= 3,
    'max_post_price' => 1000.00,
    'max_video_price' => 5000.00,
    'max_album_price' => 10000.00
];
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/text-editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/create-post.css">

<?php
$extra_css = ['reels.css'];
$extra_head_js = [];
$hide_feed_container = true;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    /* ===== Indicador de Etapas (Video Wizard) ===== */
    .video-steps-indicator {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-lg);
        position: relative;
        padding: 0 var(--space-sm);
    }

    .video-steps-indicator::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--border);
        z-index: 1;
    }

    .video-step-item {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
    }

    .step-number {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-full);
        background: var(--bg-surface);
        border: 2px solid var(--border);
        color: var(--text-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: var(--weight-bold);
        font-size: var(--text-sm);
        transition: all 0.3s ease;
    }

    .step-label {
        font-size: var(--fb-font-size-xsmall);
        font-weight: var(--weight-semibold);
        color: var(--text-light);
        margin-top: var(--space-xs);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .video-step-item.active .step-number {
        background: var(--bg-card);
        border-color: var(--primary);
        color: var(--primary);
        box-shadow: 0 0 0 4px var(--primary-soft);
    }

    .video-step-item.active .step-label {
        color: var(--primary);
    }

    .video-step-item.completed .step-number {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--text-on-primary);
    }

    .video-step-item.completed .step-label {
        color: var(--text-main);
    }

    /* Conteiner das Etapas */
    .video-wizard-step {
        display: none;
    }

    .video-wizard-step.active {
        display: block;
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ===== Editor de Vídeo Customizado ===== */
    .video-editor-area {
        margin-top: var(--space-md);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: var(--space-md);
        background: var(--bg-surface);
    }

    .video-editor-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--space-sm);
    }

    .video-editor-title {
        font-weight: var(--weight-semibold);
        font-size: var(--text-sm);
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: var(--space-xs);
    }

    .video-limits-badge {
        font-size: var(--fb-font-size-xsmall);
        font-weight: var(--weight-semibold);
        padding: var(--space-xs) var(--space-sm);
        border-radius: var(--radius-full);
        background: var(--primary-soft);
        color: var(--primary);
        white-space: nowrap;
    }

    .video-limits-badge.over-limit {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .video-editor-player {
        width: 100%;
        background: var(--bg-deep);
        border-radius: var(--radius-md);
        overflow: hidden;
        margin-bottom: var(--space-md);
        display: flex;
        justify-content: center;
    }

    .video-editor-player video {
        max-width: 100%;
        max-height: 320px;
        display: block;
    }

    /* Timeline */
    .video-timeline-wrapper {
        margin-bottom: var(--space-md);
    }

    .video-timeline-labels {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: var(--text-xs);
        font-weight: var(--weight-semibold);
        color: var(--text-main);
        margin-bottom: var(--space-xs);
    }

    .video-timeline-duration {
        font-weight: var(--weight-bold);
        color: var(--primary);
    }

    .video-timeline {
        position: relative;
        height: 56px;
        border-radius: var(--radius-sm);
        overflow: hidden;
        background: var(--border);
        user-select: none;
        touch-action: none;
    }

    .timeline-filmstrip {
        position: absolute;
        inset: 0;
        display: flex;
        width: 100%;
        height: 100%;
    }

    .timeline-filmstrip img {
        flex: 1 1 0;
        height: 100%;
        object-fit: cover;
        display: block;
        min-width: 0;
    }

    .timeline-shade {
        position: absolute;
        top: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.65);
        pointer-events: none;
        z-index: 2;
    }

    #timeline-shade-left {
        left: 0;
        width: 0;
    }

    #timeline-shade-right {
        right: 0;
        width: 0;
    }

    .timeline-selection {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        border: 3px solid var(--primary);
        box-sizing: border-box;
        border-radius: var(--radius-sm);
        z-index: 3;
        pointer-events: none;
    }

    .timeline-handle {
        position: absolute;
        top: -3px;
        bottom: -3px;
        width: 22px;
        background: var(--primary);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: auto;
        cursor: ew-resize;
        z-index: 4;
    }

    .timeline-handle-start {
        left: -3px;
    }

    .timeline-handle-end {
        right: -3px;
    }

    .handle-grip {
        width: 4px;
        height: 22px;
        background: var(--text-on-primary);
        border-radius: 2px;
    }

    .handle-time {
        position: absolute;
        top: -24px;
        font-size: 10px;
        font-weight: var(--weight-bold);
        color: var(--primary);
        background: var(--bg-card);
        border: 1px solid var(--border);
        padding: 1px var(--space-xs);
        border-radius: var(--radius-sm);
        white-space: nowrap;
        box-shadow: var(--shadow-sm);
    }

    .timeline-playhead {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #fff;
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.5);
        z-index: 5;
        pointer-events: none;
        left: 0;
    }

    .video-trim-warning {
        margin-top: var(--space-xs);
        padding: var(--space-sm) var(--space-md);
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border-radius: var(--radius-sm);
        font-size: var(--text-xs);
        display: flex;
        align-items: center;
        gap: var(--space-xs);
    }

    /* Thumbnail Picker */
    .thumb-picker-row {
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }

    .thumb-picker-preview {
        position: relative;
        flex: 0 0 90px;
        width: 90px;
        height: 56px;
        border-radius: var(--radius-sm);
        overflow: hidden;
        border: 2px solid var(--primary);
        background: #000;
    }

    .thumb-picker-preview canvas {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .thumb-picker-tag {
        position: absolute;
        bottom: 2px;
        left: 2px;
        font-size: 9px;
        font-weight: var(--weight-bold);
        color: var(--text-on-primary);
        background: var(--primary);
        padding: 1px 4px;
        border-radius: var(--radius-sm);
    }

    .thumb-strip {
        position: relative;
        flex: 1;
        height: 56px;
        border-radius: var(--radius-sm);
        overflow: hidden;
        background: var(--border);
        cursor: pointer;
        user-select: none;
        touch-action: none;
    }

    .thumb-strip-marker {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--primary);
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.4);
        left: 0;
        pointer-events: none;
    }

    /* Compress Options */
    .compress-options {
        display: flex;
        gap: var(--space-sm);
    }

    .compress-option {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: var(--space-sm);
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        cursor: pointer;
        background: var(--bg-card);
        transition: all 0.2s ease;
        text-align: center;
    }

    .compress-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .compress-option-label {
        font-weight: var(--weight-semibold);
        font-size: var(--text-xs);
        color: var(--text-main);
    }

    .compress-option-hint {
        font-size: 11px;
        color: var(--text-muted);
    }

    .compress-option:has(input:checked) {
        border-color: var(--primary);
        background: var(--primary-soft);
    }

    .compress-option:has(input:checked) .compress-option-label {
        color: var(--primary);
    }

    .final-size-status {
        margin-top: var(--space-md);
        padding: var(--space-sm) var(--space-md);
        border-radius: var(--radius-md);
        font-size: var(--text-xs);
        font-weight: var(--weight-semibold);
        display: flex;
        align-items: center;
        gap: var(--space-xs);
    }

    .final-size-status.ok {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .final-size-status.bad {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    /* Wizard Footer Actions */
    .wizard-actions {
        display: flex;
        justify-content: space-between;
        margin-top: var(--space-lg);
        gap: var(--space-sm);
    }

    .btn-wizard-nav {
        border: 1px solid var(--border);
        padding: var(--space-sm) var(--space-lg);
        border-radius: var(--radius-md);
        font-weight: var(--weight-semibold);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: var(--space-xs);
        background: var(--bg-card);
        color: var(--text-main);
        transition: background 0.15s;
    }

    .btn-wizard-nav:hover {
        background: var(--bg-surface);
    }

    .btn-wizard-nav.btn-primary-wizard {
        background: var(--primary);
        color: var(--text-on-primary);
        border-color: var(--primary);
    }

    .btn-wizard-nav.btn-primary-wizard:hover {
        background: var(--primary-hover);
    }

    /* Container de Processamento Nivo Final */
    .processing-stage-container {
        text-align: center;
        padding: var(--space-xl) var(--space-md);
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px dashed var(--border);
    }

    .processing-stage-container i {
        font-size: var(--text-3xl);
        color: var(--primary);
        margin-bottom: var(--space-sm);
    }

    /* Progress Bar Global Ajustada */
    .progress-container {
        display: none;
        background: var(--bg-surface);
        padding: var(--space-md);
        border-radius: var(--radius-md);
        margin-top: var(--space-md);
    }

    .progress-bar-wrapper {
        width: 100%;
        height: 8px;
        background: var(--border);
        border-radius: var(--radius-full);
        overflow: hidden;
        margin-bottom: var(--space-xs);
    }

    .progress-bar-fill {
        height: 100%;
        width: 0%;
        background: var(--primary);
        background-image: linear-gradient(45deg, rgba(255, 255, 255, .15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, .15) 50%, rgba(255, 255, 255, .15) 75%, transparent 75%, transparent);
        background-size: 1rem 1rem;
        animation: progress-bar-stripes 1s linear infinite;
        transition: width 0.2s ease;
    }

    @keyframes progress-bar-stripes {
        from {
            background-position: 1rem 0;
        }

        to {
            background-position: 0 0;
        }
    }

    .progress-text {
        display: flex;
        justify-content: space-between;
        font-size: var(--text-xs);
        font-weight: var(--weight-semibold);
    }
</style>

<div class="create-post-container">
    <div class="create-post-header">
        <h1>Criar Publicação</h1>
    </div>

    <div class="create-post-tabs">
        <div class="tab-item active" data-tab="text"><i class="fa-solid fa-font"></i> Texto</div>
        <div class="tab-item" data-tab="photo"><i class="fa-solid fa-image"></i> Foto</div>
        <div class="tab-item" data-tab="video"><i class="fa-solid fa-video"></i> Vídeo</div>
        <div class="tab-item" data-tab="album"><i class="fa-solid fa-images"></i> Álbum</div>
    </div>

    <div class="create-post-body">
        <div class="user-info-small">
            <img src="<?= UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="Perfil">
            <div class="name"><?= htmlspecialchars($user_data['username']) ?></div>
        </div>

        <!-- Progress Bar Global -->
        <div id="global-progress" class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span id="progress-status">Processando...</span>
                <span id="progress-percent">0%</span>
            </div>
        </div>

        <!-- Formulário Texto -->
        <div id="section-text" class="post-form-section active">
            <form action="<?= BASE_URL ?>actions/post.php" method="POST" class="ajax-post-form" data-type="text">
                <input type="hidden" name="post_type" value="text">
                <input type="hidden" name="content" class="content-hidden">

                <div class="text-editor-container">
                    <div id="editor-text" style="height: 200px;"></div>
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_post'] ? 'disabled' : '' ?>>
                        <span>Colocar à venda (Requer 1 estrela)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_post_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar</button>
                </div>
            </form>
        </div>

        <!-- Formulário Foto -->
        <div id="section-photo" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/post.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="photo">
                <input type="hidden" name="post_type" value="photo">
                <textarea name="content" class="form-control mb-3" placeholder="Escreva uma legenda..."></textarea>

                <div class="file-upload-area" onclick="document.getElementById('input-photo').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Clique para selecionar uma foto</p>
                    <input type="file" id="input-photo" name="image" accept="image/*" style="display:none" onchange="previewMedia(this, 'photo')">
                </div>

                <div id="preview-photo" class="preview-area">
                    <div class="preview-title">Preview da Foto</div>
                    <img src="" class="media-preview">
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_post'] ? 'disabled' : '' ?>>
                        <span>Colocar à venda (Requer 1 estrela)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_post_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar Foto</button>
                </div>
            </form>
        </div>

        <!-- Formulário Vídeo (WIZARD EM ETAPAS) -->
        <div id="section-video" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/video.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="video" id="video-wizard-form">
                <input type="hidden" name="post_type" value="video">

                <!-- Indicador de Passos do Vídeo -->
                <div class="video-steps-indicator">
                    <div class="video-step-item active" id="indicator-step-1">
                        <div class="step-number">1</div>
                        <div class="step-label">Publicação</div>
                    </div>
                    <div class="video-step-item" id="indicator-step-2">
                        <div class="step-number">2</div>
                        <div class="step-label">Edição</div>
                    </div>
                    <div class="video-step-item" id="indicator-step-3">
                        <div class="step-number">3</div>
                        <div class="step-label">Conversão</div>
                    </div>
                </div>

                <!-- ETAPA 1: PUBLICAÇÃO (Metadados e Arquivo) -->
                <div class="video-wizard-step active" id="video-step-content-1">
                    <textarea name="caption" class="form-control mb-3" placeholder="Escreva uma legenda para o vídeo..."></textarea>

                    <div class="file-upload-area" onclick="document.getElementById('input-video').click()">
                        <i class="fa-solid fa-film"></i>
                        <p>Clique para selecionar um vídeo</p>
                        <input type="file" id="input-video" name="video" accept="video/*" style="display:none" onchange="previewMedia(this, 'video')">
                    </div>

                    <div id="preview-video" class="preview-area" style="display:none;">
                        <div class="preview-title">Vídeo Selecionado</div>
                        <video class="media-preview" style="max-height:100px; width:auto;" muted></video>
                    </div>

                    <div class="sale-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_video'] ? 'disabled' : '' ?>>
                            <span>Vender vídeo (Requer 2 estrelas)</span>
                        </label>
                        <div class="price-input-group" style="display:none; margin-top:10px;">
                            <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_video_price'] ?>">
                            <small class="form-help">Máximo: <?= number_format($rules['max_video_price'], 2) ?> MT</small>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 2: EDIÇÃO (Corte e Capa) -->
                <div class="video-wizard-step" id="video-step-content-2">
                    <div id="video-editor" class="video-editor-area">
                        <div class="video-editor-header">
                            <span class="video-editor-title"><i class="fa-solid fa-clapperboard"></i> Ajustar e Cortar Duração</span>
                            <span id="video-limits-badge" class="video-limits-badge"></span>
                        </div>

                        <div class="video-editor-player">
                            <video id="editor-video-el" controls playsinline></video>
                        </div>

                        <!-- Linha do tempo com filmstrip + corte -->
                        <div class="video-timeline-wrapper">
                            <div class="video-timeline-labels">
                                <span><i class="fa-solid fa-scissors"></i> Recortar Intervalo</span>
                                <span id="trim-duration-label" class="video-timeline-duration">0.0s</span>
                            </div>

                            <div id="video-timeline" class="video-timeline">
                                <div id="timeline-filmstrip" class="timeline-filmstrip"></div>
                                <div id="timeline-shade-left" class="timeline-shade"></div>
                                <div id="timeline-shade-right" class="timeline-shade"></div>
                                <div id="timeline-selection" class="timeline-selection">
                                    <div id="trim-handle-start" class="timeline-handle timeline-handle-start">
                                        <span class="handle-time" id="trim-start-label">0.0s</span>
                                        <div class="handle-grip"></div>
                                    </div>
                                    <div id="trim-handle-end" class="timeline-handle timeline-handle-end">
                                        <div class="handle-grip"></div>
                                        <span class="handle-time" id="trim-end-label">0.0s</span>
                                    </div>
                                </div>
                                <div id="timeline-playhead" class="timeline-playhead"></div>
                            </div>

                            <div id="trim-warning" class="video-trim-warning" style="display:none;">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span id="trim-warning-text"></span>
                            </div>
                        </div>

                        <!-- Seletor de frame para thumbnail -->
                        <div class="video-timeline-wrapper">
                            <div class="video-timeline-labels">
                                <span><i class="fa-solid fa-image"></i> Escolha a Capa do Vídeo</span>
                            </div>

                            <div class="thumb-picker-row">
                                <div class="thumb-picker-preview">
                                    <canvas id="thumb-canvas"></canvas>
                                    <span class="thumb-picker-tag">Capa</span>
                                </div>
                                <div id="thumb-strip" class="thumb-strip">
                                    <div id="thumb-strip-filmstrip" class="timeline-filmstrip"></div>
                                    <div id="thumb-strip-marker" class="thumb-strip-marker"></div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="trim_start" id="trim-start-input" value="">
                        <input type="hidden" name="trim_end" id="trim-end-input" value="">
                        <input type="hidden" name="thumb_time" id="thumb-time-input" value="">
                    </div>
                </div>

                <!-- ETAPA 3: CONVERSÃO (Compressão e Publicação Final) -->
                <div class="video-wizard-step" id="video-step-content-3">
                    <div class="video-editor-area">
                        <div class="video-timeline-wrapper">
                            <div class="video-timeline-labels">
                                <span><i class="fa-solid fa-compress"></i> Perfil de Compressão para Renderização</span>
                            </div>
                            <div class="compress-options" id="compress-options">
                                <label class="compress-option">
                                    <input type="radio" name="compress_level" value="none" checked>
                                    <span class="compress-option-label">Qualidade Original</span>
                                    <span class="compress-option-hint">Sem alterações</span>
                                </label>
                                <label class="compress-option">
                                    <input type="radio" name="compress_level" value="medium">
                                    <span class="compress-option-label">Compressão Média</span>
                                    <span class="compress-option-hint">720p • ~50% menor</span>
                                </label>
                                <label class="compress-option">
                                    <input type="radio" name="compress_level" value="high">
                                    <span class="compress-option-label">Compressão Alta</span>
                                    <span class="compress-option-hint">480p • ~75% menor</span>
                                </label>
                            </div>
                        </div>

                        <div id="video-edit-status" class="video-edit-status"></div>

                        <!-- Validador de Metas de Dimensionamento -->
                        <div id="final-size-status" class="final-size-status" style="display:none;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span id="final-size-status-text"></span>
                        </div>
                    </div>

                    <!-- Container Especial de Publicação com Processamento Oculto Inicialmente -->
                    <div class="processing-stage-container mt-4" id="processing-action-box">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <h3>Pronto para Conclusão</h3>
                        <p class="text-muted">O vídeo foi processado localmente e está preparado conforme as etapas selecionadas.</p>
                    </div>
                </div>

                <!-- Controladores de Navegação do Wizard -->
                <div class="wizard-actions">
                    <button type="button" class="btn-wizard-nav" id="btn-wizard-prev" style="display:none;"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
                    <button type="button" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-next">Avançar <i class="fa-solid fa-arrow-right"></i></button>
                    <button type="submit" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-submit" style="display:none;"><i class="fa-solid fa-circle-check"></i> Concluir e Publicar</button>
                </div>
            </form>
        </div>

        <!-- Formulário Álbum -->
        <div id="section-album" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/album.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="album">
                <input type="hidden" name="post_type" value="album">
                <input type="hidden" name="cover_index" id="cover_index" value="0">

                <div class="form-group mb-3">
                    <label class="form-label">Nome do Álbum <span class="text-danger">*</span></label>
                    <input type="text" name="album_name" class="form-control" placeholder="Nome do Álbum" required>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="album_description" class="form-control" placeholder="Descrição do álbum..."></textarea>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Categoria <span class="text-danger">*</span></label>
                    <select name="categoria" class="form-control" onchange="handleCategoryChange(this)" required>
                        <option value="normal">Normal</option>
                        <option value="18+">18+ (Conteúdo Adulto)</option>
                    </select>
                </div>

                <div id="subcat-group-album" class="form-group mb-3" style="display:none">
                    <label class="form-label">Subcategoria <span class="text-danger">*</span></label>
                    <input type="text" name="subcategoria" id="subcat-input-album" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                </div>

                <div class="file-upload-area" onclick="document.getElementById('input-album').click()">
                    <i class="fa-solid fa-images"></i>
                    <p>Clique para selecionar as fotos</p>
                    <input type="file" id="input-album" name="images[]" accept="image/*" multiple style="display:none" onchange="previewAlbum(this)" required>
                </div>

                <div id="preview-album" class="preview-area">
                    <div class="preview-title">Fotos do Álbum (Clique para definir a capa)</div>
                    <div class="album-preview-grid" id="album-grid"></div>
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_album'] ? 'disabled' : '' ?>>
                        <span>Vender álbum (Requer 3 estrelas)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_album_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_album_price'], 2) ?> MT</small>

                        <?php if ($user_stats['stars'] >= 3): ?>
                            <div class="form-group mt-3">
                                <label class="form-label">Onde disponibilizar?</label>
                                <div class="radio-group">
                                    <label class="radio-wrapper">
                                        <input type="radio" name="show_in_feed" value="1" checked>
                                        <span class="radio-label">No Feed (visível para todos)</span>
                                    </label>
                                    <label class="radio-wrapper">
                                        <input type="radio" name="show_in_feed" value="0">
                                        <span class="radio-label">Apenas via Link (privado)</span>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Criar Álbum</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    function handleCategoryChange(select) {
        const subcatGroup = document.getElementById('subcat-group-album');
        const subcatInput = document.getElementById('subcat-input-album');
        if (select.value === '18+') {
            subcatGroup.style.display = 'block';
            subcatInput.setAttribute('required', 'required');
        } else {
            subcatGroup.style.display = 'none';
            subcatInput.removeAttribute('required');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicialização do Editor de Texto
        const quill = new Quill('#editor-text', {
            theme: 'snow',
            placeholder: 'No que você está pensando?',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    ['clean']
                ]
            }
        });

        // Alternância de Abas Principais
        const tabs = document.querySelectorAll('.tab-item');
        const sections = document.querySelectorAll('.post-form-section');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                tabs.forEach(t => t.classList.remove('active'));
                sections.forEach(s => s.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('section-' + target).classList.add('active');
            });
        });

        // Ativação das Opções de Preço/Venda
        document.querySelectorAll('.toggle-sale').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const priceGroup = this.closest('.sale-options').querySelector('.price-input-group');
                priceGroup.style.display = this.checked ? 'block' : 'none';
                if (this.checked) {
                    priceGroup.querySelector('input').focus();
                }
            });
        });

        // LÓGICA DO WIZARD DO VÍDEO (Navegação Obrigatória)
        let currentStep = 1;
        const totalSteps = 3;
        const btnPrev = document.getElementById('btn-wizard-prev');
        const btnNext = document.getElementById('btn-wizard-next');
        const btnSubmit = document.getElementById('btn-wizard-submit');

        function updateWizardUI() {
            // Alterna blocos de conteúdo das etapas
            document.querySelectorAll('.video-wizard-step').forEach(step => step.classList.remove('active'));
            document.getElementById(`video-step-content-${currentStep}`).classList.add('active');

            // Atualiza barra de indicadores superiores
            for (let i = 1; i <= totalSteps; i++) {
                const indicator = document.getElementById(`indicator-step-${i}`);
                if (i < currentStep) {
                    indicator.className = 'video-step-item completed';
                } else if (i === currentStep) {
                    indicator.className = 'video-step-item active';
                } else {
                    indicator.className = 'video-step-item';
                }
            }

            // Exibição condicional dos controladores inferiores
            btnPrev.style.display = currentStep === 1 ? 'none' : 'flex';
            if (currentStep === totalSteps) {
                btnNext.style.display = 'none';
                btnSubmit.style.display = 'flex';
            } else {
                btnNext.style.display = 'flex';
                btnSubmit.style.display = 'none';
            }
        }

        btnNext.addEventListener('click', function() {
            if (currentStep === 1) {
                const videoFiles = document.getElementById('input-video').files;
                if (videoFiles.length === 0) {
                    alert("Por favor, selecione um arquivo de vídeo para avançar.");
                    return;
                }
            }
            if (currentStep === 2) {
                if (window.videoWithinLimits === false) {
                    alert("O intervalo recortado excede as restrições permitidas. Corrija o corte.");
                    return;
                }
            }

            if (currentStep < totalSteps) {
                currentStep++;
                updateWizardUI();
            }
        });

        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                updateWizardUI();
            }
        });

        // ── Submissão Unificada via AJAX (Texto, Foto, Álbum, Vídeo) ──────────────
        document.querySelectorAll('.ajax-post-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const type = this.dataset.type;
                const currentForm = this;

                // Validação: Texto
                if (type === 'text') {
                    if (quill.getText().trim() === '') {
                        alert("Por favor, escreva algo.");
                        return;
                    }
                    this.querySelector('.content-hidden').value = quill.root.innerHTML;
                }

                // Validação: Álbum
                if (type === 'album') {
                    if (document.getElementById('input-album').files.length === 0) {
                        alert("Por favor, selecione pelo menos uma foto para o álbum.");
                        return;
                    }
                }

                // Validação: Vídeo (limites de tamanho/duração verificados no wizard)
                if (type === 'video') {
                    if (window.videoWithinLimits === false) {
                        alert("O vídeo não cumpre os requisitos de compressão/tamanho. Ajuste na etapa 2 ou 3.");
                        return;
                    }
                }

                const formData = new FormData(currentForm);
                const submitBtn = type === 'video' ?
                    document.getElementById('btn-wizard-submit') :
                    currentForm.querySelector('button[type="submit"]');

                if (submitBtn) submitBtn.disabled = true;

                // Vídeo: captura o frame do Canvas como thumbnail antes de enviar
                if (type === 'video') {
                    const canvas = document.getElementById('thumb-canvas');
                    if (canvas) {
                        canvas.toBlob(function(blob) {
                            if (blob) {
                                formData.append('thumbnail', blob, 'video_thumb.jpg');
                            }
                            // Só dispara o AJAX após o blob estar pronto (callback assíncrono)
                            dispararEnvioAjax(currentForm.action, formData, submitBtn, type);
                        }, 'image/jpeg', 0.9);
                        return; // Aguarda o callback do toBlob antes de prosseguir
                    }
                }

                // Texto, Foto e Álbum: envio direto
                dispararEnvioAjax(currentForm.action, formData, submitBtn, type);
            });
        });
    });

    // ── Motor de Envio AJAX com Barra de Progresso ────────────────────────────
    // Isolado fora do DOMContentLoaded para ser acessível ao callback do toBlob()
    function dispararEnvioAjax(actionUrl, formData, submitBtn, type) {
        const xhr = new XMLHttpRequest();
        const progressContainer = document.getElementById('global-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressPercent = document.getElementById('progress-percent');
        const progressStatus = document.getElementById('progress-status');

        xhr.open('POST', actionUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Monitoriza o upload byte a byte (relevante para vídeos e fotos pesadas)
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressContainer.style.display = 'block';
                progressFill.style.width = percent + '%';
                progressPercent.textContent = percent + '%';
                progressStatus.textContent = percent === 100 ?
                    'A processar e converter o vídeo no servidor... (Aguarde)' :
                    (type === 'video' ? 'A enviar pacotes do vídeo...' : 'A enviar ficheiros...');
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        progressStatus.textContent = 'Publicado com sucesso!';
                        window.location.href = '<?= BASE_URL ?>index.php';
                    } else {
                        alert('Erro: ' + (res.message || 'Erro desconhecido'));
                        if (submitBtn) submitBtn.disabled = false;
                        progressContainer.style.display = 'none';
                    }
                } catch (err) {
                    alert('Erro inesperado no retorno do servidor.');
                    console.error('Resposta bruta do servidor:', xhr.responseText);
                    if (submitBtn) submitBtn.disabled = false;
                    progressContainer.style.display = 'none';
                }
            } else {
                alert('Erro HTTP ' + xhr.status + '. Tente novamente.');
                if (submitBtn) submitBtn.disabled = false;
                progressContainer.style.display = 'none';
            }
        };

        xhr.onerror = function() {
            alert('Erro de rede ou falha na conexão com o servidor.');
            if (submitBtn) submitBtn.disabled = false;
            progressContainer.style.display = 'none';
        };

        xhr.send(formData);
    }

    function previewMedia(input, type) {
        const previewArea = document.getElementById('preview-' + type);
        const previewMedia = previewArea.querySelector('.media-preview');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileUrl = URL.createObjectURL(file);

            previewMedia.src = fileUrl;
            previewArea.style.display = 'block';

            if (type === 'video') {
                initVideoEditor(file, fileUrl);
            }
        }
    }

    function previewAlbum(input) {
        if (input.files && input.files.length > 0) {
            const previewArea = document.getElementById('preview-album');
            const grid = document.getElementById('album-grid');
            const coverInput = document.getElementById('cover_index');
            grid.innerHTML = '';
            coverInput.value = 0;

            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'album-preview-item' + (index === 0 ? ' is-cover' : '');
                    item.onclick = function() {
                        document.querySelectorAll('.album-preview-item').forEach(el => el.classList.remove('is-cover'));
                        item.classList.add('is-cover');
                        coverInput.value = index;
                    };
                    item.innerHTML = `<img src="${e.target.result}">`;
                    grid.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
            previewArea.style.display = 'block';
        }
    }

    // ===================== MOTOR DE EDIÇÃO DO VÍDEO =====================
    const VIDEO_MAX_DURATION = 90;
    const VIDEO_MAX_SIZE_MB = 100;
    const VIDEO_MAX_ORIGINAL_SIZE_MB = 150;
    const FILMSTRIP_FRAMES = 10;

    const COMPRESS_SIZE_FACTOR = {
        none: 1,
        medium: 0.5,
        high: 0.25
    };

    let currentVideoFile = null;
    let videoDuration = 0;
    let trimStart = 0;
    let trimEnd = 0;
    let thumbTime = 0;

    function initVideoEditor(file, fileUrl) {
        currentVideoFile = file;
        window.videoWithinLimits = undefined;

        const editorVideo = document.getElementById('editor-video-el');
        const status = document.getElementById('video-edit-status');
        const finalStatus = document.getElementById('final-size-status');

        status.textContent = '';
        status.className = 'video-edit-status';
        finalStatus.style.display = 'none';
        editorVideo.src = fileUrl;

        editorVideo.onloadedmetadata = function() {
            videoDuration = editorVideo.duration;
            trimStart = 0;
            trimEnd = Math.min(videoDuration, VIDEO_MAX_DURATION);
            thumbTime = 0;

            updateTrimLabels();
            updateLimitsBadge(file);
            buildFilmstrips();
            renderSelection();
            captureThumbFrame(thumbTime);
            syncHiddenFields();
            recalculateEstimate();
        };

        setupTimelineDrag();
        setupThumbStripDrag();

        document.querySelectorAll('input[name="compress_level"]').forEach(function(radio) {
            radio.onchange = function() {
                recalculateEstimate();
            };
        });
    }

    function updateLimitsBadge(file) {
        const badge = document.getElementById('video-limits-badge');
        const sizeMB = (file.size / (1024 * 1024));
        const overOriginalSize = sizeMB > VIDEO_MAX_ORIGINAL_SIZE_MB;
        const overDuration = videoDuration > VIDEO_MAX_DURATION;

        badge.textContent = 'Original: ' + videoDuration.toFixed(0) + 's • ' + sizeMB.toFixed(1) + 'MB';
        badge.classList.toggle('over-limit', overOriginalSize || overDuration);

        const warning = document.getElementById('trim-warning');
        const warningText = document.getElementById('trim-warning-text');

        if (overOriginalSize) {
            warning.style.display = 'flex';
            warningText.textContent = 'Este arquivo excede o limite estipulado de ' + VIDEO_MAX_ORIGINAL_SIZE_MB + 'MB.';
        } else if (overDuration) {
            warning.style.display = 'flex';
            warningText.textContent = 'O arquivo ultrapassa os ' + VIDEO_MAX_DURATION + 's máximos. Seleção adaptada automaticamente.';
        } else {
            warning.style.display = 'none';
        }
    }

    async function buildFilmstrips() {
        const mainStrip = document.getElementById('timeline-filmstrip');
        const thumbStrip = document.getElementById('thumb-strip-filmstrip');
        mainStrip.innerHTML = '';
        thumbStrip.innerHTML = '';

        const editorVideo = document.getElementById('editor-video-el');
        const captureCanvas = document.createElement('canvas');
        const ctx = captureCanvas.getContext('2d');
        const wasMuted = editorVideo.muted;
        editorVideo.muted = true;

        for (let i = 0; i < FILMSTRIP_FRAMES; i++) {
            const t = (videoDuration / FILMSTRIP_FRAMES) * i;
            const dataUrl = await grabFrame(editorVideo, captureCanvas, ctx, t);

            const img1 = document.createElement('img');
            img1.src = dataUrl;
            mainStrip.appendChild(img1);

            const img2 = document.createElement('img');
            img2.src = dataUrl;
            thumbStrip.appendChild(img2);
        }

        editorVideo.muted = wasMuted;
        editorVideo.currentTime = 0;
    }

    function grabFrame(video, canvas, ctx, time) {
        return new Promise((resolve) => {
            const onSeeked = function() {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                video.removeEventListener('seeked', onSeeked);
                resolve(canvas.toDataURL('image/jpeg', 0.5));
            };
            video.addEventListener('seeked', onSeeked);
            video.currentTime = Math.min(time, Math.max(0, video.duration - 0.05));
        });
    }

    function updateTrimLabels() {
        document.getElementById('trim-start-label').textContent = trimStart.toFixed(1) + 's';
        document.getElementById('trim-end-label').textContent = trimEnd.toFixed(1) + 's';
        document.getElementById('trim-duration-label').textContent = (trimEnd - trimStart).toFixed(1) + 's';
    }

    function renderSelection() {
        const startPct = (trimStart / videoDuration) * 100;
        const endPct = (trimEnd / videoDuration) * 100;

        const selection = document.getElementById('timeline-selection');
        selection.style.left = startPct + '%';
        selection.style.width = (endPct - startPct) + '%';

        document.getElementById('timeline-shade-left').style.width = startPct + '%';
        document.getElementById('timeline-shade-right').style.width = (100 - endPct) + '%';

        const overLimit = (trimEnd - trimStart) > VIDEO_MAX_DURATION + 0.01;
        document.getElementById('timeline-selection').style.borderColor = overLimit ? 'var(--danger)' : 'var(--primary)';

        updateTrimLabels();
    }

    function setupTimelineDrag() {
        const timeline = document.getElementById('video-timeline');
        const handleStart = document.getElementById('trim-handle-start');
        const handleEnd = document.getElementById('trim-handle-end');

        function clientXToTime(clientX) {
            const rect = timeline.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
            return ratio * videoDuration;
        }

        function bindHandle(handle, isStart) {
            handle.onpointerdown = function(e) {
                e.preventDefault();
                handle.setPointerCapture(e.pointerId);

                const onMove = function(ev) {
                    let t = clientXToTime(ev.clientX);
                    if (isStart) {
                        trimStart = Math.min(t, trimEnd - 0.2);
                        trimStart = Math.max(0, trimStart);
                        if (trimEnd - trimStart > VIDEO_MAX_DURATION) {
                            trimStart = trimEnd - VIDEO_MAX_DURATION;
                        }
                    } else {
                        trimEnd = Math.max(t, trimStart + 0.2);
                        trimEnd = Math.min(videoDuration, trimEnd);
                        if (trimEnd - trimStart > VIDEO_MAX_DURATION) {
                            trimEnd = trimStart + VIDEO_MAX_DURATION;
                        }
                    }
                    renderSelection();
                    seekPreview(isStart ? trimStart : trimEnd);
                };

                const onUp = function() {
                    handle.releasePointerCapture(e.pointerId);
                    document.removeEventListener('pointermove', onMove);
                    document.removeEventListener('pointerup', onUp);
                    syncHiddenFields();
                    recalculateEstimate();
                };

                document.addEventListener('pointermove', onMove);
                document.addEventListener('pointerup', onUp);
            };
        }

        bindHandle(handleStart, true);
        bindHandle(handleEnd, false);
    }

    function seekPreview(time) {
        const editorVideo = document.getElementById('editor-video-el');
        editorVideo.currentTime = time;
        const playhead = document.getElementById('timeline-playhead');
        playhead.style.left = ((time / videoDuration) * 100) + '%';
    }

    function setupThumbStripDrag() {
        const strip = document.getElementById('thumb-strip');
        const marker = document.getElementById('thumb-strip-marker');

        function clientXToTime(clientX) {
            const rect = strip.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
            return ratio * videoDuration;
        }

        function setThumbTime(t) {
            thumbTime = Math.min(videoDuration, Math.max(0, t));
            marker.style.left = ((thumbTime / videoDuration) * 100) + '%';
            captureThumbFrame(thumbTime);
            syncHiddenFields();
        }

        strip.onpointerdown = function(e) {
            e.preventDefault();
            strip.setPointerCapture(e.pointerId);
            setThumbTime(clientXToTime(e.clientX));

            const onMove = function(ev) {
                setThumbTime(clientXToTime(ev.clientX));
            };
            const onUp = function() {
                strip.releasePointerCapture(e.pointerId);
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        };
    }

    function captureThumbFrame(time) {
        const editorVideo = document.getElementById('editor-video-el');
        const canvas = document.getElementById('thumb-canvas');

        const onSeeked = function() {
            canvas.width = editorVideo.videoWidth;
            canvas.height = editorVideo.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(editorVideo, 0, 0, canvas.width, canvas.height);
            editorVideo.removeEventListener('seeked', onSeeked);
        };

        editorVideo.addEventListener('seeked', onSeeked);
        editorVideo.currentTime = Math.min(time, Math.max(0, videoDuration - 0.05));
    }

    function syncHiddenFields() {
        document.getElementById('trim-start-input').value = trimStart.toFixed(2);
        document.getElementById('trim-end-input').value = trimEnd.toFixed(2);
        document.getElementById('thumb-time-input').value = thumbTime.toFixed(2);
    }

    function recalculateEstimate() {
        if (!currentVideoFile) return;

        const duration = trimEnd - trimStart;
        const durationRatio = videoDuration > 0 ? (duration / videoDuration) : 1;
        const compressLevel = (document.querySelector('input[name="compress_level"]:checked') || {}).value || 'none';
        const compressFactor = COMPRESS_SIZE_FACTOR[compressLevel] ?? 1;

        const originalSizeMB = currentVideoFile.size / (1024 * 1024);
        const estimatedSizeMB = originalSizeMB * durationRatio * compressFactor;

        updateFinalSizeStatus(estimatedSizeMB, duration, compressLevel);
    }

    function updateFinalSizeStatus(sizeMB, duration, compressLevel) {
        const box = document.getElementById('final-size-status');
        const text = document.getElementById('final-size-status-text');
        const icon = box.querySelector('i');

        const overSize = sizeMB > VIDEO_MAX_SIZE_MB;
        const overDuration = duration > VIDEO_MAX_DURATION;
        const ok = !overSize && !overDuration;

        box.style.display = 'flex';
        box.className = 'final-size-status ' + (ok ? 'ok' : 'bad');
        icon.className = ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation';

        if (ok) {
            text.textContent = 'Configuração válida! Peso estimado: ~' + sizeMB.toFixed(1) + 'MB de ' + VIDEO_MAX_SIZE_MB + 'MB permitidos.';
        } else if (overSize) {
            const suggestion = compressLevel === 'none' ? ' Modifique o perfil para Média ou Alta.' : ' É necessário cortar mais o fragmento.';
            text.textContent = 'O tamanho estimado (~' + sizeMB.toFixed(1) + 'MB) excede as cotas.' + suggestion;
        } else {
            text.textContent = 'Duração inválida. Limite configurado para ' + VIDEO_MAX_DURATION + 's.';
        }

        window.videoWithinLimits = ok;
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>