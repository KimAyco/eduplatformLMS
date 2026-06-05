document.addEventListener('DOMContentLoaded', function () {
    var drawer = document.getElementById('moodleDrawer');
    var overlay = document.getElementById('drawerOverlay');
    var toggle = document.getElementById('drawerToggle');
    var userBtn = document.getElementById('userMenuBtn');
    var dropdown = document.getElementById('userDropdown');

    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            drawer.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
        });
    }
    if (userBtn && dropdown) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            dropdown.classList.remove('open');
        });
    }

    document.querySelectorAll('[data-toast]').forEach(function (toast) {
        var close = toast.querySelector('.toast-close');
        if (close) {
            close.addEventListener('click', function () {
                toast.remove();
            });
        }
        setTimeout(function () {
            toast.classList.add('fade-out');
            setTimeout(function () { toast.remove(); }, 400);
        }, 5000);
    });

    initPasswordToggles();
    initSchoolCodeSuggestion();
    initCoursePage();
});

function initCoursePage() {
    var formPanel = document.getElementById('courseFormPanel');
    if (formPanel) {
        formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var firstInput = formPanel.querySelector('input[name="title"], textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }
}

function initSchoolCodeSuggestion() {
    var nameInput = document.getElementById('school_name');
    var codeInput = document.getElementById('school_code');
    if (!nameInput || !codeInput) {
        return;
    }

    var codeTouched = codeInput.value.trim() !== '';

    codeInput.addEventListener('input', function () {
        codeTouched = true;
        codeInput.value = codeInput.value.toUpperCase();
    });

    nameInput.addEventListener('input', function () {
        if (codeTouched) {
            return;
        }
        var code = nameInput.value.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 20);
        codeInput.value = code;
    });
}

function initPasswordToggles() {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.toggleBound === '1') {
            return;
        }
        input.dataset.toggleBound = '1';

        var parent = input.parentElement;
        if (parent && parent.classList.contains('password-field')) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'password-field';
        parent.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'password-toggle';
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';

        btn.addEventListener('click', function () {
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.innerHTML = visible
                ? '<i class="fa-solid fa-eye"></i>'
                : '<i class="fa-solid fa-eye-slash"></i>';
            btn.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        });

        wrap.appendChild(btn);
    });
}
