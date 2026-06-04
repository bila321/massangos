/**
 * Verification Page - Main JavaScript
 * Handles form navigation, validation, and step management
 *
 * File flow (matches verification-submit.php expectations):
 *   - id_front / id_back  → $_POST base64 Data URL  (images stored via FileReader)
 *   - video               → $_POST base64 Data URL  (Blob converted at submit time)
 */

function goToStep(stepNumber) {
    console.log("Navegando para o passo:", stepNumber);
    if (stepNumber < 1 || stepNumber > verificationState.totalSteps) return;

    if (stepNumber > verificationState.currentStep) {
        if (!validateCurrentStep()) return;
    }

    const currentStepElement = document.querySelector(`.verification-step[data-step="${verificationState.currentStep}"]`);
    if (currentStepElement) currentStepElement.classList.remove('active');

    const newStepElement = document.querySelector(`.verification-step[data-step="${stepNumber}"]`);
    if (newStepElement) newStepElement.classList.add('active');

    updateProgressIndicator(stepNumber);
    if (stepNumber === 4) updateReviewSection();

    verificationState.currentStep = stepNumber;
    saveFormData();

    const wrapper = document.querySelector('.verification-form-wrapper');
    if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

window.goToStep = goToStep;

// ─── Global state ────────────────────────────────────────────────────────────
const verificationState = {
    currentStep: 1,
    totalSteps: 4,
    formData: {
        fullName: '', nickname: '', birthDate: '',
        province: '', contactPhone: '',
        frontDoc: null, backDoc: null, video: null
    },
    verificationId: null,
    isProcessing: false,
    mediaFiles: {
        // Sent to server as $_POST base64 strings
        frontDoc: null,  // "data:image/jpeg;base64,..."
        backDoc:  null,  // "data:image/jpeg;base64,..."
        // Raw Blob — converted to base64 only at submit time
        video: null,
        // Alias used by updateReviewSection() for clarity
        frontDocPreview: null,
        backDocPreview:  null
    }
};

// ─── Initialisation ──────────────────────────────────────────────────────────
function initializeVerificationPage() {
    setupEventListeners();
    setupDragAndDrop();
    setupFormValidation();
    loadSavedFormData();
}

function setupEventListeners() {
    document.getElementById('frontDocInput')?.addEventListener('change', (e) => handleDocumentUpload(e, 'front'));
    document.getElementById('backDocInput')?.addEventListener('change',  (e) => handleDocumentUpload(e, 'back'));
    document.getElementById('verificationForm')?.addEventListener('submit', handleFormSubmit);

    ['fullName', 'nickname', 'birthDate', 'province', 'contactPhone'].forEach(fieldId => {
        document.getElementById(fieldId)?.addEventListener('change', saveFormData);
        document.getElementById(fieldId)?.addEventListener('input',  saveFormData);
    });
}

function setupDragAndDrop() {
    document.querySelectorAll('.document-preview-area').forEach(zone => {
        zone.addEventListener('dragover',  (e) => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (!files.length) return;
            const docType   = zone.id.includes('front') ? 'front' : 'back';
            const fileInput = document.getElementById(docType === 'front' ? 'frontDocInput' : 'backDocInput');
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        zone.addEventListener('click', () => {
            const docType   = zone.id.includes('front') ? 'front' : 'back';
            const fileInput = document.getElementById(docType === 'front' ? 'frontDocInput' : 'backDocInput');
            fileInput.click();
        });
    });
}

function setupFormValidation() {
    const form = document.getElementById('verificationForm');
    if (!form) return;
    form.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('blur', () => validateField(input));
    });
}

// ─── Validation ──────────────────────────────────────────────────────────────
function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';

    switch (field.id) {
        case 'fullName':
            if (!value)            { isValid = false; errorMessage = 'Nome completo é obrigatório'; }
            else if (value.length < 5) { isValid = false; errorMessage = 'Nome deve ter pelo menos 5 caracteres'; }
            break;
        case 'nickname':
            if (!value)            { isValid = false; errorMessage = 'Apelido é obrigatório'; }
            else if (value.length < 3) { isValid = false; errorMessage = 'Apelido deve ter pelo menos 3 caracteres'; }
            break;
        case 'birthDate':
            if (!value) { isValid = false; errorMessage = 'Data de nascimento é obrigatória'; }
            else {
                const age = new Date().getFullYear() - new Date(value).getFullYear();
                if (age < 18) { isValid = false; errorMessage = 'Você deve ter pelo menos 18 anos'; }
            }
            break;
        case 'province':
            if (!value) { isValid = false; errorMessage = 'Província é obrigatória'; }
            break;
        case 'contactPhone':
            if (!value) { isValid = false; errorMessage = 'Contacto é obrigatório'; }
            else if (!/^(\+258|0)[0-9]{8,9}$/.test(value.replace(/\s/g, ''))) {
                isValid = false; errorMessage = 'Número de telefone inválido';
            }
            break;
    }

    if (!isValid) { field.classList.add('invalid');    showFieldError(field, errorMessage); }
    else          { field.classList.remove('invalid'); clearFieldError(field); }
    return isValid;
}

function showFieldError(field, message) {
    let el = field.parentElement.querySelector('.field-error');
    if (!el) {
        el = document.createElement('small');
        el.className = 'field-error';
        field.parentElement.appendChild(el);
    }
    el.textContent = message;
    el.style.color = 'var(--danger)';
    el.style.display = 'block';
}

function clearFieldError(field) {
    const el = field.parentElement.querySelector('.field-error');
    if (el) el.style.display = 'none';
}

function validateCurrentStep() {
    switch (verificationState.currentStep) {
        case 1: {
            let allValid = true;
            ['fullName', 'nickname', 'birthDate', 'province', 'contactPhone'].forEach(id => {
                const f = document.getElementById(id);
                if (f && !validateField(f)) allValid = false;
            });
            if (!allValid) showAlert('Por favor, preencha todos os campos corretamente.', 'danger');
            return allValid;
        }
        case 2:
            if (!verificationState.mediaFiles.frontDoc || !verificationState.mediaFiles.backDoc) {
                showAlert('Por favor, carregue ambas as imagens do documento.', 'danger');
                return false;
            }
            return true;
        case 3:
            if (!verificationState.mediaFiles.video) {
                showAlert('Por favor, grave o vídeo de verificação.', 'danger');
                return false;
            }
            return true;
        default:
            return true;
    }
}

// ─── File handling ───────────────────────────────────────────────────────────

/**
 * Handle document upload.
 *
 * The PHP endpoint reads files from $_POST as base64 strings, so we store
 * the Data URL (e.g. "data:image/jpeg;base64,...") directly in mediaFiles.
 * The same value doubles as the preview src for <img> tags.
 */
function handleDocumentUpload(event, docType) {
    const file = event.target.files[0];
    if (!file) return;

    if (!validateUploadFile(file, 'image')) {
        showAlert('Arquivo inválido. Selecione uma imagem JPEG ou PNG.', 'danger');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const dataUrl = e.target.result; // "data:image/jpeg;base64,..."

        if (docType === 'front') {
            verificationState.mediaFiles.frontDoc        = dataUrl;
            verificationState.mediaFiles.frontDocPreview = dataUrl;
        } else {
            verificationState.mediaFiles.backDoc        = dataUrl;
            verificationState.mediaFiles.backDocPreview = dataUrl;
        }

        const previewId     = docType === 'front' ? 'frontPreview'     : 'backPreview';
        const previewAreaId = docType === 'front' ? 'frontPreviewArea' : 'backPreviewArea';
        const preview       = document.getElementById(previewId);
        const previewArea   = document.getElementById(previewAreaId);

        if (preview)     { preview.src = dataUrl; preview.style.display = 'block'; }
        if (previewArea) {
            previewArea.classList.add('has-file');
            const ph = previewArea.querySelector('.upload-placeholder');
            if (ph) ph.style.display = 'none';
        }

        showAlert(`${docType === 'front' ? 'Frente' : 'Verso'} do documento carregado com sucesso!`, 'success');
    };

    reader.readAsDataURL(file);
}

function validateUploadFile(file, type) {
    if (file.size > 10 * 1024 * 1024) {
        showAlert('Arquivo muito grande. Máximo 10MB.', 'danger');
        return false;
    }
    if (type === 'image' && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return false;
    if (type === 'video' && !['video/webm', 'video/mp4', 'video/ogg'].includes(file.type))  return false;
    return true;
}

// ─── Progress & Review ───────────────────────────────────────────────────────
function updateProgressIndicator(stepNumber) {
    for (let i = 1; i < stepNumber; i++) {
        const el = document.querySelector(`.progress-step[data-step="${i}"]`);
        if (el) { el.classList.add('completed'); el.classList.remove('active'); }
    }
    const cur = document.querySelector(`.progress-step[data-step="${stepNumber}"]`);
    if (cur) { cur.classList.add('active'); cur.classList.remove('completed'); }
    for (let i = stepNumber + 1; i <= verificationState.totalSteps; i++) {
        const el = document.querySelector(`.progress-step[data-step="${i}"]`);
        if (el) el.classList.remove('active', 'completed');
    }
}

function updateReviewSection() {
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '-'; };
    setText('reviewFullName',  document.getElementById('fullName')?.value);
    setText('reviewNickname',  document.getElementById('nickname')?.value);
    setText('reviewBirthDate', document.getElementById('birthDate')?.value);
    setText('reviewProvince',  document.getElementById('province')?.value);
    setText('reviewContact',   document.getElementById('contactPhone')?.value);

    if (verificationState.mediaFiles.frontDocPreview) {
        const img = document.getElementById('reviewFrontImg');
        if (img) { img.src = verificationState.mediaFiles.frontDocPreview; img.style.display = 'block'; }
        const ph = document.getElementById('frontPlaceholder');
        if (ph) ph.style.display = 'none';
    }

    if (verificationState.mediaFiles.backDocPreview) {
        const img = document.getElementById('reviewBackImg');
        if (img) { img.src = verificationState.mediaFiles.backDocPreview; img.style.display = 'block'; }
        const ph = document.getElementById('backPlaceholder');
        if (ph) ph.style.display = 'none';
    }

    if (verificationState.mediaFiles.video instanceof Blob) {
        const reviewVideo = document.getElementById('reviewVideo');
        if (reviewVideo) {
            reviewVideo.src = URL.createObjectURL(verificationState.mediaFiles.video);
            reviewVideo.style.display = 'block';
        }
        const ph = document.getElementById('videoPlaceholder');
        if (ph) ph.style.display = 'none';
    }
}

// ─── Form submission ─────────────────────────────────────────────────────────

/**
 * Handle form submission.
 *
 * Images are already base64 Data URLs in mediaFiles.
 * The video Blob is converted to base64 here so all three reach PHP via $_POST.
 */
async function handleFormSubmit(event) {
    event.preventDefault();
    if (!validateCurrentStep()) return;

    showProcessingSection();

    try {
        let videoBase64 = null;
        if (verificationState.mediaFiles.video instanceof Blob) {
            try {
                videoBase64 = await blobToBase64(verificationState.mediaFiles.video);
            } catch (err) {
                console.error('Error converting video blob to base64:', err);
                showResultSection('error', 'Erro no processamento', 'Não foi possível processar o vídeo gravado. Tente gravar novamente.');
                return;
            }
        }

        if (!videoBase64 || videoBase64.length < 100) {
            showResultSection('error', 'Vídeo inválido', 'O vídeo gravado parece estar vazio ou corrompido. Por favor, grave novamente.');
            return;
        }

        const formData = {
            fullName:     document.getElementById('fullName').value,
            nickname:     document.getElementById('nickname').value,
            birthDate:    document.getElementById('birthDate').value,
            province:     document.getElementById('province').value,
            contactPhone: document.getElementById('contactPhone').value,
            frontDoc: verificationState.mediaFiles.frontDoc,  // base64 Data URL
            backDoc:  verificationState.mediaFiles.backDoc,   // base64 Data URL
            video:    videoBase64,                            // base64 Data URL
        };

        const result = await submitVerification(formData);

        if (result.success) {
            verificationState.verificationId = result.verification_id;
            pollVerificationStatus();
        } else {
            showResultSection('error', 'Erro ao enviar verificação', result.message || 'Ocorreu um erro desconhecido.');
        }
    } catch (error) {
        console.error('Erro ao enviar verificação:', error);
        showResultSection('error', 'Erro na conexão', 'Não foi possível enviar sua verificação. Tente novamente.');
    }
}

// ─── Persistence ─────────────────────────────────────────────────────────────
function saveFormData() {
    localStorage.setItem('verificationFormData', JSON.stringify({
        fullName:     document.getElementById('fullName')?.value     || '',
        nickname:     document.getElementById('nickname')?.value     || '',
        birthDate:    document.getElementById('birthDate')?.value    || '',
        province:     document.getElementById('province')?.value     || '',
        contactPhone: document.getElementById('contactPhone')?.value || '',
        currentStep:  verificationState.currentStep
    }));
}

function loadSavedFormData() {
    const saved = localStorage.getItem('verificationFormData');
    if (!saved) return;
    try {
        const data = JSON.parse(saved);
        ['fullName', 'nickname', 'birthDate', 'province', 'contactPhone'].forEach(id => {
            const el = document.getElementById(id);
            if (el && data[id]) el.value = data[id];
        });
        if (data.currentStep) verificationState.currentStep = data.currentStep;
    } catch (e) {
        console.error('Error loading saved form data:', e);
    }
}

// ─── UI helpers ──────────────────────────────────────────────────────────────
function showProcessingSection() {
    const form = document.getElementById('verificationForm');
    if (form) form.style.display = 'none';
    const proc = document.getElementById('processingSection');
    if (proc) proc.style.display = 'block';
    document.querySelector('.verification-form-wrapper')?.scrollIntoView({ behavior: 'smooth' });
}

function showResultSection(type, title, message) {
    const proc = document.getElementById('processingSection');
    if (proc) proc.style.display = 'none';
    const result = document.getElementById('resultSection');
    if (result) result.style.display = 'block';

    const icon = document.getElementById('resultIcon');
    if (icon) {
        icon.innerHTML  = type === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>';
        icon.className  = 'result-icon ' + type;
    }
    const titleEl = document.getElementById('resultTitle');
    const msgEl   = document.getElementById('resultMessage');
    if (titleEl) titleEl.textContent = title;
    if (msgEl)   msgEl.textContent   = message;

    document.querySelector('.verification-form-wrapper')?.scrollIntoView({ behavior: 'smooth' });
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.onerror   = reject;
        reader.readAsDataURL(blob);
    });
}

function showAlert(message, type = 'info') {
    const container = document.querySelector('.alert-container') || createAlertContainer();
    const el = document.createElement('div');
    el.className = 'alert';
    Object.assign(el.style, {
        background: type === 'danger' ? 'var(--danger)' : (type === 'success' ? 'var(--success)' : 'var(--info)'),
        color: 'white', padding: '14px 24px', borderRadius: 'var(--radius-md)',
        boxShadow: 'var(--shadow-lg)', display: 'flex', alignItems: 'center',
        gap: '12px', minWidth: '320px', animation: 'slideIn 0.3s cubic-bezier(0.4,0,0.2,1)'
    });
    el.innerHTML = `
        <span style="font-weight:500;font-size:0.95rem;">${message}</span>
        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;font-size:1.5rem;line-height:1;">&times;</button>
    `;
    container.appendChild(el);
    setTimeout(() => {
        Object.assign(el.style, { opacity: '0', transform: 'translateX(20px)', transition: 'all 0.5s ease' });
        setTimeout(() => el.remove(), 500);
    }, 5000);
}

function createAlertContainer() {
    const c = document.createElement('div');
    c.className = 'alert-container';
    Object.assign(c.style, { position: 'fixed', top: '24px', right: '24px', zIndex: '9999', display: 'flex', flexDirection: 'column', gap: '12px' });
    document.body.appendChild(c);
    return c;
}

// ─── Exports ─────────────────────────────────────────────────────────────────
window.handleDocumentUpload       = handleDocumentUpload;
window.handleFormSubmit           = handleFormSubmit;
window.initializeVerificationPage = initializeVerificationPage;
window.showAlert                  = showAlert;
window.showProcessingSection      = showProcessingSection;
window.showResultSection          = showResultSection;
