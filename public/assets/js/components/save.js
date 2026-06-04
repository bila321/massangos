/**
 * save.js — Toggle guardar publicações
 * Depende de: window.BASE_URL
 */
async function toggleSave(btn) {
    if (btn.disabled) return;
    btn.disabled = true;

    const itemType = btn.dataset.itemType;
    const itemId = btn.dataset.itemId;
    const csrfToken = btn.dataset.csrf;
    const isSaved = btn.classList.contains('active');
    const icon = btn.querySelector('i');
    const label = btn.querySelector('span');

    // Optimistic UI
    if (isSaved) {
        btn.classList.remove('active');
        icon?.classList.replace('fa-solid', 'fa-regular');
        if (label) label.textContent = 'Guardar';
    } else {
        btn.classList.add('active');
        icon?.classList.replace('fa-regular', 'fa-solid');
        if (label) label.textContent = 'Guardado';
        icon?.animate([
            { transform: 'scale(1)' },
            { transform: 'scale(1.4)' },
            { transform: 'scale(1)' }
        ], { duration: 300, easing: 'ease-out' });
    }

    try {
        const res = await fetch(window.BASE_URL + 'ajax/toggle_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: csrfToken,
                item_type: itemType,
                item_id: itemId,
            })
        });
        const data = await res.json();

        if (!data.success) {
            if (isSaved) {
                btn.classList.add('active');
                icon?.classList.replace('fa-regular', 'fa-solid');
                if (label) label.textContent = 'Guardado';
            } else {
                btn.classList.remove('active');
                icon?.classList.replace('fa-solid', 'fa-regular');
                if (label) label.textContent = 'Guardar';
            }
        }
    } catch (err) {
        console.error('Erro ao guardar:', err);
    } finally {
        btn.disabled = false;
    }
}