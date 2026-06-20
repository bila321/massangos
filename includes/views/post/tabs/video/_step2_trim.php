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

        <!-- Linha do tempo de corte -->
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
        <input type="hidden" name="trim_end" id="trim-end-input" value="">
        <input type="hidden" name="thumb_time" id="thumb-time-input" value="">
    </div>
</div>
