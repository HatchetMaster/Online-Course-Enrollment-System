(async function () {
    try {
        const res = await fetch('/OCES/backend/api/csrf.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const json = await res.json();
        const token = json.csrf_token;

        document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
            el.value = token;
        });
    } catch (err) {
        console.error('CSRF init failed', err);
    }
})();
