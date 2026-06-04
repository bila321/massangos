<?php
// public/manage_album_partners.php

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\Album;
use Massango\Models\AlbumPartner;
use Massango\Models\User;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$albumId = (int)($_GET['album_id'] ?? 0);

if ($albumId <= 0) {
    set_message("Álbum não especificado.", "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

$album = Album::getAlbumById($pdo, $albumId);
if (!$album) {
    set_message("Álbum não encontrado.", "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

// Verificar se o usuário é o dono ou um parceiro
$isOwner = ($album['user_id'] == $userId);
$partners = AlbumPartner::getAlbumPartners($pdo, $albumId);
$isPartner = false;
$userPartnerInfo = null;

foreach ($partners as $partner) {
    if ($partner['user_id'] == $userId) {
        $isPartner = true;
        $userPartnerInfo = $partner;
        break;
    }
}

if (!$isOwner && !$isPartner) {
    set_message("Você não tem permissão para visualizar esta página.", "danger");
    redirect(BASE_URL . 'index.php');
    exit();
}

// Calcular percentagem usada
$totalPercentage = 0;
foreach ($partners as $partner) {
    if ($partner['status'] !== 'rejected') {
        $totalPercentage += (float)$partner['percentage'];
    }
}
$availablePercentage = 100 - $totalPercentage;

$extra_css = ['premium_lightbox.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modal.css">
<style>
    .manage-partners-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
    }

    .partners-header {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-light);
    }

    .album-info {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 20px;
    }

    .album-info img {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
    }

    .album-info-text h2 {
        margin: 0 0 5px 0;
        color: var(--text-primary);
    }

    .album-info-text p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .percentage-status {
        background: var(--surface-bg);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .percentage-bar {
        width: 100%;
        height: 20px;
        background: var(--border-light);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .percentage-fill {
        height: 100%;
        background: linear-gradient(90deg, #4CAF50, #8BC34A);
        transition: width 0.3s ease;
    }

    .percentage-text {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .add-partner-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 14px;
        margin-bottom: 20px;
        transition: all 0.2s;
    }

    .add-partner-btn:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }

    .partners-list {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-light);
    }

    .partner-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        border-bottom: 1px solid var(--border-light);
        transition: background 0.2s;
    }

    .partner-item:last-child {
        border-bottom: none;
    }

    .partner-item:hover {
        background: var(--surface-bg);
    }

    .partner-info {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
    }

    .partner-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .partner-details {
        flex: 1;
    }

    .partner-name {
        font-weight: 500;
        color: var(--text-primary);
        margin: 0;
    }

    .partner-status {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 10px;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-accepted {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .partner-percentage {
        color: var(--text-secondary);
        font-size: 14px;
        margin: 5px 0 0 0;
    }

    .partner-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .btn-action {
        padding: 8px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        color: white;
    }

    .btn-accept {
        background: #28a745;
    }

    .btn-reject {
        background: #dc3545;
    }

    .btn-edit {
        background: var(--primary-color);
    }

    .btn-delete {
        background: #f44336;
    }

    .btn-action:hover {
        opacity: 0.9;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 30px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border-light);
        border-radius: 4px;
    }

    .user-list {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid var(--border-light);
        margin-top: 5px;
    }

    .user-item-search {
        padding: 8px;
        cursor: pointer;
    }

    .user-item-search:hover {
        background: var(--surface-bg);
    }
</style>

<div class="main-layout-container">
    <main class="main-content-area">
        <?php get_and_clear_messages(); ?>

        <div class="manage-partners-container">
            <div class="partners-header">
                <div class="album-info">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($album['cover_photo_url']) ?>" alt="<?= htmlspecialchars($album['album_name']) ?>">
                    <div class="album-info-text">
                        <h2><?= htmlspecialchars($album['album_name']) ?></h2>
                        <p><?= htmlspecialchars($album['album_description'] ?? 'Sem descrição') ?></p>
                        <p><strong>Dono:</strong> <?= ($isOwner) ? "Você" : "@" . User::getUserById($pdo, $album['user_id'])['username'] ?></p>
                    </div>
                </div>

                <div class="percentage-status">
                    <div class="percentage-bar">
                        <div class="percentage-fill" style="width: <?= $totalPercentage ?>%"></div>
                    </div>
                    <div class="percentage-text">
                        Percentagem total distribuída: <strong><?= number_format($totalPercentage, 2) ?>%</strong>
                        <?php if ($isOwner): ?>
                            | Disponível: <strong><?= number_format($availablePercentage, 2) ?>%</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isOwner && $availablePercentage > 0): ?>
                    <button class="add-partner-btn" onclick="openAddPartnerModal()">+ Adicionar Parceiro</button>
                <?php endif; ?>

                <?php if ($isPartner && $userPartnerInfo['status'] === 'pending'): ?>
                    <div style="background: #eef2ff; padding: 15px; border-radius: 8px; border-left: 4px solid #4f46e5; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #1e1b4b;">Convite de Parceria Pendente</h4>
                        <p style="margin: 0 0 15px 0; font-size: 14px;">Você foi convidado para ser parceiro deste álbum com <strong><?= $userPartnerInfo['percentage'] ?>%</strong> de participação.</p>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn-action btn-accept" onclick="respondPartnership(<?= $userPartnerInfo['id'] ?>, 'accept')">Aceitar Convite</button>
                            <button class="btn-action btn-reject" onclick="respondPartnership(<?= $userPartnerInfo['id'] ?>, 'reject')">Recusar</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="partners-list">
                <h3>Lista de Parceiros</h3>
                <?php if (empty($partners)): ?>
                    <div class="empty-state">Nenhum parceiro adicionado ainda.</div>
                <?php else: ?>
                    <?php foreach ($partners as $partner): ?>
                        <div class="partner-item">
                            <div class="partner-info">
                                <img src="<?= UPLOAD_URL . htmlspecialchars($partner['profile_picture'] ?? 'default_profile.png') ?>" alt="<?= htmlspecialchars($partner['username']) ?>" class="partner-avatar">
                                <div class="partner-details">
                                    <p class="partner-name">
                                        @<?= htmlspecialchars($partner['username']) ?>
                                        <span class="partner-status status-<?= $partner['status'] ?>">
                                            <?= ucfirst($partner['status']) ?>
                                        </span>
                                    </p>
                                    <p class="partner-percentage">Participação: <?= number_format($partner['percentage'], 2) ?>%</p>
                                </div>
                            </div>
                            <?php if ($isOwner): ?>
                                <div class="partner-actions">
                                    <button class="btn-action btn-edit" onclick="openEditPartnerModal(<?= $partner['id'] ?>, <?= $partner['percentage'] ?>)">Editar</button>
                                    <button class="btn-action btn-delete" onclick="removePartner(<?= $partner['id'] ?>)">Remover</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal Adicionar Parceiro -->
<div id="addPartnerModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Adicionar Novo Parceiro</h3>
        <div class="form-group">
            <label>Buscar Usuário</label>
            <input type="text" id="userSearch" placeholder="Digite o username..." autocomplete="off">
            <div id="userList" class="user-list"></div>
        </div>
        <div class="form-group">
            <label>Percentagem (%)</label>
            <input type="number" id="partnerPercentage" step="0.01" min="0.01" max="<?= $availablePercentage ?>">
        </div>
        <div class="modal-actions" style="display: flex; gap: 10px; margin-top: 20px;">
            <button class="btn-action btn-edit" style="flex: 1;" onclick="addPartner()">Convidar</button>
            <button class="btn-action btn-reject" style="flex: 1;" onclick="closeAddPartnerModal()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal Editar Percentagem -->
<div id="editPartnerModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Editar Percentagem</h3>
        <div class="form-group">
            <label>Nova Percentagem (%)</label>
            <input type="number" id="editPercentage" step="0.01" min="0.01" max="100">
        </div>
        <div class="modal-actions" style="display: flex; gap: 10px; margin-top: 20px;">
            <button class="btn-action btn-edit" style="flex: 1;" onclick="updatePartner()">Salvar</button>
            <button class="btn-action btn-reject" style="flex: 1;" onclick="closeEditPartnerModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
    const albumId = <?= $albumId ?>;
    let selectedUserId = null;
    let editingPartnerId = null;

    function openAddPartnerModal() {
        document.getElementById('addPartnerModal').classList.add('active');
    }

    function closeAddPartnerModal() {
        document.getElementById('addPartnerModal').classList.remove('active');
        selectedUserId = null;
    }

    function openEditPartnerModal(id, current) {
        editingPartnerId = id;
        document.getElementById('editPercentage').value = current;
        document.getElementById('editPartnerModal').classList.add('active');
    }

    function closeEditPartnerModal() {
        document.getElementById('editPartnerModal').classList.remove('active');
    }

    // Busca de usuários
    document.getElementById('userSearch').addEventListener('input', async function(e) {
        const q = e.target.value.trim();
        if (q.length < 2) {
            document.getElementById('userList').innerHTML = '';
            return;
        }

        try {
            const res = await fetch(`<?= BASE_URL ?>api/search_users.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            let html = '';
            if (data.users && data.users.length > 0) {
                data.users.forEach(u => {
                    html += `<div class="user-item-search" onclick="selectUser(${u.id}, '${u.username}')">@${u.username}</div>`;
                });
            } else {
                html = '<div style="padding: 10px;">Nenhum usuário encontrado</div>';
            }
            document.getElementById('userList').innerHTML = html;
        } catch (err) {
            console.error(err);
        }
    });

    function selectUser(id, name) {
        selectedUserId = id;
        document.getElementById('userSearch').value = '@' + name;
        document.getElementById('userList').innerHTML = '';
    }

    async function addPartner() {
        if (!selectedUserId) {
            alert('Selecione um usuário');
            return;
        }
        const p = document.getElementById('partnerPercentage').value;

        const res = await fetch('<?= BASE_URL ?>api/album_partners.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=add_partner&album_id=${albumId}&partner_id=${selectedUserId}&percentage=${p}`
        });
        const data = await res.json();
        if (data.success) {
            alert('Convite enviado!');
            location.reload();
        } else {
            alert(data.message);
        }
    }

    async function respondPartnership(partnerId, response) {
        const action = response === 'accept' ? 'accept_partnership' : 'reject_partnership';
        try {
            const res = await fetch('<?= BASE_URL ?>api/album_partners.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=${action}&partner_id=${partnerId}`
            });
            const data = await res.json();
            if (data.success) {
                alert(response === 'accept' ? 'Parceria aceita com sucesso!' : 'Parceria recusada.');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (err) {
            console.error(err);
            alert('Erro ao processar resposta.');
        }
    }

    async function updatePartner() {
        const p = document.getElementById('editPercentage').value;
        const res = await fetch('<?= BASE_URL ?>api/album_partners.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=update_percentage&partner_id=${editingPartnerId}&percentage=${p}`
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    }

    async function removePartner(id) {
        if (!confirm('Remover parceiro?')) return;
        const res = await fetch('<?= BASE_URL ?>api/album_partners.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=remove_partner&partner_id=${id}`
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>