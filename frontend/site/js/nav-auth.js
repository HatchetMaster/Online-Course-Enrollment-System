document.addEventListener('DOMContentLoaded', async () => {
    const navProfile = document.getElementById('navProfile');
    const navLogin = document.getElementById('navLogin');
    const logoutLink = document.getElementById('logoutLink');
    // refresh auth status
    async function refreshAuth() {
        try {
            const res = await fetch('/OCES/backend/api/whoami.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json().catch(() => null);
            
            const loggedIn = res.ok && json && json.success;
            if (loggedIn) {
                if (navProfile) navProfile.classList.remove('d-none');
                if (logoutLink) logoutLink.classList.remove('d-none');
                if (navLogin) navLogin.classList.add('d-none');
            } else {
                if (navProfile) navProfile.classList.add('d-none');
                if (logoutLink) logoutLink.classList.add('d-none');
                if (navLogin) navLogin.classList.remove('d-none');
            }
        } catch (e) {
            // on error assume logged out
            if (navProfile) navProfile.classList.add('d-none');
            if (logoutLink) logoutLink.classList.add('d-none');
            if (navLogin) navLogin.classList.remove('d-none');
        }
    }

    refreshAuth();
});
