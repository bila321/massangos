<?php

/**
 * public/verification/index.php
 * 
 * Dedicated Identity Verification Page
 * Modern, premium, and professional UI for identity verification
 * Integrates with FastAPI AI pipeline (DeepFace, ArcFace, OpenCV, Liveness Detection)
 */ define('SECURE_ACCESS', true);
define('IS_VERIFICATION_PAGE', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar a verificação de identidade.", "danger");
    redirect(BASE_URL . 'login.php');
}

$user_id = get_current_user_id();

// Fetch user's current verification status
$stmt = $pdo->prepare("SELECT verification_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$verification_status = $user_data['verification_status'] ?? 'none';

// Fetch latest verification record if exists
$stmt_latest = $pdo->prepare("
    SELECT id, status, ai_status, ai_similarity, ai_liveness, ai_notes, admin_notes, created_at 
    FROM user_verifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt_latest->execute([$user_id]);
$latest_verification = $stmt_latest->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Identidade | massangos</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Massango Base Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modals-unified.css">

    <!-- Verification Page Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/verification/verification.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/verification/verification-responsive.css">

    <!-- Permissions Policy for Camera & Microphone -->
    <meta http-equiv="Permissions-Policy" content="camera=(self), microphone=(self)">

    <!-- CropperJS for Image Editing (if needed) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
</head>


<script>
    const BASE_URL_JS = '<?= BASE_URL ?>';
</script>
<main class="main-verification verification-main">
    <div class="verification-container">
        <!-- Hero Section -->
        <section class="verification-hero">
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fa-solid fa-shield-check"></i>
                </div>
                <h1 class="hero-title">Verificação de Identidade</h1>
                <p class="hero-subtitle">
                    Confirme sua identidade para desbloquear recursos premium e começar a vender conteúdo na plataforma.
                </p>
                <div class="hero-benefits">
                    <div class="benefit-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Acesso a recursos premium</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Venda de conteúdo exclusivo</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Receba pagamentos com segurança</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Progress Indicator -->
        <section class="verification-progress">
            <div class="progress-steps">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Dados Pessoais</div>
                </div>
                <div class="progress-connector"></div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Documentos</div>
                </div>
                <div class="progress-connector"></div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Vídeo</div>
                </div>
                <div class="progress-connector"></div>
                <div class="progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Revisão</div>
                </div>
            </div>
        </section>

        <!-- Verification Status Alert (if applicable) -->
        <?php if ($verification_status === 'pending'): ?>
            <section class="verification-status-alert pending">
                <div class="status-icon">
                    <i class="fa-solid fa-hourglass-end"></i>
                </div>
                <div class="status-content">
                    <h3>Verificação em Análise</h3>
                    <p>Seus documentos estão sendo revisados pela nossa equipe. Você receberá uma notificação assim que o processo for concluído.</p>
                </div>
            </section>
        <?php elseif ($verification_status === 'approved'): ?>
            <section class="verification-status-alert approved">
                <div class="status-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="status-content">
                    <h3>Verificação Aprovada</h3>
                    <p>Parabéns! Sua identidade foi verificada com sucesso. Você agora tem acesso a todos os recursos premium.</p>
                </div>
            </section>
        <?php elseif ($verification_status === 'rejected'): ?>
            <section class="verification-status-alert rejected">
                <div class="status-icon">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <div class="status-content">
                    <h3>Verificação Rejeitada</h3>
                    <p>
                        <?php if ($latest_verification && $latest_verification['admin_notes']): ?>
                            <?= htmlspecialchars($latest_verification['admin_notes']) ?>
                        <?php else: ?>
                            Sua verificação anterior foi rejeitada. Por favor, tente novamente com documentos mais claros.
                        <?php endif; ?>
                    </p>
                </div>
            </section>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="verification-form-wrapper">
            <form id="verificationForm" class="verification-form">
                <!-- Step 1: Personal Information -->
                <div class="verification-step active" data-step="1">
                    <div class="step-content">
                        <h2 class="step-title">
                            <i class="fa-solid fa-user"></i>
                            Dados Pessoais
                        </h2>
                        <p class="step-description">Preencha suas informações pessoais conforme consta no seu documento de identificação.</p>

                        <div class="form-group">
                            <label for="fullName">Nome Completo *</label>
                            <input
                                type="text"
                                id="fullName"
                                name="fullName"
                                class="form-input"
                                placeholder="Como consta no documento"
                                required>
                            <small class="form-hint">Deve corresponder exatamente ao documento</small>
                        </div>

                        <div class="form-group">
                            <label for="nickname">Apelido/Nome de Usuário *</label>
                            <input
                                type="text"
                                id="nickname"
                                name="nickname"
                                class="form-input"
                                placeholder="Como deseja ser conhecido"
                                required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthDate">Data de Nascimento *</label>
                                <input
                                    type="date"
                                    id="birthDate"
                                    name="birthDate"
                                    class="form-input"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="province">Província *</label>
                                <select id="province" name="province" class="form-input" required>
                                    <option value="">Selecione sua província</option>
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
                        </div>

                        <div class="form-group">
                            <label for="contactPhone">Contacto (WhatsApp/Telemóvel) *</label>
                            <input
                                type="tel"
                                id="contactPhone"
                                name="contactPhone"
                                class="form-input"
                                placeholder="+258..."
                                required>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(2)">Próximo: Documentos</button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Document Upload -->
                <div class="verification-step" data-step="2">
                    <div class="step-content">
                        <h2 class="step-title">
                            <i class="fa-solid fa-id-card"></i>
                            Documentos de Identificação
                        </h2>
                        <p class="step-description">Carregue fotos claras da frente e verso do seu documento de identificação (BI ou Passaporte).</p>

                        <div class="document-upload-grid">
                            <!-- Front ID -->
                            <div class="document-upload-card">
                                <div class="document-label">
                                    <i class="fa-solid fa-id-card"></i>
                                    Frente do Documento
                                </div>
                                <div class="document-preview-area" id="frontPreviewArea">
                                    <div class="upload-placeholder">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <p>Arraste a imagem aqui</p>
                                        <small>ou clique para selecionar</small>
                                    </div>
                                    <img id="frontPreview" class="document-preview-img" style="display: none;">
                                </div>
                                <input
                                    type="file"
                                    id="frontDocInput"
                                    name="frontDoc"
                                    class="file-input"
                                    accept="image/*"
                                    style="display: none;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('frontDocInput').click()">
                                    <i class="fa-solid fa-upload"></i> Selecionar Imagem
                                </button>
                                <div class="upload-progress" id="frontProgress" style="display: none;">
                                    <div class="progress-bar"></div>
                                    <span class="progress-text">0%</span>
                                </div>
                            </div>

                            <!-- Back ID -->
                            <div class="document-upload-card">
                                <div class="document-label">
                                    <i class="fa-solid fa-id-card"></i>
                                    Verso do Documento
                                </div>
                                <div class="document-preview-area" id="backPreviewArea">
                                    <div class="upload-placeholder">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <p>Arraste a imagem aqui</p>
                                        <small>ou clique para selecionar</small>
                                    </div>
                                    <img id="backPreview" class="document-preview-img" style="display: none;">
                                </div>
                                <input
                                    type="file"
                                    id="backDocInput"
                                    name="backDoc"
                                    class="file-input"
                                    accept="image/*"
                                    style="display: none;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('backDocInput').click()">
                                    <i class="fa-solid fa-upload"></i> Selecionar Imagem
                                </button>
                                <div class="upload-progress" id="backProgress" style="display: none;">
                                    <div class="progress-bar"></div>
                                    <span class="progress-text">0%</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            Certifique-se de que o documento está bem iluminado, legível e completamente visível.
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Voltar</button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(3)">Próximo: Vídeo</button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Video Capture (Liveness Detection) -->
                <div class="verification-step" data-step="3">
                    <div class="step-content">
                        <h2 class="step-title">
                            <i class="fa-solid fa-video"></i>
                            Prova de Vida (Vídeo)
                        </h2>
                        <p class="step-description">Grave um vídeo curto (10 segundos) para confirmar que você é a pessoa no documento.</p>

                        <div class="video-capture-container">
                            <div class="video-preview-area" id="videoPreviewArea">
                                <video id="videoStream" autoplay muted playsinline webkit-playsinline></video>
                                <video id="videoPlayback" controls style="display: none;"></video>
                            </div>

                            <div class="video-instructions">
                                <h4>Instruções:</h4>
                                <ul>
                                    <li>Olhe para a esquerda, depois para a direita</li>
                                    <li>Olhe para cima, depois para baixo</li>
                                    <li>Segure o documento ao lado do seu rosto</li>
                                    <li>Certifique-se de que está bem iluminado</li>
                                </ul>
                            </div>

                            <div class="video-controls">
                                <button type="button" class="btn btn-primary btn-lg" id="recordBtn" onclick="toggleVideoRecording()">
                                    <i class="fa-solid fa-circle-play"></i> Iniciar Gravação
                                </button>
                                <div class="recording-indicator" id="recordingIndicator" style="display: none;">
                                    <span class="recording-dot"></span>
                                    <span>Gravando...</span>
                                    <span class="timer" id="recordingTimer">10s</span>
                                </div>
                            </div>

                            <div class="upload-progress" id="videoProgress" style="display: none;">
                                <div class="progress-bar"></div>
                                <span class="progress-text">0%</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(2)">Voltar</button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(4)">Próximo: Revisão</button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Review & Submit -->
                <div class="verification-step" data-step="4">
                    <div class="step-content">
                        <h2 class="step-title">
                            <i class="fa-solid fa-clipboard-check"></i>
                            Revisão Final
                        </h2>
                        <p class="step-description">Verifique se todos os dados estão corretos antes de enviar.</p>

                        <div class="review-section">
                            <h4 class="review-title">Dados Pessoais</h4>
                            <div class="review-grid">
                                <div class="review-item">
                                    <label>Nome Completo</label>
                                    <span id="reviewFullName">-</span>
                                </div>
                                <div class="review-item">
                                    <label>Apelido</label>
                                    <span id="reviewNickname">-</span>
                                </div>
                                <div class="review-item">
                                    <label>Data de Nascimento</label>
                                    <span id="reviewBirthDate">-</span>
                                </div>
                                <div class="review-item">
                                    <label>Província</label>
                                    <span id="reviewProvince">-</span>
                                </div>
                                <div class="review-item">
                                    <label>Contacto</label>
                                    <span id="reviewContact">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="review-section">
                            <h4 class="review-title">Documentos</h4>
                            <div class="review-media-grid">
                                <div class="review-media-item">
                                    <img id="reviewFrontImg" src="" alt="Frente do Documento" style="display: none;">
                                    <div id="frontPlaceholder" class="media-placeholder">
                                        <i class="fa-solid fa-image"></i>
                                        <p>Frente não carregada</p>
                                    </div>
                                </div>
                                <div class="review-media-item">
                                    <img id="reviewBackImg" src="" alt="Verso do Documento" style="display: none;">
                                    <div id="backPlaceholder" class="media-placeholder">
                                        <i class="fa-solid fa-image"></i>
                                        <p>Verso não carregado</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="review-section">
                            <h4 class="review-title">Vídeo de Verificação</h4>
                            <div class="review-video-container">
                                <video id="reviewVideo" controls style="display: none;"></video>
                                <div id="videoPlaceholder" class="media-placeholder">
                                    <i class="fa-solid fa-video"></i>
                                    <p>Vídeo não gravado</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="goToStep(3)">Voltar</button>
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fa-solid fa-paper-plane"></i> Enviar para Análise
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Processing Status Section -->
        <div class="verification-processing" id="processingSection" style="display: none;">
            <div class="processing-container">
                <div class="processing-header">
                    <h2>Processando sua Verificação</h2>
                    <p>Sua solicitação está sendo analisada pela nossa equipe de IA. Isso pode levar alguns minutos.</p>
                </div>

                <div class="processing-steps-ai">
                    <div class="ai-step" id="aiStep1">
                        <div class="ai-step-icon">
                            <i class="fa-solid fa-face-smile"></i>
                        </div>
                        <div class="ai-step-content">
                            <h4>Análise Facial</h4>
                            <p>Verificando correspondência entre rosto e documento...</p>
                        </div>
                        <div class="ai-step-status">
                            <span class="status-badge pending">Pendente</span>
                        </div>
                    </div>

                    <div class="ai-step" id="aiStep2">
                        <div class="ai-step-icon">
                            <i class="fa-solid fa-heartbeat"></i>
                        </div>
                        <div class="ai-step-content">
                            <h4>Detecção de Vida</h4>
                            <p>Validando que você é uma pessoa real...</p>
                        </div>
                        <div class="ai-step-status">
                            <span class="status-badge pending">Pendente</span>
                        </div>
                    </div>

                    <div class="ai-step" id="aiStep3">
                        <div class="ai-step-icon">
                            <i class="fa-solid fa-shield-check"></i>
                        </div>
                        <div class="ai-step-content">
                            <h4>Avaliação de Risco</h4>
                            <p>Analisando segurança e conformidade...</p>
                        </div>
                        <div class="ai-step-status">
                            <span class="status-badge pending">Pendente</span>
                        </div>
                    </div>
                </div>

                <div class="processing-score">
                    <div class="score-card">
                        <h4>Score de Similaridade Facial</h4>
                        <div class="score-bar">
                            <div class="score-fill" id="scoreBar" style="width: 0%"></div>
                        </div>
                        <p class="score-text"><span id="scoreValue">0</span>%</p>
                    </div>
                </div>

                <div class="processing-status-text" id="statusText">
                    Processando...
                </div>
            </div>
        </div>

        <!-- Result Section -->
        <div class="verification-result" id="resultSection" style="display: none;">
            <div class="result-container">
                <div class="result-icon" id="resultIcon"></div>
                <h2 class="result-title" id="resultTitle"></h2>
                <p class="result-message" id="resultMessage"></p>
                <div class="result-details" id="resultDetails"></div>
                <div class="result-actions">
                    <button type="button" class="btn btn-primary" onclick="location.href='<?= BASE_URL ?>'">
                        Voltar ao Início
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>


<!-- Verification Scripts -->
<script src="<?= BASE_URL ?>assets/js/verification/verification-main.js"></script>
<script src="<?= BASE_URL ?>assets/js/verification/verification-media.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/verification/verification-api.js"></script>

<script>
    // Initialize verification page
    document.addEventListener('DOMContentLoaded', function() {
        initializeVerificationPage();
    });
</script>
</body>

</html>