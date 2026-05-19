import './bootstrap';

import Alpine from 'alpinejs';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';

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

function initAdminOrderDatePickers() {
    const inputs = document.querySelectorAll('.js-flatpickr-date');
    if (!inputs.length) return;

    inputs.forEach((el) => {
        if (el.dataset.fpBound === '1') return;
        el.dataset.fpBound = '1';

        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: true,
            disableMobile: true,
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initRegisterPasswordRules();
    initAdminOrderDatePickers();
});
