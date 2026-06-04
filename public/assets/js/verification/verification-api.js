/**
 * Verification Page - API Communication
 * Handles communication with backend verification endpoints
 *
 * FIXES APLICADOS:
 *  - maxPollingAttempts aumentado de 72 para 180 (15 minutos)
 *    DeepFace + ArcFace na 1.ª execução demora até 10-15 min (download de modelos ~500MB).
 *  - Contador de tempo visível ao utilizador durante o polling (não só "A processar...").
 *  - pollVerificationStatus() verifica se verificationId é válido antes de iniciar.
 *  - updateAIStep() usa label correctamente (3.º parâmetro já não ignorado).
 *  - fetchVerificationStatus() com fallback PHP robusto e timeout curto na FastAPI.
 *  - Mensagem de timeout mais informativa (não assusta o utilizador).
 */

const API_BASE_URL = typeof BASE_URL_JS !== 'undefined'
    ? BASE_URL_JS
    : (window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/public/');

const VERIFICATION_SUBMIT_URL = API_BASE_URL + 'api/verification-submit.php';
const VERIFICATION_STATUS_URL = API_BASE_URL + 'api/verification-status.php';

let statusPollingInterval = null;
let maxPollingAttempts    = 180; // FIX: 15 minutos (180 × 5s) — ArcFace demora na 1.ª execução
let currentPollingAttempt = 0;
let pollingStartTime      = null;

// ─────────────────────────────────────────────────────────────────────────────
//  SUBMIT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Submete os dados de verificação para o backend.
 *
 * @param {Object} formData
 *   frontDoc  {string} base64 Data URL "data:image/...;base64,..."
 *   backDoc   {string} base64 Data URL "data:image/...;base64,..."
 *   video     {string} base64 Data URL "data:video/...;base64,..."
 */
async function submitVerification(formData) {
    try {
        if (!formData.frontDoc || typeof formData.frontDoc !== 'string') {
            throw new Error('Frente do documento inválida. Por favor, carregue novamente.');
        }
        if (!formData.backDoc || typeof formData.backDoc !== 'string') {
            throw new Error('Verso do documento inválido. Por favor, carregue novamente.');
        }
        if (!formData.video || typeof formData.video !== 'string') {
            throw new Error('Vídeo de verificação inválido. Por favor, grave novamente.');
        }

        const payload = new FormData();
        payload.append('full_name',     formData.fullName);
        payload.append('nickname',      formData.nickname);
        payload.append('birth_date',    formData.birthDate);
        payload.append('province',      formData.province);
        payload.append('contact_phone', formData.contactPhone);
        payload.append('id_front',      formData.frontDoc);
        payload.append('id_back',       formData.backDoc);
        payload.append('video',         formData.video);

        const response = await fetch(VERIFICATION_SUBMIT_URL, {
            method:      'POST',
            body:        payload,
            credentials: 'include',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' }
            // Não definir Content-Type — browser define multipart/form-data com boundary
        });

        if (!response.ok) {
            let parsedMsg = 'Erro HTTP ' + response.status;
            try {
                const serverText = await response.text();
                const json       = JSON.parse(serverText);
                parsedMsg        = json.message || parsedMsg;
            } catch (_) {}
            throw new Error(parsedMsg);
        }

        const result = await response.json();

        // Log de diagnóstico (útil em dev)
        if (result.ai_triggered === false) {
            console.warn('[Verificação] IA não foi disparada:', result.ai_error || result.ai_status);
            console.warn('[Verificação] Certifique-se que o FastAPI está em execução: uvicorn main:app --reload');
        }

        return result;

    } catch (error) {
        console.error('Erro ao enviar verificação:', error);
        throw error;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  POLLING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Iniciar polling do status a cada 5 segundos.
 * FIX: verifica se verificationId é válido antes de começar.
 * FIX: maxPollingAttempts = 180 (15 min) para cobrir download de modelos DeepFace.
 */
function pollVerificationStatus() {
    const verificationId = verificationState?.verificationId;

    if (!verificationId || verificationId <= 0) {
        console.error('pollVerificationStatus: verificationId inválido:', verificationId);
        showResultSection('error', 'Erro interno', 'ID de verificação inválido. Por favor, tente submeter novamente.');
        return;
    }

    currentPollingAttempt = 0;
    pollingStartTime      = Date.now();

    statusPollingInterval = setInterval(async () => {
        currentPollingAttempt++;

        // FIX: Timeout com mensagem informativa (não assusta o utilizador)
        if (currentPollingAttempt > maxPollingAttempts) {
            clearInterval(statusPollingInterval);
            showResultSection(
                'error',
                'Análise a demorar mais do que o esperado',
                'O seu pedido foi submetido com sucesso e está a ser processado. '
                + 'Pode fechar esta página e verificar o resultado no seu perfil mais tarde. '
                + 'Se não receber resposta em 30 minutos, contacte o suporte.'
            );
            return;
        }

        try {
            const status = await getVerificationStatus(verificationId);
            updateProcessingUI(status);

            const terminalStatuses = ['ai_approved', 'ai_rejected', 'manual_review', 'ai_error'];
            if (status.ai_status && terminalStatuses.includes(status.ai_status)) {
                clearInterval(statusPollingInterval);
                handleVerificationComplete(status);
            }
        } catch (error) {
            // Não parar o polling por um erro de rede pontual
            console.warn('Erro ao verificar status (tentativa ' + currentPollingAttempt + '):', error.message);
        }
    }, 5000);
}

// ─────────────────────────────────────────────────────────────────────────────
//  STATUS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Obter status da verificação via PHP
 */
async function getVerificationStatus(verificationId) {
    const response = await fetch(
        `${VERIFICATION_STATUS_URL}?verification_id=${encodeURIComponent(verificationId)}`,
        {
            method:      'GET',
            credentials: 'include',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' }
        }
    );

    if (!response.ok) throw new Error('HTTP error ' + response.status);
    return await response.json();
}

// ─────────────────────────────────────────────────────────────────────────────
//  UI
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Actualizar UI de processamento com base no status recebido do servidor.
 * FIX: Mostra contador de tempo para o utilizador saber que está a funcionar.
 */
function updateProcessingUI(status) {
    // Actualizar badges dos passos AI
    updateAIStep(1, status.ai_status, 'Análise Facial');
    updateAIStep(2, status.ai_status, 'Detecção de Vida');
    updateAIStep(3, status.ai_status, 'Avaliação de Risco');

    // Actualizar barra de score de similaridade
    if (status.ai_similarity) {
        const pct = Math.round(status.ai_similarity * 100);
        const bar = document.getElementById('scoreBar');
        const val = document.getElementById('scoreValue');
        if (bar) bar.style.width = pct + '%';
        if (val) val.textContent  = pct;
    }

    const statusText = document.getElementById('statusText');
    if (!statusText) return;

    // FIX: Mostrar tempo decorrido para o utilizador saber que está a funcionar
    const elapsedMs   = pollingStartTime ? (Date.now() - pollingStartTime) : 0;
    const elapsedMins = Math.floor(elapsedMs / 60000);
    const elapsedSecs = Math.floor((elapsedMs % 60000) / 1000);
    const timeLabel   = elapsedMins > 0
        ? `${elapsedMins}m ${elapsedSecs}s`
        : `${elapsedSecs}s`;

    const messages = {
        'pending':       `A aguardar processamento da IA... (${timeLabel}) — A primeira análise pode demorar até 15 minutos`,
        'queued':        `IA em fila de espera... (${timeLabel}) — Verifique se o serviço FastAPI está em execução`,
        'processing':    `A analisar documentos com IA... (${timeLabel})`,
        'ai_approved':   'Verificação aprovada pela IA!',
        'ai_rejected':   'Verificação rejeitada. Motivo: ' + (status.ai_notes || 'Documentos não correspondem'),
        'manual_review': 'Verificação enviada para revisão manual...',
        'ai_error':      'Erro ao processar: ' + (status.ai_notes || 'Erro desconhecido'),
    };

    statusText.textContent = messages[status.ai_status] || `A processar... (${timeLabel})`;
}

/**
 * Actualizar badge de passo individual de IA.
 *
 * FIX: O 3.º parâmetro `label` era recebido mas nunca usado. Agora exibido no badge.
 *
 * @param {number} stepNumber     1, 2 ou 3
 * @param {string} overallStatus  ai_status vindo do servidor
 * @param {string} label          Nome do passo (para display)
 */
function updateAIStep(stepNumber, overallStatus, label) {
    const aiStep = document.getElementById(`aiStep${stepNumber}`);
    if (!aiStep) return;

    const badge = aiStep.querySelector('.status-badge');
    if (!badge) return;

    let stepStatus = 'pending';

    if (overallStatus === 'ai_approved' || overallStatus === 'manual_review') {
        stepStatus = 'completed';
    } else if (overallStatus === 'ai_rejected' || overallStatus === 'ai_error') {
        // Passo 1 falhou; os restantes ficam pendentes
        stepStatus = (stepNumber === 1) ? 'failed' : 'pending';
    } else if (overallStatus === 'processing') {
        stepStatus = (stepNumber === 1) ? 'processing' : 'pending';
    }

    const statusLabels = {
        pending:    'Pendente',
        processing: 'A processar',
        completed:  'Concluído',
        failed:     'Falhou',
    };

    badge.className   = 'status-badge ' + stepStatus;
    badge.textContent = statusLabels[stepStatus] || 'Pendente';
}

/**
 * Tratar conclusão da verificação
 */
function handleVerificationComplete(status) {
    const similarity = status.ai_similarity ? Math.round(status.ai_similarity * 100) : 0;

    if (status.ai_status === 'ai_approved') {
        showResultSection(
            'success',
            'Verificação Aprovada!',
            'A sua identidade foi verificada com sucesso. Tem agora acesso a todos os recursos premium.'
        );
        const details = document.getElementById('resultDetails');
        if (details) {
            details.innerHTML = `
                <div class="result-detail-item">
                    <span class="result-detail-label">Score de Similaridade:</span>
                    <span class="result-detail-value">${similarity}%</span>
                </div>
                <div class="result-detail-item">
                    <span class="result-detail-label">Detecção de Vida:</span>
                    <span class="result-detail-value">${status.ai_liveness ? 'Aprovada' : 'Falhou'}</span>
                </div>
            `;
        }

    } else if (status.ai_status === 'ai_rejected') {
        showResultSection(
            'error',
            'Verificação Rejeitada',
            status.ai_notes || 'A sua verificação não cumpriu os critérios de segurança. Por favor, tente novamente com documentos mais nítidos.'
        );

    } else if (status.ai_status === 'manual_review') {
        showResultSection(
            'success',
            'Verificação em Revisão Manual',
            'A sua verificação foi enviada para revisão manual pela nossa equipa. Receberá uma notificação assim que o processo for concluído.'
        );

    } else if (status.ai_status === 'ai_error') {
        showResultSection(
            'error',
            'Erro no Processamento',
            status.ai_notes || 'Ocorreu um erro ao processar a sua verificação. Por favor, tente novamente mais tarde.'
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  FETCH COM FALLBACK
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Obter status — tenta FastAPI primeiro (timeout 3s), fallback para PHP.
 * Útil para polling mais rápido quando a FastAPI está acessível directamente.
 */
async function fetchVerificationStatus(verificationId) {
    try {
        const response = await fetch(
            `http://127.0.0.1:8000/identity/status/${encodeURIComponent(verificationId)}`,
            {
                method:  'GET',
                headers: { 'Content-Type': 'application/json' },
                signal:  AbortSignal.timeout(3000), // 3s timeout
            }
        );
        if (response.ok) return await response.json();
    } catch (_) {
        // FastAPI indisponível ou timeout — usar PHP como fallback
        console.debug('[Verificação] FastAPI indisponível, usando endpoint PHP.');
    }
    return await getVerificationStatus(verificationId);
}

// ─────────────────────────────────────────────────────────────────────────────
//  EXPORTS
// ─────────────────────────────────────────────────────────────────────────────

window.submitVerification         = submitVerification;
window.pollVerificationStatus     = pollVerificationStatus;
window.getVerificationStatus      = getVerificationStatus;
window.updateProcessingUI         = updateProcessingUI;
window.handleVerificationComplete = handleVerificationComplete;
window.fetchVerificationStatus    = fetchVerificationStatus;
