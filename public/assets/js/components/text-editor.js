/**
 * Text Editor with Quill.js
 * Editor de texto com formatação para posts de texto
 */

let textEditorInstance = null;

function initializeTextEditor(containerId = 'text-editor-content') {
    // Verificar se Quill.js está disponível
    if (typeof Quill === 'undefined') {
        console.error('Quill.js não foi carregado. Certifique-se de incluir a biblioteca.');
        return false;
    }

    // Configurar Quill.js
    textEditorInstance = new Quill('#' + containerId, {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ 'header': 1 }, { 'header': 2 }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'script': 'sub'}, { 'script': 'super' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                [{ 'size': ['small', false, 'large', 'huge'] }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                ['clean'],
                ['link']
            ]
        },
        placeholder: 'Compartilhe seus pensamentos...',
        bounds: '#' + containerId
    });

    // Limitar o tamanho do conteúdo
    const maxLength = 5000;
    textEditorInstance.on('text-change', function(delta, oldDelta, source) {
        const length = textEditorInstance.getLength() - 1; // -1 para não contar o \n final
        
        // Atualizar contador de caracteres
        updateTextCounter(length, maxLength);

        // Limitar o tamanho
        if (length > maxLength) {
            textEditorInstance.deleteText(maxLength, length);
        }
    });

    return true;
}

function updateTextCounter(current, max) {
    const counterEl = document.getElementById('text-editor-counter');
    if (!counterEl) return;

    counterEl.textContent = `${current} / ${max} caracteres`;
    
    if (current >= max) {
        counterEl.classList.add('danger');
        counterEl.classList.remove('warning');
    } else if (current >= max * 0.8) {
        counterEl.classList.add('warning');
        counterEl.classList.remove('danger');
    } else {
        counterEl.classList.remove('warning', 'danger');
    }
}

function getTextEditorContent() {
    if (!textEditorInstance) return '';
    
    // Obter o conteúdo HTML
    const html = textEditorInstance.root.innerHTML;
    
    // Limpar tags vazias e espaços em branco
    if (html === '<p><br></p>' || html === '' || html === '<br>') {
        return '';
    }
    
    return html;
}

function getTextEditorPlainText() {
    if (!textEditorInstance) return '';
    return textEditorInstance.getText().trim();
}

function setTextEditorContent(html) {
    if (!textEditorInstance) return false;
    textEditorInstance.root.innerHTML = html;
    return true;
}

function clearTextEditor() {
    if (!textEditorInstance) return false;
    textEditorInstance.setContents([]);
    return true;
}

function isTextEditorEmpty() {
    if (!textEditorInstance) return true;
    const text = textEditorInstance.getText().trim();
    return text === '';
}

// Função para exportar o conteúdo do editor como HTML limpo
function exportTextEditorAsHTML() {
    if (!textEditorInstance) return '';
    return textEditorInstance.root.innerHTML;
}

// Função para exportar o conteúdo do editor como texto plano
function exportTextEditorAsPlainText() {
    if (!textEditorInstance) return '';
    return textEditorInstance.getText().trim();
}

// Função para validar o conteúdo do editor
function validateTextEditorContent() {
    if (isTextEditorEmpty()) {
        return {
            valid: false,
            error: 'O post de texto não pode estar vazio.'
        };
    }

    const plainText = getTextEditorPlainText();
    if (plainText.length > 5000) {
        return {
            valid: false,
            error: 'O post excede o limite de 5000 caracteres.'
        };
    }

    if (plainText.length < 3) {
        return {
            valid: false,
            error: 'O post deve ter pelo menos 3 caracteres.'
        };
    }

    return {
        valid: true,
        error: null
    };
}

// Inicializar o editor quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Apenas inicializar se o container existir
    if (document.getElementById('text-editor-content')) {
        initializeTextEditor('text-editor-content');
    }
});
