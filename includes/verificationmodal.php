<style>
    /* 1. LAYOUT GERAL */
    .settings-wrapper {
        max-width: 900px;
        margin: 40px auto;
        padding: 0 20px;
        animation: fadeInUp 0.5s ease;
    }

    .settings-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-xl);
        box-shadow: var(--card-shadow);
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .settings-header {
        padding: 30px;
        background: var(--dark-bg);
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .settings-header h2 {
        margin: 0;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-color);
    }

    .settings-content {
        padding: 30px;
    }

    .settings-section {
        margin-bottom: 40px;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--text-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* 2. PRIVACIDADE E BLOQUEIOS */
    .privacy-container {
        background: var(--surface-bg);
        padding: 25px;
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--border-light);
    }

    .privacy-option {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 15px;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .privacy-option:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .blocked-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

    .blocked-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: var(--surface-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--border-light);
    }

    /* 3. SISTEMA DE MODAL (ORGANIZADO) */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: var(--card-bg);
        width: 100%;
        max-width: 600px;
        max-height: 95vh;
        /* Evita que o modal saia da tela */
        border-radius: var(--border-radius-xl);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
        animation: modalFadeIn 0.3s ease;
    }

    .modal-header-inner {
        padding: 20px 25px;
        background: var(--dark-bg);
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        /* Não deixa o header encolher */
    }

    /* A ÁREA DO SCROLL (80vh) */
    .modal-body {
        padding: 5px;
        overflow-y: auto;
        max-height: 100vh;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) transparent;
    }

    /* Custom Scrollbar Webkit */
    .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background-color: var(--primary-color);
        border-radius: 10px;
    }

    /* 4. EDITOR DE FOTO (CROPPER) */
    .cropper-container-wrapper {
        width: 100%;
        height: 350px;
        /* Altura fixa para o editor */
        background: #000;
        margin-bottom: 20px;
        border-radius: var(--border-radius);
        overflow: hidden;
        display: none;
        /* Só aparece ao carregar arquivo */
    }

    #image-to-crop {
        display: block;
        max-width: 100%;
    }

    /* 5. FORMULÁRIOS INTERNOS */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group textarea {
        width: 100%;
        padding: 12px;
        background: var(--surface-bg);
        border: 1px solid var(--border-light);
        border-radius: var(--border-radius);
        color: var(--text-color);
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<!-- Modal de Verificação -->
<div id="verificationModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header-inner">
            <h2>Verificação de Identidade</h2>
            <span class="close-button" onclick="document.getElementById('verificationModal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body">
            <div id="verificationSteps">
                <!-- Step 1: Dados Pessoais -->
                <div class="v-step" id="v-step-1">
                    <h4>Passo 1: Dados Pessoais</h4>
                    <div class="form-group">
                        <label>Nome e Sobrenome</label>
                        <input type="text" id="v_full_name" placeholder="Como no documento" required>
                    </div>
                    <div class="form-group">
                        <label>Apelido</label>
                        <input type="text" id="v_nickname" required>
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" id="v_birth_date" required>
                    </div>
                    <div class="form-group">
                        <label>Província</label>
                        <select id="v_province" class="form-control" style="width: 100%; padding: 12px; background: var(--surface-bg); border: 1px solid var(--border-light); border-radius: var(--border-radius); color: var(--text-color);">
                            <option value="">Selecione...</option>
                            <option value="Maputo">Maputo</option>
                            <option value="Gaza">Gaza</option>
                            <option value="Inhambane">Inhambane</option>
                            <option value="Sofala">Sofala</option>
                            <option value="Manica">Manica</option>
                            <option value="Tete">Tete</option>
                            <option value="Zambézia">Zambézia</option>
                            <option value="Nampula">Nampula</option>
                            <option value="Niassa">Niassa</option>
                            <option value="Cabo Delgado">Cabo Delgado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contacto (WhatsApp/Telemóvel)</label>
                        <input type="text" id="v_contact" placeholder="+258..." required>
                    </div>
                    <button class="btn btn-primary" onclick="nextStep(2)">Próximo: Captura de Documentos</button>
                </div>

                <!-- Step 2: Captura de Documentos -->
                <div class="v-step" id="v-step-2" style="display:none;">
                    <h4>Passo 2: Fotos do Documento (BI ou Passaporte)</h4>
                    <p>Use a câmera para capturar a frente e o verso do seu documento.</p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="capture-box">
                            <label>Frente do Documento</label>
                            <div id="preview_front" class="media-preview" style="width:100%; height:150px; background:#000; border-radius:8px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                <i class="fa-solid fa-id-card fa-3x" style="color:#333;"></i>
                            </div>
                            <button class="btn btn-sm btn-secondary" onclick="startCapture('front')">Capturar Frente</button>
                        </div>
                        <div class="capture-box">
                            <label>Verso do Documento</label>
                            <div id="preview_back" class="media-preview" style="width:100%; height:150px; background:#000; border-radius:8px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                <i class="fa-solid fa-id-card fa-3x" style="color:#333;"></i>
                            </div>
                            <button class="btn btn-sm btn-secondary" onclick="startCapture('back')">Capturar Verso</button>
                        </div>
                    </div>
                    <div style="margin-top:20px; display:flex; gap:10px;">
                        <button class="btn btn-secondary" onclick="prevStep(1)">Voltar</button>
                        <button class="btn btn-primary" onclick="nextStep(3)">Próximo: Verificação em Vídeo</button>
                    </div>
                </div>

                <!-- Step 3: Vídeo -->
                <div class="v-step" id="v-step-3" style="display:none;">
                    <h4>Passo 3: Vídeo de Segurança (10 segundos)</h4>
                    <p>Instruções: Olhe para a esquerda/direita 2 vezes, cima/baixo 2 vezes e segure o documento ao lado do rosto.</p>

                    <div id="preview_video_container" style="width:100%; max-width:400px; height:300px; background:#000; border-radius:8px; margin:0 auto 20px; overflow:hidden; position:relative;">
                        <video id="video_stream" autoplay muted playsinline webkit-playsinline style="width:100%; height:100%; object-fit:cover;"></video>
                        <video id="video_playback" controls playsinline webkit-playsinline style="width:100%; height:100%; object-fit:cover; display:none;"></video>
                        <div id="recording_indicator" style="position:absolute; top:10px; right:10px; color:red; display:none;">
                            <i class="fa-solid fa-circle"></i> GRAVANDO <span id="timer">10s</span>
                        </div>
                    </div>

                    <div style="text-align:center;">
                        <button id="btn_start_video" class="btn btn-danger" onclick="startVideoRecording()">Iniciar Gravação</button>
                    </div>

                    <div style="margin-top:20px; display:flex; gap:10px;">
                        <button class="btn btn-secondary" onclick="prevStep(2)">Voltar</button>
                        <button id="btn_submit_verification" class="btn btn-success" onclick="submitVerification()" style="display:none;">Enviar para Análise</button>
                    </div>
                </div>
            </div>

            <!-- Camera UI Overlay -->
            <div id="cameraOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:3000; flex-direction:column; align-items:center; justify-content:center;">
                <video id="camera_stream" autoplay playsinline webkit-playsinline style="max-width:90%; max-height:70%; border:2px solid #fff;"></video>
                <canvas id="camera_canvas" style="display:none;"></canvas>
                <div style="margin-top:20px; display:flex; gap:20px;">
                    <button class="btn btn-primary" onclick="takeSnapshot()">Tirar Foto</button>
                    <button class="btn btn-secondary" onclick="closeCamera()">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ============================================
    // SISTEMA DE VERIFICAÇÃO COM SUPORTE A CÂMERA
    // Corrigido para desktop e mobile
    // ============================================

    let currentStream = null;
    let currentCaptureType = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let capturedMedia = {
        front: null,
        back: null,
        video: null
    };
    let videoRecordingStream = null;

    // Verificar suporte do navegador
    function checkBrowserSupport() {
        const hasGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        if (!hasGetUserMedia) {
            console.error('getUserMedia nao eh suportado neste navegador');
            return false;
        }
        const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (!isSecure && window.location.protocol !== 'file:') {
            console.warn('Aviso: Camera requer HTTPS para funcionar corretamente');
        }
        return true;
    }

    // Traduzir erros de câmera
    function translateCameraError(err) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            return 'Permissão de câmera negada. Por favor, verifique as configurações de privacidade do seu navegador e permita o acesso à câmera.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            return 'Nenhuma câmera encontrada. Verifique se seu dispositivo possui câmera conectada.';
        } else if (err.name === 'NotReadableError') {
            return 'Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo.';
        } else if (err.name === 'SecurityError') {
            return 'Erro de segurança. Este site deve ser acessado via HTTPS para usar a câmera.';
        }
        return 'Erro ao acessar a câmera: ' + (err.message || err.name);
    }

    function nextStep(step) {
        document.querySelectorAll('.v-step').forEach(el => el.style.display = 'none');
        document.getElementById('v-step-' + step).style.display = 'block';
    }

    function prevStep(step) {
        nextStep(step);
    }

    async function startCapture(type) {
        if (!checkBrowserSupport()) {
            alert('Seu navegador não suporta acesso à câmera. Por favor, use um navegador moderno como Chrome, Firefox, Safari ou Edge.');
            return;
        }

        currentCaptureType = type;
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Acessando câmera...';

        try {
            // Tentar câmera traseira primeiro (melhor para documentos), depois frontal
            let constraints = {
                video: {
                    facingMode: {
                        ideal: 'environment'
                    },
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                },
                audio: false
            };

            try {
                currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch (err) {
                // Fallback para câmera frontal se traseira não funcionar
                console.log('Câmera traseira não disponível, tentando frontal...');
                constraints.video.facingMode = {
                    ideal: 'user'
                };
                currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            }

            const videoElement = document.getElementById('camera_stream');
            videoElement.srcObject = currentStream;

            // Aguardar que o vídeo esteja pronto
            videoElement.onloadedmetadata = () => {
                videoElement.play().catch(e => console.error('Erro ao reproduzir vídeo:', e));
                document.getElementById('cameraOverlay').style.display = 'flex';
            };

            // Timeout de segurança
            setTimeout(() => {
                if (videoElement.readyState < 2) {
                    closeCamera();
                    alert('Timeout ao acessar a câmera. Tente novamente.');
                    btn.disabled = false;
                    btn.innerHTML = (type === 'front' ? 'Capturar Frente' : 'Capturar Verso');
                }
            }, 5000);

        } catch (err) {
            console.error('Erro ao acessar câmera:', err);
            alert(translateCameraError(err));
            btn.disabled = false;
            btn.innerHTML = (type === 'front' ? 'Capturar Frente' : 'Capturar Verso');
        }
    }

    function takeSnapshot() {
        try {
            const video = document.getElementById('camera_stream');
            const canvas = document.getElementById('camera_canvas');

            if (!video.srcObject) {
                alert('Câmera não está ativa.');
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            if (canvas.width === 0 || canvas.height === 0) {
                alert('Câmera ainda está carregando. Tente novamente.');
                return;
            }

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            const dataUrl = canvas.toBlob('image/jpeg', 0.85);
            capturedMedia[currentCaptureType] = dataUrl;

            const preview = document.getElementById('preview_' + currentCaptureType);
            preview.innerHTML = `<img src="${dataUrl}" style="width:100%; height:100%; object-fit:cover;">`;

            closeCamera();
            alert('Foto capturada com sucesso!');
        } catch (err) {
            console.error('Erro ao capturar foto:', err);
            alert('Erro ao capturar foto: ' + err.message);
        }
    }

    function closeCamera() {
        if (currentStream) {
            currentStream.getTracks().forEach(track => {
                track.stop();
            });
            currentStream = null;
        }
        document.getElementById('cameraOverlay').style.display = 'none';
        document.getElementById('camera_stream').srcObject = null;
    }

    async function startVideoRecording() {
        if (!checkBrowserSupport()) {
            alert('Seu navegador não suporta gravação de vídeo. Por favor, use um navegador moderno.');
            return;
        }

        const btn = document.getElementById('btn_start_video');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Acessando câmera...';

        try {
            // Constraints para gravação de vídeo com áudio
            let constraints = {
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
            } catch (err) {
                // Fallback: tentar sem áudio se houver erro
                console.log('Tentando sem áudio...');
                constraints.audio = false;
                videoRecordingStream = await navigator.mediaDevices.getUserMedia(constraints);
            }

            const videoElement = document.getElementById('video_stream');
            videoElement.srcObject = videoRecordingStream;
            videoElement.style.display = 'block';
            document.getElementById('video_playback').style.display = 'none';

            // Aguardar que o vídeo esteja pronto
            videoElement.onloadedmetadata = () => {
                videoElement.play().catch(e => console.error('Erro ao reproduzir vídeo:', e));
            };

            // Configurar MediaRecorder
            const options = {
                mimeType: 'video/webm;codecs=vp9,opus',
                videoBitsPerSecond: 2500000
            };

            // Fallback para codec alternativo se vp9 não for suportado
            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                options.mimeType = 'video/webm;codecs=vp8,opus';
            }
            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                options.mimeType = 'video/webm';
            }

            mediaRecorder = new MediaRecorder(videoRecordingStream, options);
            recordedChunks = [];

            mediaRecorder.ondataavailable = (e) => {
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
                        document.getElementById('btn_submit_verification').style.display = 'block';
                    };

                    const url = URL.createObjectURL(blob);
                    const playback = document.getElementById('video_playback');
                    playback.src = url;
                    playback.style.display = 'block';
                    videoElement.style.display = 'none';

                    videoRecordingStream.getTracks().forEach(track => track.stop());
                    videoRecordingStream = null;
                } catch (err) {
                    console.error('Erro ao processar gravação:', err);
                    alert('Erro ao processar a gravação: ' + err.message);
                }
            };

            mediaRecorder.onerror = (e) => {
                console.error('Erro no MediaRecorder:', e.error);
                alert('Erro durante a gravação: ' + e.error);
            };

            mediaRecorder.start();
            document.getElementById('recording_indicator').style.display = 'block';
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-stop"></i> Gravando...';

            let timeLeft = 10;
            const timerInterval = setInterval(() => {
                timeLeft--;
                document.getElementById('timer').innerText = timeLeft + 's';
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    mediaRecorder.stop();
                    document.getElementById('recording_indicator').style.display = 'none';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-redo"></i> Gravar Novamente';
                }
            }, 1000);

        } catch (err) {
            console.error('Erro ao gravar vídeo:', err);
            alert(translateCameraError(err));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-video"></i> Iniciar Gravação';
            if (videoRecordingStream) {
                videoRecordingStream.getTracks().forEach(track => track.stop());
                videoRecordingStream = null;
            }
        }
    }

    async function submitVerification() {
        if (!capturedMedia.front || !capturedMedia.back || !capturedMedia.video) {
            alert('Capture todas as mídias obrigatórias antes de enviar.');
            return;
        }

        const fullName = document.getElementById('v_full_name').value.trim();
        const nickname = document.getElementById('v_nickname').value.trim();
        const birthDate = document.getElementById('v_birth_date').value.trim();
        const province = document.getElementById('v_province').value.trim();
        const contact = document.getElementById('v_contact').value.trim();

        if (!fullName || !nickname || !birthDate || !province || !contact) {
            alert('Preencha todos os campos obrigatórios.');
            return;
        }

        const btn = document.getElementById('btn_submit_verification');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        const formData = new FormData();
        formData.append('full_name', fullName);
        formData.append('nickname', nickname);
        formData.append('birth_date', birthDate);
        formData.append('province', province);
        formData.append('contact_phone', contact);
        formData.append('id_front', capturedMedia.front);
        formData.append('id_back', capturedMedia.back);
        formData.append('video', capturedMedia.video);

        try {
            const response = await fetch('process_verification.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Erro HTTP: ' + response.status);
            }

            const result = await response.json();
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
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Enviar para Análise';
            }
        } catch (err) {
            console.error('Erro ao enviar verificação:', err);
            alert('Erro na conexão: ' + (err.message || 'Erro desconhecido'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Enviar para Análise';
        }
    }

    window.addEventListener('beforeunload', () => {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
        }
        if (videoRecordingStream) {
            videoRecordingStream.getTracks().forEach(track => track.stop());
        }
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    });
</script>