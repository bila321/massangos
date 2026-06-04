/**
 * Post Modal Loader - massango
 * Intercepts clicks on post.php?id= links and opens them in a modal popup
 */

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('postModal');
    const modalBody = document.getElementById('postModalBody');
    // Use delegation for close buttons since they might be loaded via AJAX
    function setupCloseButtons() {
        const closeButtons = document.querySelectorAll('.post-modal-close');
        closeButtons.forEach(btn => {
            // Remove existing listener to avoid duplicates
            btn.removeEventListener('click', closeModal);
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal();
            });
        });
    }

    if (!modal || !modalBody) {
        console.warn('Post modal elements not found');
        return;
    }

    /**
     * Open modal with AJAX content
     */
    function openModal(url) {
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        modal.style.transition = 'opacity 0.3s ease';
        // Force reflow
        modal.offsetHeight;
        modal.style.opacity = '1';
        document.body.style.overflow = 'hidden';
        modalBody.innerHTML = '<div class="modal-loader"><div class="loader-spinner"></div></div>';

        // Ensure ajax=1 parameter
        const ajaxUrl = new URL(url, window.location.origin);
        ajaxUrl.searchParams.set('ajax', '1');

        fetch(ajaxUrl.toString())
            .then(response => {
                if (!response.ok) throw new Error('Erro ao carregar conteúdo');
                return response.text();
            })
            .then(html => {
                // Create temporary container
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                // Clear modal body
                modalBody.innerHTML = '';

                // Move non-script nodes to modal
                Array.from(tempDiv.childNodes).forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'SCRIPT') {
                        modalBody.appendChild(node.cloneNode(true));
                    } else if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                        // Keep text nodes if they have content
                        modalBody.appendChild(node.cloneNode(true));
                    }
                });

                // Extract and execute scripts
                const scripts = tempDiv.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');

                    // Copy attributes
                    Array.from(oldScript.attributes).forEach(attr => {
                        newScript.setAttribute(attr.name, attr.value);
                    });

                    // Copy content
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }

                    // Append to body to execute
                    document.body.appendChild(newScript);

                    // Clean up after execution
                    setTimeout(() => {
                        if (newScript.parentNode) {
                            newScript.parentNode.removeChild(newScript);
                        }
                    }, 100);
                });

                // Setup close buttons that were just loaded
                setupCloseButtons();

                // Dispatch custom event
                document.dispatchEvent(new CustomEvent('postModalLoaded', {
                    detail: { url: url, content: modalBody.innerHTML }
                }));
            })
            .catch(error => {
                console.error('Modal load error:', error);
                modalBody.innerHTML = '<div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">' +
                    '<strong>Erro:</strong> ' + error.message +
                    '</div>';
            });
    }

    /**
     * Close modal
     */
    function closeModal() {
        // Add a small fade out effect if desired, or just hide
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.opacity = '1'; // Reset for next open
            document.body.style.overflow = 'auto';
            modalBody.innerHTML = '';

            // Remove the hash or clean up URL if needed without reload
            if (window.location.search.includes('id=')) {
                const newUrl = window.location.pathname;
                window.history.pushState({}, '', newUrl);
            }
        }, 300);
    }

    /**
     * Intercept clicks on post links
     */
    document.body.addEventListener('click', function (e) {
        const target = e.target;

        // Check for data-post-modal attribute
        const modalTrigger = target.closest('[data-post-modal]');
        if (modalTrigger) {
            const postId = modalTrigger.getAttribute('data-post-modal');
            if (postId) {
                e.preventDefault();
                e.stopPropagation();
                const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '/';
                openModal(baseUrl + 'post.php?id=' + postId);
                return;
            }
        }

        // Check for regular post.php?id= links
        const link = target.closest('a');
        if (link && link.href && link.href.includes('post.php?id=')) {
            // Ignore edit links and modifier clicks
            if (link.href.includes('edit_post.php') || e.ctrlKey || e.metaKey) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            openModal(link.href);
        }
    }, true); // Use capture phase

    // Initial setup for close buttons
    setupCloseButtons();

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // Expose functions globally
    window.postModalLoader = {
        open: openModal,
        close: closeModal
    };
});
