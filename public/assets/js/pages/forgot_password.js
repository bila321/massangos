/**
 * assets/js/pages/forgot_password.js
 *
 * Extraído do <script> inline de forgot_password.php (etapa 3 — nova senha).
 * Depende de window.MIN_PASSWORD_LENGTH (definido na view, com fallback a 8).
 */
(function () {
    'use strict';

    /* ── Toggle de visibilidade da senha ── */
    document.querySelectorAll('.pw-toggle[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const inp  = document.getElementById(btn.dataset.target);
            const icon = btn.querySelector('i');
            if (!inp) return;
            const hide = inp.type === 'password';
            inp.type = hide ? 'text' : 'password';
            icon.className = hide ? 'ti ti-eye-off' : 'ti ti-eye';
            btn.setAttribute('aria-label', hide ? 'Ocultar senha' : 'Mostrar senha');
        });
    });

    /* ── Medidor de força + requisitos ── */
    (function () {
        const pwd  = document.getElementById('password');
        const conf = document.getElementById('confirm_password');
        if (!pwd) return;

        const fill  = document.getElementById('strengthFill');
        const lbl   = document.getElementById('strengthLabel');
        const match = document.getElementById('matchLabel');

        const reqLen   = document.getElementById('req-len');
        const reqUpper = document.getElementById('req-upper');
        const reqNum   = document.getElementById('req-num');

        const minLen = window.MIN_PASSWORD_LENGTH || 8;

        const levels = [
            { pct: 0,   bg: 'transparent', text: '' },
            { pct: 25,  bg: '#ef4444',     text: 'Muito fraca' },
            { pct: 50,  bg: '#f59e0b',     text: 'Fraca' },
            { pct: 75,  bg: '#3b82f6',     text: 'Razoável' },
            { pct: 100, bg: '#07c95b',     text: 'Forte' },
        ];

        function setReq(el, ok) {
            el.classList.toggle('valid', ok);
            el.querySelector('i').className = ok ? 'ti ti-circle-check' : 'ti ti-circle';
        }

        function updateMatch() {
            if (!conf.value) {
                match.textContent = '';
                match.style.color = '';
                return;
            }
            const ok = pwd.value === conf.value;
            match.textContent = ok ? '✓ As senhas coincidem' : '✗ As senhas não coincidem';
            match.style.color = ok ? 'var(--success)' : 'var(--danger)';
        }

        pwd.addEventListener('input', function () {
            const v = this.value;
            const hasLen   = v.length >= minLen;
            const hasUpper = /[A-Z]/.test(v);
            const hasNum   = /[0-9]/.test(v);
            const hasSpec  = /[^A-Za-z0-9]/.test(v);

            setReq(reqLen, hasLen);
            setReq(reqUpper, hasUpper);
            setReq(reqNum, hasNum);

            const score = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;
            const lvl = levels[score] ?? levels[0];
            fill.style.width = lvl.pct + '%';
            fill.style.background = lvl.bg;
            lbl.textContent = lvl.text;
            lbl.style.color = lvl.bg;

            updateMatch();
        });

        conf.addEventListener('input', updateMatch);

        /* Bloqueia submit se as senhas não coincidem */
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (pwd.value !== conf.value) {
                    e.preventDefault();
                    match.textContent = '✗ As senhas não coincidem';
                    match.style.color = 'var(--danger)';
                    conf.focus();
                }
            });
        }
    })();

})();
