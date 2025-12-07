document.addEventListener('DOMContentLoaded', async () => {
    try {
        // get csrf token
        const res = await fetch('/OCES/backend/api/csrf.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const j = await res.json();
        const token = j?.data?.csrf_token;
        // set token in hidden input
        if (token) {
            const el = document.querySelector('input[name="csrf_token"]');
            if (el) el.value = token;
            // also store on form dataset for JS submiters
            const f = document.getElementById('loginForm');
            if (f) f.dataset.csrf = token;
        } else {
            console.warn('csrf: no token returned');
        }
    } catch (e) {
        console.error('csrf fetch failed', e);
    }
});
