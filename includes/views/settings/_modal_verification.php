<!-- ══ Modal de Verificação de Identidade ══════════════════════ -->
<div id="verificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h2><i class="fa-solid fa-shield-halved"></i> Verificação de Identidade</h2>
            <button class="modal-close" type="button"
                onclick="document.getElementById('verificationModal').style.display='none'"
                aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">

            <!-- Barra de progresso -->
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
                                <?php
                                $provinces = [
                                    'Maputo','Gaza','Inhambane','Sofala','Manica',
                                    'Tete','Zambézia','Nampula','Niassa','Cabo Delgado',
                                ];
                                foreach ($provinces as $p): ?>
                                    <option><?= $p ?></option>
                                <?php endforeach; ?>
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
