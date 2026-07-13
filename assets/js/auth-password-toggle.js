(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-password-toggle]');

        if (!button) {
            return;
        }

        var inputId = button.getAttribute('aria-controls');
        var input = inputId ? document.getElementById(inputId) : null;

        if (!input || (input.type !== 'password' && input.type !== 'text')) {
            return;
        }

        var showPassword = input.type === 'password';
        var icon = button.querySelector('.material-icons-round');

        input.type = showPassword ? 'text' : 'password';
        button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
        button.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');

        if (icon) {
            icon.textContent = showPassword ? 'visibility_off' : 'visibility';
        }
    });
}());
