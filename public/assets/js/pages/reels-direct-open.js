/**
 * reels-direct-open.js
 *
 * Abre o lightbox de Reels automaticamente ao entrar em reels.php,
 * sem mostrar grid. Os triggers (sem nenhum recurso real carregado)
 * já existem no DOM via includes/views/reels/_triggers_only.php —
 * este script só dispara o clique programático que normalmente
 * viria de um clique real do utilizador no grid.
 *
 * Depende de:
 *   - window.reelsManagerInstance (criado por premium_lightbox.js,
 *     no DOMContentLoaded — por isso este script TEM de ser carregado
 *     depois de premium_lightbox.js no HTML).
 *   - #reels-triggers .lightbox-trigger (gerados por _triggers_only.php)
 */
(function () {
    'use strict';

    function openFirstReel() {
        const manager = window.reelsManagerInstance;
        if (!manager || typeof manager.openReels !== 'function') {
            console.warn('[Reels] reelsManagerInstance não disponível — lightbox não foi aberto automaticamente.');
            return;
        }

        const firstTrigger = document.querySelector('#reels-triggers .lightbox-trigger');
        if (!firstTrigger) {
            console.warn('[Reels] Nenhum trigger encontrado — não há reels para mostrar.');
            return;
        }

        manager.openReels(firstTrigger);
    }

    // premium_lightbox.js cria window.reelsManagerInstance dentro do
    // seu próprio listener DOMContentLoaded. Para garantir que já
    // correu antes de tentarmos usá-lo, esperamos pelo mesmo evento
    // e adicionamos um pequeno atraso de segurança (0ms via setTimeout
    // já é suficiente para passar para o fim da fila de listeners,
    // mas usamos um valor pequeno explícito para maior margem).
    function init() {
        setTimeout(openFirstReel, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
