<?php

/**
 * public/admin/verifications_review.php
 * 
 * Painel profissional de revisão manual de verificações de identidade.
 * Suporta filtros, busca, paginação, scores visuais, badges de risco e AJAX.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Validar acesso admin
check_admin_access();

// ─────────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO DE AÇÕES (POST)
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = sanitize_input($_POST['action'] ?? '');
    $verification_id = (int)($_POST['verification_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $admin_id = get_current_user_id();
    $notes = sanitize_input($_POST['admin_notes'] ?? '');
    $risk_level = sanitize_input($_POST['risk_level'] ?? 'low');

    // Validar risk_level
    if (!in_array($risk_level, ['low', 'medium', 'high'])) {
        $risk_level = 'low';
    }

    if ($verification_id <= 0 || $user_id <= 0 || $admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'approve') {
            // Aprovar verificação
            $stmt = $pdo->prepare("
                UPDATE user_verifications 
                SET status = 'approved', 
                    admin_notes = ?, 
                    reviewed_by = ?, 
                    reviewed_at = NOW(),
                    risk_level = ?
                WHERE id = ?
            ");
            $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

            // Atualizar usuário
            $stmt_user = $pdo->prepare("
                UPDATE users 
                SET is_verified_creator = 1, verification_status = 'approved' 
                WHERE id = ?
            ");
            $stmt_user->execute([$user_id]);

            // Registrar em admin_logs
            $log_details = json_encode([
                'verification_id' => $verification_id,
                'user_id' => $user_id,
                'action' => 'approve',
                'risk_level' => $risk_level,
                'notes' => $notes
            ]);
            $stmt_log = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_log->execute([$admin_id, 'VERIFICATION_APPROVED', $log_details, get_client_ip()]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Verificação aprovada com sucesso',
                'verification_id' => $verification_id
            ]);
        } elseif ($action === 'reject') {
            // Rejeitar verificação
            $stmt = $pdo->prepare("
                UPDATE user_verifications 
                SET status = 'rejected', 
                    admin_notes = ?, 
                    reviewed_by = ?, 
                    reviewed_at = NOW(),
                    risk_level = ?
                WHERE id = ?
            ");
            $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

            // Atualizar usuário
            $stmt_user = $pdo->prepare("
                UPDATE users 
                SET verification_status = 'rejected' 
                WHERE id = ?
            ");
            $stmt_user->execute([$user_id]);

            // Registrar em admin_logs
            $log_details = json_encode([
                'verification_id' => $verification_id,
                'user_id' => $user_id,
                'action' => 'reject',
                'risk_level' => $risk_level,
                'notes' => $notes
            ]);
            $stmt_log = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_log->execute([$admin_id, 'VERIFICATION_REJECTED', $log_details, get_client_ip()]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Verificação rejeitada',
                'verification_id' => $verification_id
            ]);
        } elseif ($action === 'manual_review') {
            // Marcar para revisão manual (sem decisão final)
            $stmt = $pdo->prepare("
                UPDATE user_verifications 
                SET admin_notes = ?, 
                    reviewed_by = ?, 
                    reviewed_at = NOW(),
                    risk_level = ?
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Verificação marcada para revisão manual',
                'verification_id' => $verification_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// RECUPERAÇÃO DE DADOS (GET)
// ─────────────────────────────────────────────────────────────────────────────

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtros
$status_filter = sanitize_input($_GET['status'] ?? 'pending');
$search_query = sanitize_input($_GET['search'] ?? '');
$sort_by = sanitize_input($_GET['sort'] ?? 'created_at');
$sort_order = sanitize_input($_GET['order'] ?? 'DESC');

// Validar sort_by e sort_order
$allowed_sorts = ['created_at', 'ai_similarity', 'username', 'risk_level'];
if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'created_at';
if (!in_array($sort_order, ['ASC', 'DESC'])) $sort_order = 'DESC';

// Validar status_filter
$allowed_statuses = ['pending', 'approved', 'rejected', 'manual_review'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'pending';

// Construir query base
$where_clauses = [];
$params = [];

// Filtro por status
if ($status_filter !== 'all') {
    $where_clauses[] = "v.status = ?";
    $params[] = $status_filter;
}

// Busca por usuário (username, email, full_name)
if (!empty($search_query)) {
    $where_clauses[] = "(u.username LIKE ? OR u.email LIKE ? OR v.full_name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Contar total
$count_query = "SELECT COUNT(*) as total FROM user_verifications v JOIN users u ON v.user_id = u.id $where_sql";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $per_page);

// Recuperar dados
$query = "
    SELECT 
        v.id, 
        v.user_id, 
        v.full_name, 
        v.nickname,
        v.birth_date,
        v.province,
        v.contact_phone,
        v.id_front_path, 
        v.id_back_path, 
        v.video_path,
        v.status, 
        v.ai_status,
        v.ai_similarity, 
        v.ai_liveness, 
        v.ai_notes,
        v.admin_notes,
        v.risk_level,
        v.created_at, 
        v.reviewed_at,
        v.reviewed_by,
        u.username, 
        u.email,
        admin_user.username as reviewed_by_username
    FROM user_verifications v 
    JOIN users u ON v.user_id = u.id 
    LEFT JOIN users admin_user ON v.reviewed_by = admin_user.id
    $where_sql
    ORDER BY v.$sort_by $sort_order
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$per_page, $offset]));
$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─────────────────────────────────────────────────────────────────────────────
// FUNÇÕES AUXILIARES
// ─────────────────────────────────────────────────────────────────────────────

function get_risk_badge($risk_level, $ai_similarity = null)
{
    $badges = [
        'low' => ['class' => 'badge-success', 'icon' => 'fa-check-circle', 'text' => 'Baixo'],
        'medium' => ['class' => 'badge-warning', 'icon' => 'fa-exclamation-circle', 'text' => 'Médio'],
        'high' => ['class' => 'badge-danger', 'icon' => 'fa-times-circle', 'text' => 'Alto']
    ];

    $badge = $badges[$risk_level] ?? $badges['low'];
    return sprintf(
        '<span class="badge %s"><i class="fas %s"></i> %s</span>',
        $badge['class'],
        $badge['icon'],
        $badge['text']
    );
}

function get_status_badge($status)
{
    $statuses = [
        'pending' => ['class' => 'badge-info', 'text' => 'Pendente'],
        'approved' => ['class' => 'badge-success', 'text' => 'Aprovado'],
        'rejected' => ['class' => 'badge-danger', 'text' => 'Rejeitado'],
        'manual_review' => ['class' => 'badge-warning', 'text' => 'Revisão Manual']
    ];

    $badge = $statuses[$status] ?? $statuses['pending'];
    return sprintf('<span class="badge %s">%s</span>', $badge['class'], $badge['text']);
}

function get_ai_status_badge($ai_status)
{
    $statuses = [
        'pending' => ['class' => 'badge-secondary', 'text' => 'Aguardando IA'],
        'ai_approved' => ['class' => 'badge-success', 'text' => 'IA Aprovado'],
        'ai_rejected' => ['class' => 'badge-danger', 'text' => 'IA Rejeitado'],
        'ai_error' => ['class' => 'badge-dark', 'text' => 'Erro IA'],
        'manual_review' => ['class' => 'badge-warning', 'text' => 'Revisão Manual']
    ];

    $badge = $statuses[$ai_status] ?? $statuses['pending'];
    return sprintf('<span class="badge %s">%s</span>', $badge['class'], $badge['text']);
}

function get_similarity_color($score)
{
    if ($score === null) return 'gray';
    if ($score >= 0.85) return 'green';
    if ($score >= 0.70) return 'orange';
    return 'red';
}

function get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
?>

<style>
    .verifications-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .filters-section {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-weight: 600;
        font-size: 0.9rem;
        color: #333;
    }

    .filter-group input,
    .filter-group select {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.9rem;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-filter {
        background: #007bff;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s;
    }

    .btn-filter:hover {
        background: #0056b3;
    }

    .btn-reset {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s;
    }

    .btn-reset:hover {
        background: #545b62;
    }

    .verification-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .verification-table thead {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .verification-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
        cursor: pointer;
        user-select: none;
    }

    .verification-table th:hover {
        background: #e9ecef;
    }

    .verification-table td {
        padding: 15px;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.9rem;
    }

    .verification-table tbody tr:hover {
        background: #f8f9fa;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #007bff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .user-details {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .user-details strong {
        color: #333;
    }

    .user-details small {
        color: #666;
    }

    .score-display {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }

    .score-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 0.85rem;
    }

    .score-circle.green {
        background: #28a745;
    }

    .score-circle.orange {
        background: #ffc107;
        color: #333;
    }

    .score-circle.red {
        background: #dc3545;
    }

    .score-circle.gray {
        background: #6c757d;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-approve {
        background: #28a745;
        color: white;
    }

    .btn-approve:hover {
        background: #218838;
    }

    .btn-reject {
        background: #dc3545;
        color: white;
    }

    .btn-reject:hover {
        background: #c82333;
    }

    .btn-review {
        background: #ffc107;
        color: #333;
    }

    .btn-review:hover {
        background: #e0a800;
    }

    .btn-view {
        background: #17a2b8;
        color: white;
    }

    .btn-view:hover {
        background: #138496;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
        color: #007bff;
        cursor: pointer;
    }

    .pagination a:hover {
        background: #007bff;
        color: white;
    }

    .pagination .active {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .empty-state i {
        font-size: 3rem;
        color: #ccc;
        margin-bottom: 15px;
    }

    .empty-state p {
        color: #666;
        font-size: 1rem;
    }

    /* Modal Styles */
    .review-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        overflow: auto;
    }

    .review-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 30px;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .review-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #eee;
        padding-bottom: 15px;
    }

    .review-modal-header h2 {
        margin: 0;
        color: #333;
    }

    .close-modal {
        font-size: 28px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
    }

    .close-modal:hover {
        color: #000;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }

    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
    }

    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .btn-modal {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-modal-cancel {
        background: #6c757d;
        color: white;
    }

    .btn-modal-cancel:hover {
        background: #545b62;
    }

    .btn-modal-submit {
        background: #007bff;
        color: white;
    }

    .btn-modal-submit:hover {
        background: #0056b3;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }

    .badge-secondary {
        background: #e2e3e5;
        color: #383d41;
    }

    .badge-dark {
        background: #d6d8db;
        color: #1b1e21;
    }

    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }

        .verification-table {
            font-size: 0.8rem;
        }

        .verification-table th,
        .verification-table td {
            padding: 10px;
        }

        .actions-cell {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="admin-card">
    <div class="admin-header">
        <h2><i class="fas fa-user-check"></i> Revisão Manual de Verificações</h2>
        <p>Analise e aprove/rejeite pedidos de verificação de identidade com score visual e badges de risco.</p>
    </div>

    <div class="verifications-container">
        <!-- FILTROS -->
        <div class="filters-section">
            <form method="GET" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Todos</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Aprovados</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejeitados</option>
                            <option value="manual_review" <?= $status_filter === 'manual_review' ? 'selected' : '' ?>>Revisão Manual</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sort">Ordenar por</label>
                        <select name="sort" id="sort">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Data de Submissão</option>
                            <option value="ai_similarity" <?= $sort_by === 'ai_similarity' ? 'selected' : '' ?>>Score de Similaridade</option>
                            <option value="username" <?= $sort_by === 'username' ? 'selected' : '' ?>>Usuário</option>
                            <option value="risk_level" <?= $sort_by === 'risk_level' ? 'selected' : '' ?>>Nível de Risco</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="search">Pesquisar</label>
                        <input type="text" name="search" id="search" placeholder="Usuário, email ou nome..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="verifications_review.php" class="btn-reset"><i class="fas fa-redo"></i> Limpar</a>
                </div>
            </form>
        </div>

        <!-- TABELA -->
        <?php if (empty($verifications)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhuma verificação encontrada com os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <table class="verification-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Dados Pessoais</th>
                        <th>Score IA</th>
                        <th>Status</th>
                        <th>Risco</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verifications as $v): ?>
                        <tr>
                            <!-- Usuário -->
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?= strtoupper(substr($v['username'], 0, 1)) ?></div>
                                    <div class="user-details">
                                        <strong>@<?= htmlspecialchars($v['username']) ?></strong>
                                        <small><?= htmlspecialchars($v['email']) ?></small>
                                    </div>
                                </div>
                            </td>

                            <!-- Dados Pessoais -->
                            <td>
                                <small>
                                    <strong><?= htmlspecialchars($v['full_name']) ?></strong><br>
                                    <?= htmlspecialchars($v['nickname']) ?><br>
                                    <span style="color: #666;">📱 <?= htmlspecialchars($v['contact_phone']) ?></span>
                                </small>
                            </td>

                            <!-- Score IA -->
                            <td>
                                <?php if ($v['ai_similarity'] !== null): ?>
                                    <div class="score-display">
                                        <div class="score-circle <?= get_similarity_color($v['ai_similarity']) ?>">
                                            <?= round($v['ai_similarity'] * 100) ?>%
                                        </div>
                                        <div>
                                            <?= get_ai_status_badge($v['ai_status']) ?>
                                            <?php if ($v['ai_liveness']): ?>
                                                <br><span style="color: #28a745;"><i class="fas fa-check"></i> Liveness OK</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">Aguardando IA</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <?= get_status_badge($v['status']) ?>
                                <?php if ($v['reviewed_by']): ?>
                                    <br><small style="color: #666;">Por: <?= htmlspecialchars($v['reviewed_by_username']) ?></small>
                                <?php endif; ?>
                            </td>

                            <!-- Risco -->
                            <td>
                                <?= get_risk_badge($v['risk_level'], $v['ai_similarity']) ?>
                            </td>

                            <!-- Data -->
                            <td>
                                <small>
                                    <strong>Enviado:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
                                    <?php if ($v['reviewed_at']): ?>
                                        <br><strong>Revisado:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($v['reviewed_at'])) ?>
                                    <?php endif; ?>
                                </small>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action btn-view" onclick="openMediaModal(<?= $v['id'] ?>, '<?= htmlspecialchars($v['id_front_path']) ?>', '<?= htmlspecialchars($v['id_back_path']) ?>', '<?= htmlspecialchars($v['video_path']) ?>')">
                                        <i class="fas fa-images"></i> Ver
                                    </button>
                                    <?php if ($v['status'] === 'pending'): ?>
                                        <button class="btn-action btn-approve" onclick="openReviewModal(<?= $v['id'] ?>, <?= $v['user_id'] ?>, 'approve')">
                                            <i class="fas fa-check"></i> Aprovar
                                        </button>
                                        <button class="btn-action btn-reject" onclick="openReviewModal(<?= $v['id'] ?>, <?= $v['user_id'] ?>, 'reject')">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- PAGINAÇÃO -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>">« Primeira</a>
                        <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>">‹ Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>">Próxima ›</a>
                        <a href="?page=<?= $total_pages ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>">Última »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE REVISÃO -->
<div id="reviewModal" class="review-modal">
    <div class="review-modal-content">
        <div class="review-modal-header">
            <h2 id="modalTitle">Revisar Verificação</h2>
            <span class="close-modal" onclick="closeReviewModal()">&times;</span>
        </div>

        <form id="reviewForm">
            <input type="hidden" id="formAction" name="action">
            <input type="hidden" id="formVerificationId" name="verification_id">
            <input type="hidden" id="formUserId" name="user_id">

            <div class="form-group">
                <label for="riskLevel">Nível de Risco</label>
                <select id="riskLevel" name="risk_level" required>
                    <option value="low">Baixo</option>
                    <option value="medium">Médio</option>
                    <option value="high">Alto</option>
                </select>
            </div>

            <div class="form-group">
                <label for="adminNotes">Notas Administrativas</label>
                <textarea id="adminNotes" name="admin_notes" placeholder="Adicione observações sobre a decisão..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeReviewModal()">Cancelar</button>
                <button type="submit" class="btn-modal btn-modal-submit">
                    <span id="submitText">Submeter</span>
                    <span id="submitSpinner" class="loading-spinner" style="display: none;"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE MÍDIA -->
<div id="mediaModal" class="review-modal">
    <div class="review-modal-content" style="max-width: 800px;">
        <div class="review-modal-header">
            <h2>Visualizar Documentos e Vídeo</h2>
            <span class="close-modal" onclick="closeMediaModal()">&times;</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <h4>Frente do BI</h4>
                <img id="mediaFront" src="" style="width: 100%; border-radius: 6px; border: 1px solid #ddd;">
            </div>
            <div>
                <h4>Verso do BI</h4>
                <img id="mediaBack" src="" style="width: 100%; border-radius: 6px; border: 1px solid #ddd;">
            </div>
        </div>

        <div>
            <h4>Vídeo de Prova de Vida</h4>
            <video id="mediaVideo" controls style="width: 100%; border-radius: 6px; border: 1px solid #ddd; background: #000;">
                Seu navegador não suporta vídeos.
            </video>
        </div>
    </div>
</div>

<script>
    let currentAction = '';
    let currentVerificationId = 0;
    let currentUserId = 0;

    function openReviewModal(verificationId, userId, action) {
        currentAction = action;
        currentVerificationId = verificationId;
        currentUserId = userId;

        document.getElementById('formAction').value = action;
        document.getElementById('formVerificationId').value = verificationId;
        document.getElementById('formUserId').value = userId;

        const actionText = action === 'approve' ? 'Aprovar' : 'Rejeitar';
        document.getElementById('modalTitle').textContent = actionText + ' Verificação';

        document.getElementById('reviewModal').style.display = 'block';
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
        document.getElementById('reviewForm').reset();
    }

    function openMediaModal(verificationId, frontPath, backPath, videoPath) {
        const baseUrl = '<?= UPLOAD_URL ?>';
        document.getElementById('mediaFront').src = baseUrl + frontPath;
        document.getElementById('mediaBack').src = baseUrl + backPath;
        document.getElementById('mediaVideo').src = baseUrl + videoPath;
        document.getElementById('mediaModal').style.display = 'block';
    }

    function closeMediaModal() {
        document.getElementById('mediaModal').style.display = 'none';
        document.getElementById('mediaVideo').pause();
    }

    document.getElementById('reviewForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');

        submitText.style.display = 'none';
        submitSpinner.style.display = 'inline-block';
        submitBtn.disabled = true;

        const formData = new FormData(this);

        try {
            const response = await fetch('verifications_review.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao processar a solicitação: ' + error.message);
        } finally {
            submitText.style.display = 'inline';
            submitSpinner.style.display = 'none';
            submitBtn.disabled = false;
        }
    });

    // Fechar modais ao clicar fora deles
    window.onclick = function(event) {
        const reviewModal = document.getElementById('reviewModal');
        const mediaModal = document.getElementById('mediaModal');

        if (event.target === reviewModal) {
            closeReviewModal();
        }
        if (event.target === mediaModal) {
            closeMediaModal();
        }
    };
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>