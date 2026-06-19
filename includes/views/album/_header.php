<?php
/** @var array $author @var array $content_data @var array|null $ai_analysis @var string $album_risk_level @var float $album_explicit_pct @var bool $is_owner @var int $album_id */
?>
<!-- ── Header do álbum ── -->
<div class="va-header">
    <a href="javascript:history.back()" class="va-back-btn" title="Voltar">
        <i class="fa-solid fa-arrow-left"></i>
    </a>

    <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'profiles/default_profile.png') ?>"
        class="va-header-avatar" alt="<?= htmlspecialchars($author['username']) ?>">

    <div class="va-header-info">
        <h1 class="va-header-title"><?= htmlspecialchars($content_data['album_name'] ?? 'Álbum') ?></h1>
        <div class="va-header-context">
            Álbum de&nbsp;<a href="<?= BASE_URL ?>profile.php?id=<?= (int)$author['id'] ?>"
                class="va-header-author-link">@<?= htmlspecialchars($author['username']) ?></a>
        </div>
        <div class="va-header-meta">
            <span><i class="fa-solid fa-image"></i> <?= count($photos) ?> foto<?= count($photos) !== 1 ? 's' : '' ?></span>
            <span><i class="fa-solid fa-eye"></i> <?= (int)$content_data['views_count'] ?></span>
            <?php if ($is_owner): ?>
                <button onclick="document.getElementById('addPhotoModal').classList.add('open')"
                    style="background:none;border:none;color:var(--accent,#00f28f);cursor:pointer;font-size:13px;display:flex;align-items:center;gap:5px;padding:0;">
                    <i class="fa-solid fa-plus"></i> Adicionar foto
                </button>
            <?php endif; ?>
        </div>

        <?php if ($ai_analysis && $ai_analysis['status'] === 'done' && in_array($album_risk_level, ['medium', 'high'])): ?>
            <?php
                $risk_label = $album_risk_level === 'high' ? 'Alto' : 'Médio';
                $risk_icon  = $album_risk_level === 'high' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation';
            ?>
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span class="va-explicit-badge risk-<?= $album_risk_level ?>">
                    <i class="fa-solid <?= $risk_icon ?>"></i>
                    Conteúdo <?= $risk_label ?> &mdash; <?= round($album_explicit_pct) ?>%
                </span>
            </div>
        <?php elseif ($ai_analysis && in_array($ai_analysis['status'], ['pending', 'processing'])): ?>
            <div style="margin-top:8px;font-size:0.75rem;color:var(--text-muted,#888);display:flex;align-items:center;gap:5px;">
                <i class="fa-solid fa-circle-notch fa-spin" style="font-size:0.7rem;"></i>
                A analisar conteúdo…
            </div>
        <?php endif; ?>
    </div>
</div>
