<?php
// includes/right-sidebar.php
// Renderiza apenas o container — o conteúdo é carregado via fetch assíncrono.
// Isto garante que o espaço da coluna está reservado no primeiro paint,
// sem bloquear o feed enquanto as queries de sugestões/álbuns correm.

if (!is_logged_in()) return;
?>

<!-- ── Right Sidebar / Widgets (Desktop Only) ────────────────────────── -->
<aside class="widgets-container" id="widgetsContainer"
    role="complementary"
    aria-label="Sugestões e conteúdo em destaque">


    <!-- Skeleton: visível enquanto o conteúdo carrega -->
    <div class="widgets-skeleton" id="widgetsSkeleton" aria-hidden="true">

        <!-- Skeleton — Sugestões -->
        <div class="widget-card">
            <div class="skel skel-title"></div>
            <div class="widget-content-list">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="skel skel-avatar"></div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
                            <div class="skel skel-line" style="width:70%;"></div>
                            <div class="skel skel-line" style="width:40%;"></div>
                        </div>
                        <div class="skel skel-btn"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Skeleton — Álbuns -->
        <div class="widget-card">
            <div class="skel skel-title"></div>
            <div class="widget-grid">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="skel skel-album-thumb"></div>
                <?php endfor; ?>
            </div>
        </div>

    </div>

    <!-- Conteúdo real: injectado pelo fetch abaixo -->
    <div id="widgetsContent" style="display:none;"></div>


</aside>

<style>
    /* ── Skeleton Widgets ───────────────────────────────────────────────── */
    .skel {
        background: linear-gradient(90deg,
                var(--bg-surface) 25%,
                var(--border) 50%,
                var(--bg-surface) 75%);
        background-size: 200% 100%;
        animation: skel-shimmer 1.4s ease infinite;
        border-radius: var(--radius-sm);
    }

    @keyframes skel-shimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    .skel-title {
        height: 14px;
        width: 55%;
        margin-bottom: var(--space-md);
    }

    .skel-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .skel-line {
        height: 10px;
        border-radius: 4px;
    }

    .skel-btn {
        width: 44px;
        height: 28px;
        border-radius: var(--radius-full);
        flex-shrink: 0;
    }

    .skel-album-thumb {
        aspect-ratio: 1;
        border-radius: var(--radius-md);
        width: 100%;
    }

    .widget-content-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .widget-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
</style>

<script>
    (function() {
        var BASE_URL = window.BASE_URL || '/';

        fetch(BASE_URL + 'api/widgets.php', {
                credentials: 'same-origin'
            })
            .then(function(r) {
                return r.ok ? r.text() : Promise.reject(r.status);
            })
            .then(function(html) {
                var skeleton = document.getElementById('widgetsSkeleton');
                var content = document.getElementById('widgetsContent');
                if (!skeleton || !content) return;

                content.innerHTML = html;

                /* Troca suave: fade-out do skeleton, fade-in do conteúdo */
                skeleton.style.transition = 'opacity 0.2s';
                skeleton.style.opacity = '0';
                setTimeout(function() {
                    skeleton.style.display = 'none';
                    content.style.display = 'flex';
                    content.style.flexDirection = 'column';
                    content.style.gap = 'var(--space-lg)';
                    content.style.opacity = '0';
                    content.style.transition = 'opacity 0.2s';
                    requestAnimationFrame(function() {
                        content.style.opacity = '1';
                    });
                }, 200);
            })
            .catch(function(err) {
                /* Em caso de erro, esconde o skeleton silenciosamente */
                var skeleton = document.getElementById('widgetsSkeleton');
                if (skeleton) skeleton.style.display = 'none';
                console.warn('[widgets] Falha ao carregar:', err);
            });
    })();
</script>