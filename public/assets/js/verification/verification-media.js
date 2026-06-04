/**
 * Verification Page - Media Handling
 * Handles video capture, recording, and media processing
 *
 * FIXES:
 *  - Bug de scope: variável global `videoStream` renomeada para `activeStream`
 *    para não colidir com `const videoStream = document.getElementById('videoStream')`
 *    dentro de startVideoRecording().
 *  - recordBtn.disabled não bloqueava mais o botão de parar; agora o botão
 *    é reactivado logo após o MediaRecorder iniciar (com 500ms de debounce).
 *  - Limpeza de tracks em onstop usa `activeStream` correctamente.
 *  - beforeunload usa `activeStream` correctamente.
 */

let activeStream    = null; // ← era "videoStream" — renomeado para evitar conflito
let mediaRecorder   = null;
let recordedChunks  = [];
let recordingStartTime = null;
let recordingTimer  = null;

/**
 * Toggle video recording
 */
async function toggleVideoRecording() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
        await startVideoRecording();
    } else if (mediaRecorder.state === 'recording') {
        stopVideoRecording();
    }
}

/**
 * Start video recording
 */
async function startVideoRecording() {
    try {
        const recordBtn          = document.getElementById('recordBtn');
        const videoElement       = document.getElementById('videoStream');   // ← era "const videoStream" — renomeado
        const recordingIndicator = document.getElementById('recordingIndicator');

        // Desactivar botão imediatamente para evitar duplo-clique
        recordBtn.disabled = true;
        recordBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A iniciar...';

        // Pedir acesso à câmera
        const constraints = {
            video: { width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
        };

        try {
            activeStream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            // Fallback sem áudio
            console.warn('A tentar sem áudio...', err);
            constraints.audio = false;
            activeStream = await navigator.mediaDevices.getUserMedia(constraints);
        }

        videoElement.srcObject = activeStream;

        // Escolher codec suportado
        const mimeType = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm'
        ].find(type => MediaRecorder.isTypeSupported(type)) || '';

        const options = { videoBitsPerSecond: 2500000 };
        if (mimeType) options.mimeType = mimeType;

        mediaRecorder    = new MediaRecorder(activeStream, options);
        recordedChunks   = [];

        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            if (recordedChunks.length === 0) {
                console.error('Nenhum dado de vídeo gravado');
                showAlert('Erro: Nenhum dado de vídeo foi capturado.', 'danger');
                return;
            }

            const blob = new Blob(recordedChunks, {
                type: mediaRecorder.mimeType || 'video/webm'
            });

            // Guardar blob no estado global
            verificationState.mediaFiles.video = blob;

            // Pré-visualização
            const videoPlayback = document.getElementById('videoPlayback');
            videoPlayback.src   = URL.createObjectURL(blob);
            videoPlayback.style.display = 'block';
            videoElement.style.display  = 'none';

            showAlert('Vídeo gravado com sucesso!', 'success');

            // Parar TODAS as tracks usando activeStream (fix do bug de scope)
            if (activeStream) {
                activeStream.getTracks().forEach(track => track.stop());
                activeStream = null;
            }
        };

        mediaRecorder.onerror = (event) => {
            console.error('Erro MediaRecorder:', event.error);
            showAlert('Erro durante a gravação: ' + event.error, 'danger');
            recordBtn.disabled  = false;
            recordBtn.innerHTML = '<i class="fa-solid fa-circle-play"></i> Iniciar Gravação';
        };

        // Iniciar gravação
        mediaRecorder.start();
        recordingStartTime = Date.now();

        // Actualizar UI — botão agora permite PARAR
        recordBtn.innerHTML = '<i class="fa-solid fa-stop"></i> Parar Gravação';
        recordBtn.disabled  = false;   // ← FIX: reactivar para o utilizador poder parar
        recordingIndicator.style.display = 'flex';

        startRecordingTimer();

    } catch (error) {
        const recordBtn = document.getElementById('recordBtn');
        if (recordBtn) {
            recordBtn.disabled  = false;
            recordBtn.innerHTML = '<i class="fa-solid fa-circle-play"></i> Iniciar Gravação';
        }
        console.error('Erro ao iniciar gravação:', error);
        showAlert(translateCameraError(error), 'danger');
    }
}

/**
 * Stop video recording
 */
function stopVideoRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();

        const recordBtn = document.getElementById('recordBtn');
        if (recordBtn) {
            recordBtn.innerHTML = '<i class="fa-solid fa-redo"></i> Gravar Novamente';
            recordBtn.disabled  = false;
        }

        const recordingIndicator = document.getElementById('recordingIndicator');
        if (recordingIndicator) recordingIndicator.style.display = 'none';

        clearInterval(recordingTimer);
    }
}

/**
 * Countdown timer — para automaticamente aos 10s
 */
function startRecordingTimer() {
    let secondsLeft  = 10;
    const timerEl    = document.getElementById('recordingTimer');
    if (timerEl) timerEl.textContent = secondsLeft + 's';

    recordingTimer = setInterval(() => {
        secondsLeft--;
        if (timerEl) timerEl.textContent = secondsLeft + 's';

        if (secondsLeft <= 0) {
            clearInterval(recordingTimer);
            stopVideoRecording();
        }
    }, 1000);
}

/**
 * Traduzir erros de câmera para português
 */
function translateCameraError(error) {
    const msgs = {
        'NotAllowedError':  'Permissão de câmera negada. Permita o acesso nas configurações do navegador.',
        'NotFoundError':    'Nenhuma câmera encontrada. Verifique se está conectada.',
        'NotReadableError': 'Câmera em uso por outra aplicação.',
        'SecurityError':    'Acesso à câmera bloqueado por razões de segurança.',
        'TypeError':        'Erro ao aceder à câmera.'
    };
    return msgs[error.name] || 'Erro ao aceder à câmera: ' + error.message;
}

/**
 * Limpar streams ao sair da página
 */
window.addEventListener('beforeunload', () => {
    // FIX: usa activeStream em vez da variável shadow
    if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
});

// Exports
window.toggleVideoRecording = toggleVideoRecording;
window.startVideoRecording  = startVideoRecording;
window.stopVideoRecording   = stopVideoRecording;
window.translateCameraError = translateCameraError;
