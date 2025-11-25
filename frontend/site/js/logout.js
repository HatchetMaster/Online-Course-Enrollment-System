document.addEventListener('DOMContentLoaded', () => {
    const link = document.getElementById('logoutLink');
    if (!link) return;

    link.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            const res = await fetch('/OCES/backend/api/logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (json.success) {
                window.location.href = '/OCES/frontend/site/login.html';
            } else {
                alert('Logout failed: ' + json.message);
            }
        } catch (err) {
            console.error('Logout error', err);
            alert('Logout failed. See console.');
        }
    });
});
