<?php
/* ══════════════════════════════════════════════════════════════
   WIDGET 1 — Informações do perfil
   Mostra: localização, aniversário, website, género
   Cada campo só aparece se o dono do perfil activou a visibilidade
   Ficheiro: public/components/profile/widget-info.php
   ══════════════════════════════════════════════════════════════ */

// Segurança: garante que $profile_data e $is_owner existem
if (!isset($profile_data)) return;

// Recolhe os campos (vazios ou nulos são ignorados)
$w1_location   = trim($profile_data['location']   ?? '');
$w1_website    = trim($profile_data['website']     ?? '');
$w1_birth_date = trim($profile_data['birth_date']  ?? '');
$w1_gender     = $profile_data['gender'] ?? null;

$w1_show_location   = !empty($profile_data['show_location']);
$w1_show_birth_date = !empty($profile_data['show_birth_date']);
$w1_show_website    = !empty($profile_data['show_website']);
$w1_show_gender     = !empty($profile_data['show_gender']);

// Monta os itens visíveis
$w1_items = [];

if ($w1_show_location && $w1_location !== '') {
    $w1_items[] = [
        'icon'  => 'fa-solid fa-location-dot',
        'label' => htmlspecialchars($w1_location, ENT_QUOTES, 'UTF-8'),
        'link'  => null,
    ];
}

if ($w1_show_birth_date && $w1_birth_date !== '') {
    // BUG FIX: strftime é deprecated no PHP 8.1+ e inconsistente entre SO.
    // Usamos sempre a tabela de meses para garantir português em todos os ambientes.
    $months_pt = [
        'Janeiro',
        'Fevereiro',
        'Março',
        'Abril',
        'Maio',
        'Junho',
        'Julho',
        'Agosto',
        'Setembro',
        'Outubro',
        'Novembro',
        'Dezembro'
    ];
    $ts = strtotime($w1_birth_date);
    $w1_date_fmt = $ts
        ? date('d', $ts) . ' de ' . $months_pt[(int)date('n', $ts) - 1]
        : htmlspecialchars($w1_birth_date, ENT_QUOTES, 'UTF-8');
    $w1_items[] = [
        'icon'  => 'fa-solid fa-cake-candles',
        'label' => $w1_date_fmt,
        'link'  => null,
    ];
}

if ($w1_show_website && $w1_website !== '') {
    // Garante que o link tem protocolo
    $w1_href = preg_match('#^https?://#i', $w1_website)
        ? $w1_website
        : 'https://' . $w1_website;
    // Label curto (sem https://)
    $w1_label_site = preg_replace('#^https?://(www\.)?#i', '', rtrim($w1_website, '/'));
    $w1_items[] = [
        'icon'  => 'fa-solid fa-link',
        'label' => htmlspecialchars($w1_label_site, ENT_QUOTES, 'UTF-8'),
        'link'  => htmlspecialchars($w1_href, ENT_QUOTES, 'UTF-8'),
    ];
}

if ($w1_show_gender && $w1_gender !== null) {
    $w1_gender_labels = [
        'male'              => 'Masculino',
        'female'            => 'Feminino',
        'other'             => 'Outro',
        'prefer_not_to_say' => 'Prefere não dizer',
    ];
    $w1_items[] = [
        'icon'  => 'fa-solid fa-user',
        'label' => $w1_gender_labels[$w1_gender] ?? ucfirst($w1_gender),
        'link'  => null,
    ];
}

// Só renderiza se houver pelo menos 1 item visível
// OU se for o dono do perfil (para mostrar o estado vazio + link para settings)
$w1_has_content = !empty($w1_items);
if (!$w1_has_content && !$is_owner) return;
?>

<!-- ══ WIDGET 1 — INFORMAÇÕES DO PERFIL ══ -->
<aside class="w1-card" aria-label="Informações do perfil">

    <div class="w1-header">
        <span class="w1-title">Informações</span>
        <?php if ($is_owner): ?>
            <a href="<?= BASE_URL ?>settings.php#info" class="w1-edit-btn"
                title="Editar informações" aria-label="Editar informações do perfil">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($w1_has_content): ?>
        <ul class="w1-list" role="list">
            <?php foreach ($w1_items as $w1_item): ?>
                <li class="w1-item">
                    <span class="w1-icon" aria-hidden="true">
                        <i class="<?= $w1_item['icon'] ?>"></i>
                    </span>
                    <?php if ($w1_item['link']): ?>
                        <a href="<?= $w1_item['link'] ?>"
                            class="w1-link"
                            target="_blank"
                            rel="noopener noreferrer">
                            <?= $w1_item['label'] ?>
                        </a>
                    <?php else: ?>
                        <span class="w1-text"><?= $w1_item['label'] ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php elseif ($is_owner): ?>
        <!-- Estado vazio — só visível para o dono -->
        <p class="w1-empty">
            Adiciona a tua localização, aniversário ou website para que os teus seguidores te conheçam melhor.
        </p>
        <a href="<?= BASE_URL ?>settings.php#info" class="w1-cta">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Adicionar informações
        </a>
    <?php endif; ?>

</aside>

<style>
    /* ══ Widget 1 — Informações do perfil ══════════════════════════ */
    .w1-card {
        background: var(--bg-card, #1e1e2e);
        border-radius: var(--radius-lg, 14px);
        padding: 18px 16px 16px;
        margin-bottom: 12px;
        box-sizing: border-box;
        width: 100%;
    }

    /* Cabeçalho */
    .w1-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .w1-title {
        font-size: .92rem;
        font-weight: 700;
        color: var(--text-main, #fff);
        line-height: 1;
    }

    .w1-edit-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--bg-surface, rgba(255, 255, 255, .07));
        color: var(--text-muted, #888);
        text-decoration: none;
        font-size: .72rem;
        transition: background .18s, color .18s;
        flex-shrink: 0;
    }

    .w1-edit-btn:hover {
        background: var(--primary, #07c95b);
        color: #fff;
    }

    /* Lista de itens */
    .w1-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 11px;
    }

    .w1-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .w1-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--bg-surface, rgba(255, 255, 255, .07));
        flex-shrink: 0;
        font-size: .75rem;
        color: var(--primary, #07c95b);
    }

    .w1-text {
        font-size: .85rem;
        color: var(--text-main, #e4e6ea);
        line-height: 1.3;
        word-break: break-word;
    }

    .w1-link {
        font-size: .85rem;
        color: var(--primary, #07c95b);
        text-decoration: none;
        line-height: 1.3;
        word-break: break-all;
        transition: opacity .15s;
    }

    .w1-link:hover {
        opacity: .8;
        text-decoration: underline;
    }

    /* Estado vazio (só dono) */
    .w1-empty {
        font-size: .82rem;
        color: var(--text-muted, #888);
        line-height: 1.5;
        margin: 0 0 12px;
    }

    .w1-cta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .82rem;
        font-weight: 600;
        color: var(--primary, #07c95b);
        text-decoration: none;
        padding: 6px 12px;
        border-radius: var(--radius-sm, 8px);
        background: var(--primary-soft, rgba(7, 201, 91, .1));
        transition: background .18s;
    }

    .w1-cta:hover {
        background: var(--primary-soft, rgba(7, 201, 91, .2));
    }
</style>
<!-- ══ /WIDGET 1 ══ -->