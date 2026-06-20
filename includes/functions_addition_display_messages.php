<?php
/**
 * Adicionar a includes/functions.php
 *
 * Versão de display_site_messages() usada em contexto de modal AJAX
 * (edit_album, edit_post, edit_video). É a mesma função que estava
 * duplicada 3× inline em cada um desses ficheiros.
 *
 * Se já existir uma display_site_messages() "normal" (para páginas completas),
 * esta função guarda-se com outro nome para não colidir.
 */
if (!function_exists('display_site_messages_modal')) {
    function display_site_messages_modal(): void
    {
        $messages = get_and_clear_messages();
        if (empty($messages)) return;

        echo '<div class="alert-container" style="position:fixed;top:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:12px;">';
        foreach ($messages as $message) {
            $type = htmlspecialchars($message['type'] ?? 'info');
            $bg = match ($type) {
                'danger'  => 'var(--danger)',
                'success' => 'var(--success)',
                default   => 'var(--info)',
            };
            echo '<div class="alert" style="background:' . $bg . ';color:white;padding:14px 24px;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:12px;min-width:320px;animation:slideIn 0.3s cubic-bezier(0.4,0,0.2,1);">';
            echo '<span style="font-weight:500;font-size:0.95rem;">' . htmlspecialchars($message['content'] ?? '') . '</span>';
            echo '<button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;font-size:1.5rem;line-height:1;">&times;</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<style>@keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}</style>';
        echo '<script>setTimeout(()=>{document.querySelectorAll(".alert-container .alert").forEach(a=>{a.style.opacity="0";a.style.transform="translateX(20px)";a.style.transition="all 0.5s ease";});setTimeout(()=>document.querySelector(".alert-container")?.remove(),500);},5000);</script>';
    }
}
