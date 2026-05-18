import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

function initRegisterPasswordRules() {
    const passwordInput = document.getElementById('registerPassword');
    const rulesBox = document.getElementById('passwordRules');

    if (!passwordInput || !rulesBox) return;

    const rules = {
        length: (value) => value.length >= 8,
        upper: (value) => /[A-Z]/.test(value),
        lower: (value) => /[a-z]/.test(value),
        number: (value) => /[0-9]/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };

    const ruleElements = {};
    rulesBox.querySelectorAll('[data-rule]').forEach((el) => {
        const key = el.getAttribute('data-rule');
        if (key) ruleElements[key] = el;
    });

    const update = () => {
        const value = passwordInput.value || '';
        Object.entries(rules).forEach(([key, fn]) => {
            const ok = fn(value);
            const el = ruleElements[key];
            if (!el) return;
            el.classList.toggle('is-ok', ok);
        });
    };

    const show = () => {
        rulesBox.style.display = 'block';
        update();
    };

    const hide = () => {
        rulesBox.style.display = 'none';
    };

    passwordInput.addEventListener('focus', show);
    passwordInput.addEventListener('input', update);
    passwordInput.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (document.activeElement !== passwordInput) {
                hide();
            }
        }, 120);
    });

    // If the server returned a password error, show the rules immediately.
    if (passwordInput.dataset.showRules === '1') {
        show();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initRegisterPasswordRules();
});
