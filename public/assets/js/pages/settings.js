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