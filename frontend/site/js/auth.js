async function _fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    let json = null;
    try {
        json = await res.json();
    } catch (e) {
    }
    if (!res.ok) {
        const errMsg = (json && json.message) ? json.message : `Request failed: ${res.status}`;
        throw new Error(errMsg);
    }
    return json;
}

async function apiRegister(payload) {
    return _fetchJson('/OCES/backend/api/registration.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
    });
}

async function apiLogin(payload) {
    return _fetchJson('/OCES/backend/api/login_form.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
        credentials: 'same-origin'
    });
}

async function apiLogout() {
    return _fetchJson('/OCES/backend/api/logout.php', {
        method: 'POST', credentials: 'same-origin'
    });
}
