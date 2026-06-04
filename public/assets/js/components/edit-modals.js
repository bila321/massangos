/**
 * Edit Modals Manager - Massango Project
 * Handles AJAX loading for edit_album.php, edit_video.php, and edit_post.php
 */

function openEditModal(type, id) {
    // 1. Check for existing modal container
    let modal = document.getElementById('editModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'editModal';
        modal.className = 'edit-modal';
        modal.innerHTML = `
            <div class="edit-modal-content" id="editModalContent">
                <div class="edit-modal-loader" style="padding: 60px; text-align: center;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 2.5rem; color: var(--primary-color, #01a649);"></i>
                    <p style="margin-top: 20px; color: var(--text-secondary, #94a3b8);">Carregando formulário...</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Close on overlay click
        modal.onclick = function(event) {
            if (event.target == modal) closeEditModal();
        }
    }

    // 2. Show modal with loader
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    // 3. Determine URL
    let url = '';
    if (type === 'post') url = 'edit_post.php';
    else if (type === 'video') url = 'edit_video.php';
    else if (type === 'album') url = 'edit_album.php';

    // 4. Fetch content via AJAX
    fetch(`${url}?id=${id}&ajax=1`)
        .then(response => {
            if (!response.ok) throw new Error('Erro ao carregar conteúdo');
            return response.text();
        })
        .then(html => {
            const contentArea = document.getElementById('editModalContent');
            contentArea.innerHTML = html;
            
            // Re-initialize any specific scripts if needed
            const scripts = contentArea.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                document.body.appendChild(newScript);
                setTimeout(() => newScript.remove(), 500);
            });

            // Focus first input
            const firstInput = contentArea.querySelector('input, textarea');
            if (firstInput) firstInput.focus();
        })
        .catch(error => {
            console.error('Modal Error:', error);
            document.getElementById('editModalContent').innerHTML = `
                <div class="edit-modal-header">
                    <h2>Erro ao Carregar</h2>
                    <button type="button" class="edit-modal-close" onclick="closeEditModal()">&times;</button>
                </div>
                <div class="edit-modal-body" style="text-align: center; padding: 40px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; color: var(--accent-color, #f43f5e); margin-bottom: 20px;"></i>
                    <p>Não foi possível carregar o formulário de edição.</p>
                    <button class="btn-modal-cancel" onclick="closeEditModal()" style="margin-top: 20px;">Fechar</button>
                </div>
            `;
        });
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        // Reset content after transition
        setTimeout(() => {
            const content = document.getElementById('editModalContent');
            if (content) content.innerHTML = '<div style="padding: 60px; text-align: center;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size: 2.5rem; color: var(--primary-color, #01a649);"></i><p style="margin-top: 20px;">Carregando...</p></div>';
        }, 300);
    }
}

// Global escape listener
document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") closeEditModal();
});

// Intercept form submissions inside modal to keep it AJAX
document.addEventListener('submit', function(e) {
    if (e.target.closest('#editModalContent') && e.target.classList.contains('edit-form')) {
        // Optional: Implement AJAX submission here if you want to stay in modal after save
        // For now, let it submit normally which will redirect/reload page
    }
});

// Intercept clicks on edit links to open in modal
document.addEventListener('click', function(e) {
    const target = e.target.closest('a');
    if (!target || !target.href) return;

    const url = new URL(target.href, window.location.origin);
    const path = url.pathname;
    const params = new URLSearchParams(url.search);
    const id = params.get('id');

    if (id) {
        if (path.includes('edit_post.php')) {
            e.preventDefault();
            openEditModal('post', id);
        } else if (path.includes('edit_video.php')) {
            e.preventDefault();
            openEditModal('video', id);
        } else if (path.includes('edit_album.php')) {
            e.preventDefault();
            openEditModal('album', id);
        }
    }
});
