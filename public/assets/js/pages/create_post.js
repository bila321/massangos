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

document.addEventListener('DOMContentLoaded', function () {
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
        checkbox.addEventListener('change', function () {
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

    btnNext.addEventListener('click', function () {
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

    btnPrev.addEventListener('click', function () {
        if (currentStep > 1) {
            currentStep--;
            updateWizardUI();
        }
    });

    // ── Submissão Unificada via AJAX (Texto, Foto, Álbum, Vídeo) ──────────────
    document.querySelectorAll('.ajax-post-form').forEach(form => {
        form.addEventListener('submit', function (e) {
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
                    canvas.toBlob(function (blob) {
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
    xhr.upload.onprogress = function (e) {
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

    xhr.onload = function () {
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

    xhr.onerror = function () {
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
            reader.onload = function (e) {
                const item = document.createElement('div');
                item.className = 'album-preview-item' + (index === 0 ? ' is-cover' : '');
                item.onclick = function () {
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

    editorVideo.onloadedmetadata = function () {
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

    document.querySelectorAll('input[name="compress_level"]').forEach(function (radio) {
        radio.onchange = function () {
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
        const onSeeked = function () {
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
        handle.onpointerdown = function (e) {
            e.preventDefault();
            handle.setPointerCapture(e.pointerId);

            const onMove = function (ev) {
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

            const onUp = function () {
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

    strip.onpointerdown = function (e) {
        e.preventDefault();
        strip.setPointerCapture(e.pointerId);
        setThumbTime(clientXToTime(e.clientX));

        const onMove = function (ev) {
            setThumbTime(clientXToTime(ev.clientX));
        };
        const onUp = function () {
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

    const onSeeked = function () {
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