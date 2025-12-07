(function () {
    'use strict';
    const form = document.getElementById('regForm');
    const msgEl = document.getElementById('message');
    const btn = document.getElementById('registerBtn');
    if (!form) return;

    function setMessage(text = '', type = '') {
        msgEl.textContent = text;
        msgEl.className = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted';
    }

    function clientValidation(values) {
        if (!values.firstName || !values.lastName || !values.username || !values.email || !values.password) {
            return 'Please complete all required fields.';
        }
        if (values.password.length < 8) return 'Password must be at least 8 characters.';
        if (values.password !== values.passwordConfirm) return 'Passwords do not match.';
        if (!/^[A-Za-z0-9._-]{3,64}$/.test(values.username)) {
            return 'Username contains invalid characters or is the wrong length.';
        }
        return null;
    }
    // form validation and submit handler
    form.addEventListener('submit', function (e) {
        const data = new FormData(form);
        const payload = {
            firstName: (data.get('firstName') || '').toString().trim(),
            lastName: (data.get('lastName') || '').toString().trim(),
            username: (data.get('username') || '').toString().trim(),
            email: (data.get('email') || '').toString().trim(),
            password: (data.get('password') || '').toString(),
            passwordConfirm: (data.get('passwordConfirm') || '').toString()
        };
        const err = clientValidation(payload);
        if (err) {
            e.preventDefault();
            setMessage(err, 'error');
            form.classList.add('was-validated');
            return false;
        }
        btn.disabled = true;
        return true;
    });
})();
