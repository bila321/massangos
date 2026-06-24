/* ── Validação de força de password — sincronizada com SecurityManager::validateInput (PHP) ── */
(function () {
    'use strict';

    var policy = window.PASSWORD_POLICY || {
        minLength: 8, requireUppercase: true, requireLowercase: true,
        requireNumber: true, requireSymbol: false
    };

    var passwordInput = document.getElementById('password');
    if (!passwordInput) return;

    var confirmInput = document.getElementById('confirm_password');
    var formGroup = passwordInput.closest('.form-group');
    var form = passwordInput.closest('form');

    injectStyles();

    var wrap = document.createElement('div');
    wrap.className = 'pw-strength';
    wrap.innerHTML =
        '<div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>' +
        '<ul class="pw-strength-checklist">' +
            '<li data-rule="length">Mínimo ' + policy.minLength + ' caracteres</li>' +
            (policy.requireUppercase ? '<li data-rule="uppercase">Uma letra maiúscula</li>' : '') +
            (policy.requireLowercase ? '<li data-rule="lowercase">Uma letra minúscula</li>' : '') +
            (policy.requireNumber ? '<li data-rule="number">Um número</li>' : '') +
            (policy.requireSymbol ? '<li data-rule="symbol">Um símbolo (!@#$...)</li>' : '') +
        '</ul>';
    formGroup.appendChild(wrap);

    var fillEl = wrap.querySelector('.pw-strength-fill');
    var items = wrap.querySelectorAll('[data-rule]');

    function evaluate(value) {
        var rules = {
            length: value.length >= policy.minLength,
            uppercase: !policy.requireUppercase || /[A-Z]/.test(value),
            lowercase: !policy.requireLowercase || /[a-z]/.test(value),
            number: !policy.requireNumber || /[0-9]/.test(value),
            symbol: !policy.requireSymbol || /[^A-Za-z0-9]/.test(value)
        };

        items.forEach(function (li) {
            li.classList.toggle('is-valid', !!rules[li.getAttribute('data-rule')]);
        });

        var keys = Object.keys(rules);
        var passed = keys.filter(function (k) { return rules[k]; }).length;
        var pct = (passed / keys.length) * 100;

        fillEl.style.width = pct + '%';
        fillEl.classList.remove('weak', 'medium', 'strong');
        fillEl.classList.add(pct < 50 ? 'weak' : pct < 100 ? 'medium' : 'strong');

        return passed === keys.length;
    }

    passwordInput.addEventListener('input', function () {
        evaluate(passwordInput.value);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            var valid = evaluate(passwordInput.value);
            if (!valid) {
                e.preventDefault();
                passwordInput.focus();
                showError('A senha não cumpre todos os requisitos indicados abaixo.');
                return;
            }
            if (confirmInput && confirmInput.value !== passwordInput.value) {
                e.preventDefault();
                confirmInput.focus();
                showError('As senhas não coincidem.');
            }
        });
    }

    function showError(msg) {
        var existing = form.querySelector('.pw-form-error');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.className = 'pw-form-error';
        el.textContent = msg;
        form.insertBefore(el, form.firstChild);
    }

    function injectStyles() {
        if (document.getElementById('pw-strength-styles')) return;
        var style = document.createElement('style');
        style.id = 'pw-strength-styles';
        style.textContent =
            '.pw-strength{margin-top:8px;}' +
            '.pw-strength-bar{height:4px;border-radius:2px;background:#e5e7eb;overflow:hidden;}' +
            '.pw-strength-fill{height:100%;width:0;transition:width .2s,background .2s;}' +
            '.pw-strength-fill.weak{background:#ef4444;}' +
            '.pw-strength-fill.medium{background:#f59e0b;}' +
            '.pw-strength-fill.strong{background:#07c95b;}' +
            '.pw-strength-checklist{list-style:none;padding:0;margin:8px 0 0;font-size:12.5px;color:#6b7280;display:flex;flex-wrap:wrap;gap:8px;}' +
            '.pw-strength-checklist li{position:relative;padding-left:16px;}' +
            '.pw-strength-checklist li::before{content:"○";position:absolute;left:0;color:#d1d5db;}' +
            '.pw-strength-checklist li.is-valid{color:#07c95b;}' +
            '.pw-strength-checklist li.is-valid::before{content:"●";color:#07c95b;}' +
            '.pw-form-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13.5px;}';
        document.head.appendChild(style);
    }
})();
