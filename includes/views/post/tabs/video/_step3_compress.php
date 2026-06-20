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
