(async function () {
    try {
        const res = await fetch('/OCES/backend/api/flash.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) return;
        const json = await res.json();
        const flash = json.flash;
        if (!flash) return;
        // add flash message to DOM
        const container = document.getElementById('message') || (function () {
            const form = document.querySelector('form');
            if (!form) return null;
            const div = document.createElement('div');
            div.id = 'message';
            form.parentNode.insertBefore(div, form.nextSibling);
            return div;
        })();
        if (!container) return;
        // update DOM
        const key = Object.keys(flash)[0];
        const messageText = flash[key];
        container.textContent = messageText;
        container.className = key === 'error' ? 'text-danger' :
            key === 'success' ? 'text-success' : 'text-muted';
        container.setAttribute('role', 'status');
    } catch (err) {
        console.error('flash.js error', err);
    }
})();
