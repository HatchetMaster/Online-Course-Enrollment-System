document.addEventListener('DOMContentLoaded', async () => {
    const navProfile = document.getElementById('navProfile');
    const navLogin = document.getElementById('navLogin');
    const logoutLink = document.getElementById('logoutLink');

    async function refreshAuth() {
        try {
            const res = await fetch('/OCES/backend/api/whoami.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                // logged in
                if (navProfile) navProfile.classList.remove('d-none');
                if (logoutLink) logoutLink.classList.remove('d-none');
                if (navLogin) navLogin.classList.add('d-none');
            } else {
                // logged out
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
