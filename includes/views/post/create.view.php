<?php
/**
 * View: Criar Publicação
 *
 * Variáveis esperadas (injetadas pelo PostController::showCreate):
 *   @var array  $user_data   Dados do utilizador autenticado
 *   @var array  $user_stats  ['stars', 'balance', 'is_verified_creator']
 *   @var array  $rules       Regras de venda calculadas pelo PostService
 */

// Segurança: esta view nunca deve ser acessada diretamente
if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado.');
}
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/create-post.css">

<?php
$extra_css          = ['reels.css'];
$extra_head_js      = [];
$hide_feed_container = true;
require_once __DIR__ . '/../../../includes/header.php';
?>

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

        <!-- Cabeçalho do utilizador -->
        <div class="user-info-small">
            <img src="<?= UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="Perfil">
            <div class="name"><?= htmlspecialchars($user_data['username']) ?></div>
        </div>

        <!-- Barra de progresso global (controlada por JS) -->
        <div id="global-progress" class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span id="progress-status">Processando...</span>
                <span id="progress-percent">0%</span>
            </div>
        </div>

        <!-- ===================== SECÇÃO: TEXTO ===================== -->
        <div id="section-text" class="post-form-section active">
            <form action="<?= BASE_URL ?>actions/post.php" method="POST"
                  class="ajax-post-form" data-type="text">
                <input type="hidden" name="post_type" value="text">
                <input type="hidden" name="content" class="content-hidden">

                <div class="text-editor-container">
                    <div id="editor-text" style="height: 200px;"></div>
                </div>

                <?php include __DIR__ . '/_sale_options_post.php'; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar</button>
                </div>
            </form>
        </div>

        <!-- ===================== SECÇÃO: FOTO ===================== -->
        <div id="section-photo" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/post.php" method="POST"
                  enctype="multipart/form-data" class="ajax-post-form" data-type="photo">
                <input type="hidden" name="post_type" value="photo">
                <textarea name="content" class="form-control mb-3"
                          placeholder="Escreva uma legenda..."></textarea>

                <div class="file-upload-area" onclick="document.getElementById('input-photo').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Clique para selecionar uma foto</p>
                    <input type="file" id="input-photo" name="image" accept="image/*"
                           style="display:none" onchange="previewMedia(this, 'photo')">
                </div>

                <div id="preview-photo" class="preview-area">
                    <div class="preview-title">Preview da Foto</div>
                    <img src="" class="media-preview">
                </div>

                <?php include __DIR__ . '/_sale_options_post.php'; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar Foto</button>
                </div>
            </form>
        </div>

        <!-- ===================== SECÇÃO: VÍDEO (WIZARD) ===================== -->
        <div id="section-video" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/video.php" method="POST"
                  enctype="multipart/form-data" class="ajax-post-form"
                  data-type="video" id="video-wizard-form">
                <input type="hidden" name="post_type" value="video">

                <!-- Indicador de passos -->
                <div class="video-steps-indicator">
                    <?php foreach (['Publicação', 'Edição', 'Conversão'] as $i => $label): ?>
                        <div class="video-step-item <?= $i === 0 ? 'active' : '' ?>"
                             id="indicator-step-<?= $i + 1 ?>">
                            <div class="step-number"><?= $i + 1 ?></div>
                            <div class="step-label"><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PASSO 1: metadados e arquivo -->
                <div class="video-wizard-step active" id="video-step-content-1">
                    <textarea name="caption" class="form-control mb-3"
                              placeholder="Escreva uma legenda para o vídeo..."></textarea>

                    <div class="file-upload-area" onclick="document.getElementById('input-video').click()">
                        <i class="fa-solid fa-film"></i>
                        <p>Clique para selecionar um vídeo</p>
                        <input type="file" id="input-video" name="video" accept="video/*"
                               style="display:none" onchange="previewMedia(this, 'video')">
                    </div>

                    <div id="preview-video" class="preview-area" style="display:none;">
                        <div class="preview-title">Vídeo Selecionado</div>
                        <video class="media-preview" style="max-height:100px; width:auto;" muted></video>
                    </div>

                    <?php include __DIR__ . '/_sale_options_video.php'; ?>
                </div>

                <!-- PASSO 2: corte e capa -->
                <div class="video-wizard-step" id="video-step-content-2">
                    <div id="video-editor" class="video-editor-area">
                        <div class="video-editor-header">
                            <span class="video-editor-title">
                                <i class="fa-solid fa-clapperboard"></i> Ajustar e Cortar Duração
                            </span>
                            <span id="video-limits-badge" class="video-limits-badge"></span>
                        </div>

                        <div class="video-editor-player">
                            <video id="editor-video-el" controls playsinline></video>
                        </div>

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

                        <!-- Seletor de thumbnail -->
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
                        <input type="hidden" name="trim_end"   id="trim-end-input"   value="">
                        <input type="hidden" name="thumb_time" id="thumb-time-input"  value="">
                    </div>
                </div>

                <!-- PASSO 3: compressão -->
                <div class="video-wizard-step" id="video-step-content-3">
                    <div class="video-editor-area">
                        <div class="video-timeline-wrapper">
                            <div class="video-timeline-labels">
                                <span><i class="fa-solid fa-compress"></i> Perfil de Compressão</span>
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
                        <div id="final-size-status" class="final-size-status" style="display:none;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span id="final-size-status-text"></span>
                        </div>
                    </div>
                    <div class="processing-stage-container mt-4" id="processing-action-box">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <h3>Pronto para Conclusão</h3>
                        <p class="text-muted">O vídeo foi processado e está preparado para publicação.</p>
                    </div>
                </div>

                <!-- Navegação do wizard -->
                <div class="wizard-actions">
                    <button type="button" class="btn-wizard-nav" id="btn-wizard-prev" style="display:none;">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="button" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-next">
                        Avançar <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-submit" style="display:none;">
                        <i class="fa-solid fa-circle-check"></i> Concluir e Publicar
                    </button>
                </div>
            </form>
        </div>

        <!-- ===================== SECÇÃO: ÁLBUM ===================== -->
        <div id="section-album" class="post-form-section">
            <form action="<?= BASE_URL ?>actions/album.php" method="POST"
                  enctype="multipart/form-data" class="ajax-post-form" data-type="album">
                <input type="hidden" name="post_type"    value="album">
                <input type="hidden" name="cover_index"  id="cover_index" value="0">

                <div class="form-group mb-3">
                    <label class="form-label">Nome do Álbum <span class="text-danger">*</span></label>
                    <input type="text" name="album_name" class="form-control"
                           placeholder="Nome do Álbum" required>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="album_description" class="form-control"
                              placeholder="Descrição do álbum..."></textarea>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Categoria <span class="text-danger">*</span></label>
                    <select name="categoria" class="form-control"
                            onchange="handleCategoryChange(this)" required>
                        <option value="normal">Normal</option>
                        <option value="18+">18+ (Conteúdo Adulto)</option>
                    </select>
                </div>

                <div id="subcat-group-album" class="form-group mb-3" style="display:none">
                    <label class="form-label">Subcategoria <span class="text-danger">*</span></label>
                    <input type="text" name="subcategoria" id="subcat-input-album"
                           class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                </div>

                <div class="file-upload-area" onclick="document.getElementById('input-album').click()">
                    <i class="fa-solid fa-images"></i>
                    <p>Clique para selecionar as fotos</p>
                    <input type="file" id="input-album" name="images[]" accept="image/*"
                           multiple style="display:none" onchange="previewAlbum(this)" required>
                </div>

                <div id="preview-album" class="preview-area">
                    <div class="preview-title">Fotos do Álbum (Clique para definir a capa)</div>
                    <div class="album-preview-grid" id="album-grid"></div>
                </div>

                <?php include __DIR__ . '/_sale_options_album.php'; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Criar Álbum</button>
                </div>
            </form>
        </div>

    </div><!-- /.create-post-body -->
</div><!-- /.create-post-container -->

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/pages/create-post.js"></script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
