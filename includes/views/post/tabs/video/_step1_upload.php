<?php /** @var array $rules */ ?>
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

    <?php require __DIR__ . '/../../_sale_options_video.php'; ?>
</div>
