<?php

/**
 * Componente para exibir parceiros de um álbum
 * 
 * Uso:
 * <?php include 'components/album_partners_display.php'; ?>
 * 
 * Requer as variáveis:
 * - $pdo: Conexão com o banco de dados
 * - $albumId: ID do álbum
 */

if (!isset($pdo) || !isset($albumId)) {
    return;
}

use Massango\Models\AlbumPartner;

$partners = AlbumPartner::getAlbumPartners($pdo, $albumId);

if (empty($partners)) {
    return;
}

// Construir string de parceiros - apenas os aceitos
$partnersList = [];
foreach ($partners as $partner) {
    // Exibir apenas parceiros com status 'accepted'
    if ($partner['status'] === 'accepted') {
        $partnersList[] = '@' . htmlspecialchars($partner['username']);
    }
}

// Se não houver parceiros aceitos, não exibir nada
if (empty($partnersList)) {
    return;
}

$partnersText = implode(' + ', $partnersList);
?>

<div class="album-partners-display">
    <p class="partners-text">
        <strong>Por:</strong>
        <span class="partners-list"><?= $partnersText ?></span>
    </p>
</div>

<style>
    .album-partners-display {
        margin-bottom: 15px;
        padding: 10px;
        background: rgba(var(--primary-rgb), 0.05);
        border-left: 3px solid var(--primary-color);
        border-radius: 4px;
    }

    .partners-text {
        margin: 0;
        font-size: 14px;
        color: var(--text-primary);
    }

    .partners-list {
        color: var(--primary-color);
        font-weight: 500;
    }
</style>