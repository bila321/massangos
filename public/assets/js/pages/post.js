document.addEventListener('DOMContentLoaded', function () {
    const lb = document.getElementById('postLightboxV3');
    const sidebar = document.getElementById('v3CommentsSidebar');
    let commentsLoaded = false;

    // Revelar blur
    const rev = document.getElementById('v3RevealBtn');
    if (rev) {
        rev.onclick = () => {
            document.getElementById('v3BlurShield').remove();
            const media = document.querySelector('.va-lb-img');
            if (media) media.classList.remove('v3-blurred');
        };
    }

    // Sidebar
    window.toggleV3Sidebar = () => {
        sidebar.classList.toggle('open');
        lb.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
        if (sidebar.classList.contains('open') && !commentsLoaded) loadComments();
    };

    function loadComments() {
        fetch('api/comments.php?feed_item_id=<?= (int)$feed_item_id ?>')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    commentsLoaded = true;
                    document.getElementById('v3CommentCount').textContent = d.comments.length;
                    const container = document.getElementById('v3CommentsList');
                    container.innerHTML = d.comments.map(c => `
                            <div style="display:flex; gap:12px; margin-bottom:15px;">
                                <img src="${UPLOAD_URL + c.profile_picture}" style="width:32px; height:32px; border-radius:50%;">
                                <div style="flex:1;">
                                    <div style="background:rgba(255,255,255,0.08); padding:10px; border-radius:15px;">
                                        <div style="font-weight:700; font-size:12px; margin-bottom:2px;">${c.username}</div>
                                        <div style="font-size:13px; color:#eee;">${c.content}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                }
            });
    }

    lb.onclick = (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const act = btn.dataset.action;
        if (act === 'close-lightbox') window.history.back();
        if (act === 'toggle-comments') toggleV3Sidebar();
        if (act === 'like') {
            fetch('process_vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `feed_item_id=<?= (int)$feed_item_id ?>&vote_type=like`
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    btn.classList.toggle('active');
                    document.getElementById('v3LikeCount').textContent = d.likes;
                }
            });
        }
    };

    document.getElementById('v3CommentForm').onsubmit = (e) => {
        e.preventDefault();
        const input = document.getElementById('v3CommentInput');
        if (!input.value.trim()) return;
        const fd = new FormData();
        fd.append('feed_item_id', <?= (int)$feed_item_id ?>);
        fd.append('comment_content', input.value);
        fd.append('action', 'add_comment');
        fetch('process_comment.php', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(d => {
            if (d.success) {
                input.value = '';
                loadComments();
            }
        });
    };
});