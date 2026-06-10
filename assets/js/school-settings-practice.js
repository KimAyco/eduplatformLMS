(function () {
    var form = document.getElementById('practiceSettingForm');
    var switchEl = document.getElementById('practiceSwitch');
    var valueInput = document.getElementById('practiceEnabledValue');
    var passwordHidden = document.getElementById('practiceAdminPasswordHidden');
    var dialog = document.getElementById('practicePasswordDialog');
    var passwordForm = document.getElementById('practicePasswordForm');
    var passwordInput = document.getElementById('practiceAdminPassword');
    var messageEl = document.getElementById('practicePasswordMessage');

    if (!form || !switchEl || !dialog || !passwordForm) return;

    var card = document.getElementById('practiceSettingCard');
    var statusBadge = document.getElementById('practiceStatusBadge');
    var switchLabel = document.getElementById('practiceSwitchLabel');
    var currentEnabled = form.dataset.practiceEnabled === '1';
    var pendingEnabled = currentEnabled;

    function setSwitchState(enabled) {
        switchEl.checked = enabled;
        switchEl.setAttribute('aria-checked', enabled ? 'true' : 'false');
        if (card) {
            card.classList.toggle('is-enabled', enabled);
            card.classList.toggle('is-disabled', !enabled);
        }
        if (statusBadge) {
            statusBadge.textContent = enabled ? 'Enabled' : 'Disabled';
            statusBadge.dataset.state = enabled ? 'on' : 'off';
        }
        if (switchLabel) {
            switchLabel.textContent = enabled ? 'On' : 'Off';
        }
    }

    function openDialog(enabled) {
        pendingEnabled = enabled;
        if (messageEl) {
            messageEl.textContent = enabled
                ? 'Enter your password to enable practice quizzes for students.'
                : 'Enter your password to disable practice quizzes for students.';
        }
        if (passwordInput) {
            passwordInput.value = '';
        }
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            if (passwordInput) {
                passwordInput.focus();
            }
        }
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        }
        setSwitchState(currentEnabled);
    }

    switchEl.addEventListener('change', function () {
        var intended = switchEl.checked;
        setSwitchState(currentEnabled);
        openDialog(intended);
    });

    document.querySelectorAll('[data-close-practice-password]').forEach(function (btn) {
        btn.addEventListener('click', closeDialog);
    });

    passwordForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!passwordInput || passwordInput.value === '') {
            passwordInput.focus();
            return;
        }
        if (valueInput) {
            valueInput.value = pendingEnabled ? '1' : '0';
        }
        if (passwordHidden) {
            passwordHidden.value = passwordInput.value;
        }
        form.submit();
    });
})();
