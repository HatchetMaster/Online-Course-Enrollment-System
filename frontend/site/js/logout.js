document.addEventListener('click', async (ev) => {
    const link = ev.target.closest && ev.target.closest('#logoutLink');
    if (!link) return;

    ev.preventDefault();

    try {
        // Get current CSRF token (server stores it in session)
        let csrf = null;
        try {
            const tokenRes = await fetch('/OCES/backend/api/csrf.php', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            if (tokenRes.ok) {
                const tokenJson = await tokenRes.json().catch(() => null);
                csrf = tokenJson?.data?.csrf_token ?? null;
            }
        } catch (e) {
            // never throw here — handled below
            csrf = null;
        }

        if (!csrf) {
            alert('Logout failed: missing CSRF token. Try refreshing the page and try again.');
            return;
        }
        // Send logout request
        const res = await fetch('/OCES/backend/api/logout.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrf
            }
        });

        const json = await res.json().catch(() => null);
        if (res.ok && json?.success) {
            // Clear session data and redirect to login page
            location.replace('/OCES/frontend/site/login.html');
        } else {
            alert('Logout failed: ' + (json?.error?.message || json?.message || `Status ${res.status}`));
        }
    } catch (err) {
        console.error('Logout error', err);
        alert('Logout failed. See console.');
    }
});