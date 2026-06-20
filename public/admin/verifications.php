<?php
// public/admin/verifications.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Lógica para ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $verification_id = (int)($_POST['verification_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $notes = sanitize_input($_POST['admin_notes'] ?? '');

    if ($verification_id > 0 && $user_id > 0) {
        try {
            $pdo->beginTransaction();

            if ($action === 'approve') {
                // Aprovar verificação
                $stmt = $pdo->prepare("UPDATE user_verifications SET status = 'approved', admin_notes = ? WHERE id = ?");
                $stmt->execute([$notes, $verification_id]);

                // Atualizar usuário
                $stmt_user = $pdo->prepare("UPDATE users SET is_verified_creator = 1, verification_status = 'approved' WHERE id = ?");
                $stmt_user->execute([$user_id]);

                $_SESSION['admin_message'] = "Verificação aprovada com sucesso.";
                $_SESSION['admin_message_type'] = "success";
            } elseif ($action === 'reject') {
                // Rejeitar verificação
                $stmt = $pdo->prepare("UPDATE user_verifications SET status = 'rejected', admin_notes = ? WHERE id = ?");
                $stmt->execute([$notes, $verification_id]);

                // Atualizar usuário
                $stmt_user = $pdo->prepare("UPDATE users SET verification_status = 'rejected' WHERE id = ?");
                $stmt_user->execute([$user_id]);

                $_SESSION['admin_message'] = "Verificação rejeitada.";
                $_SESSION['admin_message_type'] = "warning";
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['admin_message'] = "Erro: " . $e->getMessage();
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    header("Location: verifications.php");
    exit();
}

$query = "
    SELECT v.*, u.username, u.email 
    FROM user_verifications v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.status = 'pending' 
    ORDER BY v.created_at ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .verification-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .verification-card {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .verification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .verification-body {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 20px;
    }

    .user-info-section {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
    }

    .media-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .media-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .media-item {
        position: relative;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #ddd;
    }

    .media-item img,
    .media-item video {
        width: 100%;
        height: 200px;
        object-fit: cover;
        display: block;
        cursor: pointer;
    }

    .media-label {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        font-size: 0.75rem;
        padding: 4px 8px;
        text-align: center;
    }

    .admin-actions-section {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }

    .btn-view-full {
        background: #333;
        color: #fff;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-top: 5px;
    }

    /* Modal styles */
    .v-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        overflow: auto;
    }

    .v-modal-content {
        margin: auto;
        display: block;
        max-width: 90%;
        max-height: 90%;
        margin-top: 2%;
    }

    .v-close {
        position: absolute;
        top: 15px;
        right: 35px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
    }
</style>

<div class="admin-card">
    <div class="admin-header">
        <h2><i class="fas fa-user-check"></i> Análise de Verificações Pendentes</h2>
        <p>Revise os documentos e o vídeo de identificação antes de aprovar um criador.</p>
    </div>

    <?php if (empty($verifications)): ?>
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-check-circle" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
            <p>Excelente! Não há pedidos de verificação pendentes.</p>
        </div>
    <?php else: ?>
        <div class="verification-container">
            <?php foreach ($verifications as $v): ?>
                <div class="verification-card">
                    <div class="verification-header">
                        <div>
                            <strong>@<?= htmlspecialchars($v['username']) ?></strong>
                            <span style="color: #888; margin-left: 10px; font-size: 0.85rem;">Enviado em: <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></span>
                        </div>
                        <span class="badge badge-warning">Pendente</span>
                    </div>

                    <div class="verification-body">
                        <div class="user-info-section">
                            <h4 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Dados do Formulário</h4>
                            <p><strong>Nome Completo:</strong><br><?= htmlspecialchars($v['full_name']) ?></p>
                            <p><strong>Apelido:</strong><br><?= htmlspecialchars($v['nickname']) ?></p>
                            <p><strong>Data de Nasc.:</strong><br><?= htmlspecialchars($v['birth_date']) ?></p>
                            <p><strong>Província:</strong><br><?= htmlspecialchars($v['province']) ?></p>
                            <p><strong>Telefone:</strong><br><?= htmlspecialchars($v['contact_phone']) ?></p>
                            <p><strong>Email:</strong><br><?= htmlspecialchars($v['email']) ?></p>

                            <div style="margin-top: 15px; padding: 10px; border-radius: 6px; background: <?= ($v['ai_status'] === 'ai_approved') ? '#e6ffed' : (($v['ai_status'] === 'ai_rejected') ? '#ffeef0' : (($v['ai_status'] === 'queued') ? '#f0f0f0' : '#fff9e6')) ?>; border: 1px solid <?= ($v['ai_status'] === 'ai_approved') ? '#28a745' : (($v['ai_status'] === 'ai_rejected') ? '#dc3545' : (($v['ai_status'] === 'queued') ? '#6c757d' : '#ffc107')) ?>;">
                                <h5 style="margin: 0 0 5px 0; color: #333;"><i class="fas fa-robot"></i> Resultado da IA</h5>
                                <p style="margin: 0; font-size: 0.9rem;">
                                    <strong>Status:</strong> <?= strtoupper(str_replace('ai_', '', $v['ai_status'])) ?><br>
                                    <strong>Similaridade:</strong> <?= number_format($v['ai_similarity'] * 100, 1) ?>%<br>
                                    <small><strong>Notas:</strong> <?= htmlspecialchars($v['ai_notes']) ?></small>
                                </p>
                            </div>
                        </div>

                        <div class="media-section">
                            <h4 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Documentos e Prova de Vida</h4>
                            <div class="media-grid">
                                <div class="media-item">
                                    <img src="<?= UPLOAD_URL . $v['id_front_path'] ?>" onclick="openVModal(this.src, 'img')" alt="Frente do BI">
                                    <div class="media-label">Frente do BI</div>
                                </div>
                                <div class="media-item">
                                    <img src="<?= UPLOAD_URL . $v['id_back_path'] ?>" onclick="openVModal(this.src, 'img')" alt="Verso do BI">
                                    <div class="media-label">Verso do BI</div>
                                </div>
                            </div>
                            <div class="media-item" style="height: auto;">
                                <video controls style="width: 100%; border-radius: 6px; background: #000;">
                                    <source src="<?= UPLOAD_URL . $v['video_path'] ?>" type="video/webm">
                                    Seu navegador não suporta vídeos.
                                </video>
                                <div class="media-label" style="position: relative;">Vídeo de Verificação (Prova de Vida)</div>
                                <a href="<?= UPLOAD_URL . $v['video_path'] ?>" target="_blank" class="btn-view-full"><i class="fas fa-external-link-alt"></i> Abrir em nova aba</a>
                            </div>
                        </div>
                    </div>

                    <div class="admin-actions-section">
                        <form method="POST">
                            <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="user_id" value="<?= $v['user_id'] ?>">
                            <div style="margin-bottom: 10px;">
                                <label><strong>Notas Administrativas:</strong> (Serão visíveis para o usuário em caso de rejeição)</label>
                                <textarea name="admin_notes" class="admin-input" placeholder="Ex: Foto do BI desfocada, por favor reenvie..." style="width: 100%; height: 60px; margin-top: 5px;"></textarea>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" name="action" value="approve" class="btn-admin btn-edit" style="padding: 10px 20px;" onclick="return confirm('Confirmar aprovação deste criador?')">
                                    <i class="fas fa-check"></i> Aprovar Criador
                                </button>
                                <button type="submit" name="action" value="reject" class="btn-admin btn-delete" style="padding: 10px 20px;" onclick="return confirm('Tem certeza que deseja REJEITAR este pedido?')">
                                    <i class="fas fa-times"></i> Rejeitar Pedido
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Ampliar Imagens -->
<div id="vModal" class="v-modal" onclick="this.style.display='none'">
    <span class="v-close">&times;</span>
    <img class="v-modal-content" id="vModalImg">
</div>

<script>
    function openVModal(src, type) {
        var modal = document.getElementById("vModal");
        var modalImg = document.getElementById("vModalImg");
        modal.style.display = "block";
        modalImg.src = src;
    }
</script>