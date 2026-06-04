/**
 * Sistema de "Ver mais" para descrições longas - Massango
 * Padronizado para todos os tipos de publicações
 */

(function() {
    const MAX_LENGTH = 200; // Número de caracteres antes de truncar
    const MAX_LINES = 3;    // Número máximo de linhas visíveis

    /**
     * Inicializa o sistema de "Ver mais" para elementos de descrição
     */
    function initTruncation() {
        // Selecionamos todos os elementos de descrição
        const descriptionElements = document.querySelectorAll(
            '.post-text:not([data-truncate-init])'
        );

        descriptionElements.forEach(element => {
            // Pular se já foi processado
            if (element.dataset.truncateInit === 'true') return;
            
            // Marcar como processado
            element.dataset.truncateInit = 'true';

            // Obter o texto completo
            const fullText = element.innerHTML.trim();
            const plainText = element.textContent.trim();

            // Verificar se precisa truncar
            if (plainText.length > MAX_LENGTH) {
                // Armazenar o conteúdo completo
                element.dataset.fullContent = fullText;
                
                // Criar versão truncada
                let truncatedText = plainText.substring(0, MAX_LENGTH);
                
                // Tentar cortar no último espaço para não quebrar palavras
                const lastSpace = truncatedText.lastIndexOf(' ');
                if (lastSpace > MAX_LENGTH * 0.7) {
                    truncatedText = truncatedText.substring(0, lastSpace);
                }

                // Adicionar classe de truncado
                element.classList.add('truncated');

                // Substituir conteúdo com versão truncada
                element.innerHTML = truncatedText + '... ';

                // Criar e adicionar link "Ver mais"
                const readMoreLink = document.createElement('a');
                readMoreLink.href = '#';
                readMoreLink.className = 'read-more-link';
                readMoreLink.textContent = 'Ver mais';
                readMoreLink.dataset.expanded = 'false';

                readMoreLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const isExpanded = readMoreLink.dataset.expanded === 'true';

                    if (isExpanded) {
                        // Voltar ao estado truncado
                        let truncatedText = plainText.substring(0, MAX_LENGTH);
                        const lastSpace = truncatedText.lastIndexOf(' ');
                        if (lastSpace > MAX_LENGTH * 0.7) {
                            truncatedText = truncatedText.substring(0, lastSpace);
                        }
                        element.innerHTML = truncatedText + '... ';
                        element.appendChild(readMoreLink);
                        element.classList.add('truncated');
                        readMoreLink.textContent = 'Ver mais';
                        readMoreLink.dataset.expanded = 'false';
                    } else {
                        // Expandir para mostrar tudo
                        element.innerHTML = element.dataset.fullContent;
                        element.appendChild(readMoreLink);
                        element.classList.remove('truncated');
                        readMoreLink.textContent = 'Ver menos';
                        readMoreLink.dataset.expanded = 'true';
                    }
                });

                element.appendChild(readMoreLink);
            }
        });
    }

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTruncation);
    } else {
        initTruncation();
    }

    // Observador para novos elementos adicionados dinamicamente (AJAX, Lightbox, etc)
    const observer = new MutationObserver(function(mutations) {
        // Verificar se há novos elementos de descrição
        const hasNewDescriptions = mutations.some(mutation => {
            return Array.from(mutation.addedNodes).some(node => {
                if (node.nodeType === 1) { // Element node
                    return node.classList && (
                        node.classList.contains('post-text') ||
                        node.classList.contains('post-card') ||
                        node.classList.contains('post-content')
                    );
                }
                return false;
            });
        });

        if (hasNewDescriptions) {
            initTruncation();
        }
    });

    // Configurar observador
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: false,
        characterData: false
    });

    // Expor função globalmente para uso manual se necessário
    window.initDescriptionTruncation = initTruncation;
})();
