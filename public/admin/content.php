<?php
// public/admin/content.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Lógica para apagar conteúdo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_content') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $item_type = $_POST['item_type'] ?? '';
    $feed_item_id = (int)($_POST['feed_item_id'] ?? 0);

    if ($feed_item_id > 0) {
        $pdo->beginTransaction();
        try {
            if ($item_type === 'post') {
                $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$item_id]);
            } elseif ($item_type === 'video') {
                $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$item_id]);
            } elseif ($item_type === 'album') {
                $pdo->prepare("DELETE FROM album_photos WHERE album_id = ?")->execute([$item_id]);
                $pdo->prepare("DELETE FROM albums WHERE id = ?")->execute([$item_id]);
            }
            $pdo->prepare("DELETE FROM feed_items WHERE id = ?")->execute([$feed_item_id]);
            $pdo->commit();
            $_SESSION['admin_message'] = "Conteúdo removido com sucesso.";
            $_SESSION['admin_message_type'] = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_message'] = "Erro ao remover conteúdo.";
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    header("Location: content.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Lógica para aprovação de conteúdo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_content') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $item_type = $_POST['item_type'] ?? '';

    $table = '';
    if ($item_type === 'post') $table = 'posts';
    elseif ($item_type === 'video') $table = 'videos';
    elseif ($item_type === 'album') $table = 'albums';

    if ($table && $item_id > 0) {
        $stmt = $pdo->prepare("UPDATE $table SET is_approved = 1 WHERE id = ?");
        if ($stmt->execute([$item_id])) {
            $_SESSION['admin_message'] = "Conteúdo aprovado com sucesso.";
            $_SESSION['admin_message_type'] = "success";
        } else {
            $_SESSION['admin_message'] = "Erro ao aprovar conteúdo.";
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    header("Location: content.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Filtros
$filter = $_GET['filter'] ?? 'all';
$where_clause = "";
if ($filter === 'pending') {
    $where_clause = "WHERE (
        (fi.item_type = 'post' AND (SELECT is_approved FROM posts WHERE id = fi.item_id) = 0) OR
        (fi.item_type = 'video' AND (SELECT is_approved FROM videos WHERE id = fi.item_id) = 0) OR
        (fi.item_type = 'album' AND (SELECT is_approved FROM albums WHERE id = fi.item_id) = 0)
    )";
} elseif ($filter === 'sales') {
    $where_clause = "WHERE (
        (fi.item_type = 'video' AND (SELECT is_for_sale FROM videos WHERE id = fi.item_id) = 1) OR
        (fi.item_type = 'album' AND (SELECT is_for_sale FROM albums WHERE id = fi.item_id) = 1)
    )";
}

// Contadores para os filtros
$count_pending = (
    ($pdo->query("SELECT COUNT(*) FROM posts WHERE is_approved = 0")->fetchColumn() ?: 0) +
    ($pdo->query("SELECT COUNT(*) FROM videos WHERE is_approved = 0")->fetchColumn() ?: 0) +
    ($pdo->query("SELECT COUNT(*) FROM albums WHERE is_approved = 0")->fetchColumn() ?: 0)
);

$contents = $pdo->query("
    SELECT fi.id as feed_item_id, fi.item_type, fi.item_id, fi.created_at, u.username, u.profile_picture,
           CASE 
               WHEN fi.item_type = 'post' THEN (SELECT content FROM posts WHERE id = fi.item_id)
               WHEN fi.item_type = 'video' THEN (SELECT caption FROM videos WHERE id = fi.item_id)
               WHEN fi.item_type = 'album' THEN (SELECT name FROM albums WHERE id = fi.item_id)
           END as title,
	           CASE 
	               WHEN fi.item_type = 'post' THEN (SELECT image_path FROM posts WHERE id = fi.item_id)
	               WHEN fi.item_type = 'video' THEN (SELECT thumbnail_path FROM videos WHERE id = fi.item_id)
	               WHEN fi.item_type = 'album' THEN (SELECT cover_photo_url FROM albums WHERE id = fi.item_id)
	               ELSE NULL
	           END as preview_img,
	           CASE 
	               WHEN fi.item_type = 'video' THEN (SELECT video_path FROM videos WHERE id = fi.item_id)
	               ELSE NULL
	           END as video_path,
           CASE 
               WHEN fi.item_type = 'post' THEN (SELECT is_approved FROM posts WHERE id = fi.item_id)
               WHEN fi.item_type = 'video' THEN (SELECT is_approved FROM videos WHERE id = fi.item_id)
               WHEN fi.item_type = 'album' THEN (SELECT is_approved FROM albums WHERE id = fi.item_id)
               ELSE 1
           END as is_approved,
           CASE 
               WHEN fi.item_type = 'post' THEN 0
               WHEN fi.item_type = 'video' THEN (SELECT is_for_sale FROM videos WHERE id = fi.item_id)
               WHEN fi.item_type = 'album' THEN (SELECT is_for_sale FROM albums WHERE id = fi.item_id)
               ELSE 0
           END as is_for_sale,
           CASE 
               WHEN fi.item_type = 'post' THEN 0
               WHEN fi.item_type = 'video' THEN (SELECT price FROM videos WHERE id = fi.item_id)
               WHEN fi.item_type = 'album' THEN (SELECT price FROM albums WHERE id = fi.item_id)
               ELSE 0
           END as price
    FROM feed_items fi 
    JOIN users u ON fi.user_id = u.id 
    $where_clause
    ORDER BY fi.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;"><i class="fas fa-shield-alt"></i> Moderação de Conteúdos</h3>
        <div class="moderation-filters">
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Todos</a>
            <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                Pendentes <?php if ($count_pending > 0): ?><span class="pending-count-badge"><?= $count_pending ?></span><?php endif; ?>
            </a>
            <a href="?filter=sales" class="filter-btn <?= $filter === 'sales' ? 'active' : '' ?>">À Venda</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Conteúdo</th>
                    <th>Autor</th>
                    <th>Status</th>
                    <th>Data</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contents as $item): ?>
                    <tr id="row-<?= $item['feed_item_id'] ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="position: relative;">
                                    <?php if ($item['preview_img']): ?>
                                        <img src="<?= UPLOAD_URL . $item['preview_img'] ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 2px solid #eee;" onerror="this.src='<?= UPLOAD_URL ?>default_profile.png';">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; border-radius: 8px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #ccc; border: 2px solid #eee;">
                                            <i class="fas fa-image fa-lg"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span class="badge badge-info" style="position: absolute; bottom: -5px; right: -5px; font-size: 0.6rem; padding: 2px 6px;"><?= ucfirst($item['item_type']) ?></span>
                                </div>
                                <div>
                                    <div style="font-weight: bold; color: var(--admin-primary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($item['title'] ?? 'Sem título') ?>
                                    </div>
                                    <?php if ($item['is_for_sale']): ?>
                                        <div style="margin-top: 4px;">
                                            <span class="badge badge-warning" style="font-size: 0.65rem;"><i class="fas fa-tag"></i> À VENDA: <?= number_format($item['price'], 2) ?> MT</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <img src="<?= UPLOAD_URL . ($item['profile_picture'] ?: 'default_profile.png') ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                <strong><?= htmlspecialchars($item['username']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($item['is_approved']): ?>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Aprovado</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-clock"></i> Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.85rem; color: #7f8c8d;">
                            <?= date('d/m/Y', strtotime($item['created_at'])) ?><br>
                            <?= date('H:i', strtotime($item['created_at'])) ?>
                        </td>
                        <td class="text-right">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <button onclick="openQuickView(<?= htmlspecialchars(json_encode($item)) ?>)" class="btn-admin btn-edit" style="background: #f1f3f5; color: #495057;" title="Visualização Rápida">
                                    <i class="fas fa-expand"></i>
                                </button>

                                <?php
                                $view_url = BASE_URL . 'post.php?id=' . $item['feed_item_id'];
                                if ($item['item_type'] === 'album') {
                                    $view_url = BASE_URL . 'view_album.php?id=' . $item['item_id'];
                                }
                                ?>
                                <a href="<?= $view_url ?>" target="_blank" class="btn-admin btn-edit" title="Ver no Site">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>

                                <?php if (!$item['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_content">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="item_type" value="<?= $item['item_type'] ?>">
                                        <button type="submit" class="btn-admin" style="background-color: var(--admin-success); color: white;" title="Aprovar Agora">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja apagar este conteúdo?');">
                                    <input type="hidden" name="action" value="delete_content">
                                    <input type="hidden" name="feed_item_id" value="<?= $item['feed_item_id'] ?>">
                                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                    <input type="hidden" name="item_type" value="<?= $item['item_type'] ?>">
                                    <button type="submit" class="btn-admin btn-delete" title="Apagar Conteúdo">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($contents)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 50px; color: #95a5a6;">
                            <i class="fas fa-inbox fa-3x" style="display: block; margin-bottom: 15px; opacity: 0.3;"></i>
                            Nenhum conteúdo encontrado para este filtro.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="premium-modal">
    <div class="premium-modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Visualização do Conteúdo</h3>
            <span class="close-modal" onclick="closeQuickView()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="preview-container">
                <div class="preview-media" id="modalMedia">
                    <!-- Media will be injected here -->
                </div>
                <div class="preview-details">
                    <div class="preview-info-row">
                        <span class="preview-info-label">Tipo de Conteúdo</span>
                        <span class="preview-info-value" id="modalType"></span>
                    </div>
                    <div class="preview-info-row">
                        <span class="preview-info-label">Autor</span>
                        <span class="preview-info-value" id="modalAuthor"></span>
                    </div>
                    <div class="preview-info-row">
                        <span class="preview-info-label">Descrição / Legenda</span>
                        <span class="preview-info-value" id="modalDescription"></span>
                    </div>
                    <div class="preview-info-row" id="modalPriceRow">
                        <span class="preview-info-label">Preço de Venda</span>
                        <span class="preview-info-value" id="modalPrice" style="color: var(--admin-warning); font-weight: bold; font-size: 1.2rem;"></span>
                    </div>
                    <div class="preview-info-row">
                        <span class="preview-info-label">Data de Publicação</span>
                        <span class="preview-info-value" id="modalDate"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" id="modalActions">
            <!-- Actions will be injected here -->
        </div>
    </div>
</div>

<script>
    function openQuickView(item) {
        const modal = document.getElementById('quickViewModal');
        const mediaContainer = document.getElementById('modalMedia');

        // Set text details
        document.getElementById('modalType').innerText = item.item_type.toUpperCase();
        document.getElementById('modalAuthor').innerText = item.username;
        document.getElementById('modalDescription').innerText = item.title || 'Sem descrição';
        document.getElementById('modalDate').innerText = item.created_at;

        if (item.is_for_sale) {
            document.getElementById('modalPriceRow').style.display = 'block';
            document.getElementById('modalPrice').innerText = parseFloat(item.price).toLocaleString('pt-PT', {
                style: 'currency',
                currency: 'MZN'
            }).replace('MZN', 'MT');
        } else {
            document.getElementById('modalPriceRow').style.display = 'none';
        }

        // Set media
        mediaContainer.innerHTML = '';
        if (item.item_type === 'video' && item.video_path) {
            const video = document.createElement('video');
            video.src = '<?= UPLOAD_URL ?>' + item.video_path;
            video.controls = true;
            video.style.width = '100%';
            video.style.maxHeight = '400px';
            video.style.borderRadius = '10px';
            video.poster = item.preview_img ? '<?= UPLOAD_URL ?>' + item.preview_img : '';
            mediaContainer.appendChild(video);
        } else if (item.preview_img) {
            const img = document.createElement('img');
            img.src = '<?= UPLOAD_URL ?>' + item.preview_img;
            img.style.width = '100%';
            img.style.borderRadius = '10px';
            mediaContainer.appendChild(img);
        } else {
            mediaContainer.innerHTML = '<div style="height: 300px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 10px; color: #ccc;"><i class="fas fa-image fa-4x"></i></div>';
        }

        // Set actions
        const footer = document.getElementById('modalActions');
        footer.innerHTML = '';

        if (!item.is_approved) {
            const approveForm = `
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="approve_content">
                <input type="hidden" name="item_id" value="${item.item_id}">
                <input type="hidden" name="item_type" value="${item.item_type}">
                <button type="submit" class="btn-admin" style="background-color: var(--admin-success); color: white; padding: 10px 25px;">
                    <i class="fas fa-check"></i> Aprovar Publicação
                </button>
            </form>
        `;
            footer.innerHTML += approveForm;
        }

        const deleteForm = `
        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja apagar este conteúdo?');">
            <input type="hidden" name="action" value="delete_content">
            <input type="hidden" name="feed_item_id" value="${item.feed_item_id}">
            <input type="hidden" name="item_id" value="${item.item_id}">
            <input type="hidden" name="item_type" value="${item.item_type}">
            <button type="submit" class="btn-admin btn-delete" style="padding: 10px 25px;">
                <i class="fas fa-trash"></i> Apagar
            </button>
        </form>
    `;
        footer.innerHTML += deleteForm;

        modal.style.display = 'block';
    }

    function closeQuickView() {
        const modal = document.getElementById('quickViewModal');
        const mediaContainer = document.getElementById('modalMedia');

        // Parar vídeos se existirem
        const videos = mediaContainer.getElementsByTagName('video');
        for (let v of videos) {
            v.pause();
            v.src = "";
            v.load();
        }

        modal.style.display = 'none';
        mediaContainer.innerHTML = '';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('quickViewModal');
        if (event.target == modal) {
            closeQuickView();
        }
    }
</script>