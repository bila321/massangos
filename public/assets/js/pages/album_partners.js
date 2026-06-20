/**
 * assets/js/pages/album_partners.js
 *
 * Extraído do <script> inline de manage_album_partners.php.
 * Depende de window.ALBUM_PARTNERS_ALBUM_ID e window.BASE_URL
 * (definidos pela view antes deste script).
 */
(function () {
    'use strict';

    const albumId = window.ALBUM_PARTNERS_ALBUM_ID;
    const BASE_URL = window.BASE_URL;
    let selectedUserId = null;
    let editingPartnerId = null;

    window.openAddPartnerModal = function () {
        document.getElementById('addPartnerModal').classList.add('active');
    };

    window.closeAddPartnerModal = function () {
        document.getElementById('addPartnerModal').classList.remove('active');
        selectedUserId = null;
    };

    window.openEditPartnerModal = function (id, current) {
        editingPartnerId = id;
        document.getElementById('editPercentage').value = current;
        document.getElementById('editPartnerModal').classList.add('active');
    };

    window.closeEditPartnerModal = function () {
        document.getElementById('editPartnerModal').classList.remove('active');
    };

    // Busca de usuários
    const userSearchInput = document.getElementById('userSearch');
    if (userSearchInput) {
        userSearchInput.addEventListener('input', async function (e) {
            const q = e.target.value.trim();
            const userListEl = document.getElementById('userList');

            if (q.length < 2) {
                userListEl.innerHTML = '';
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}api/search_users.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                let html = '';

                if (data.users && data.users.length > 0) {
                    data.users.forEach(u => {
                        html += `<div class="user-item-search" data-user-id="${u.id}" data-username="${u.username}">@${u.username}</div>`;
                    });
                } else {
                    html = '<div style="padding:10px;">Nenhum usuário encontrado</div>';
                }

                userListEl.innerHTML = html;

                userListEl.querySelectorAll('.user-item-search').forEach(el => {
                    el.addEventListener('click', () => {
                        selectUser(el.dataset.userId, el.dataset.username);
                    });
                });
            } catch (err) {
                console.error(err);
            }
        });
    }

    function selectUser(id, name) {
        selectedUserId = id;
        document.getElementById('userSearch').value = '@' + name;
        document.getElementById('userList').innerHTML = '';
    }

    window.addPartner = async function () {
        if (!selectedUserId) {
            alert('Selecione um usuário');
            return;
        }
        const p = document.getElementById('partnerPercentage').value;

        const res = await fetch(`${BASE_URL}api/album_partners.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_partner&album_id=${albumId}&partner_id=${selectedUserId}&percentage=${p}`,
        });
        const data = await res.json();

        if (data.success) {
            alert('Convite enviado!');
            location.reload();
        } else {
            alert(data.message);
        }
    };

    window.respondPartnership = async function (partnerId, response) {
        const action = response === 'accept' ? 'accept_partnership' : 'reject_partnership';
        try {
            const res = await fetch(`${BASE_URL}api/album_partners.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&partner_id=${partnerId}`,
            });
            const data = await res.json();

            if (data.success) {
                alert(response === 'accept' ? 'Parceria aceita com sucesso!' : 'Parceria recusada.');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        } catch (err) {
            console.error(err);
            alert('Erro ao processar resposta.');
        }
    };

    window.updatePartner = async function () {
        const p = document.getElementById('editPercentage').value;
        const res = await fetch(`${BASE_URL}api/album_partners.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_percentage&partner_id=${editingPartnerId}&percentage=${p}`,
        });
        const data = await res.json();

        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    };

    window.removePartner = async function (id) {
        if (!confirm('Remover parceiro?')) return;

        const res = await fetch(`${BASE_URL}api/album_partners.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove_partner&partner_id=${id}`,
        });
        const data = await res.json();

        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    };

})();
