// frontend/site/js/enroll.js
// Minimal helper: attach to buttons with .enrollBtn and data-course-id / data-action ("enroll"|"cancel")
document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest && ev.target.closest('.enrollBtn');
    if (!btn) return;

    ev.preventDefault();
    const courseId = btn.getAttribute('data-course-id');
    const action = (btn.getAttribute('data-action') || 'enroll').toLowerCase();

    if (!courseId) {
        console.error('enroll: missing course id');
        return;
    }

    try {
        // Acquire CSRF token (server endpoint issues token into session)
        const tokenRes = await fetch('/OCES/backend/api/csrf.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const tokenJson = await tokenRes.json();
        const csrf = tokenJson?.data?.csrf_token;
        if (!csrf) throw new Error('no csrf token');

        const res = await fetch('/OCES/backend/api/enroll.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ course_id: Number(courseId), action: action, csrf_token: csrf })
        });

        const j = await res.json().catch(() => null);
        if (!res.ok) {
            const msg = j?.error?.message || j?.message || `Request failed: ${res.status}`;
            alert(`Error: ${msg}`);
            return;
        }

        const data = j?.data || {};
        if (data.message === 'waitlisted' || res.status === 202) {
            const pos = data.waitlist_position ? ` (position ${data.waitlist_position})` : '';
            alert(`You have been added to the waitlist${pos}.`);
        } else if (data.message === 'enrolled') {
            alert('You are enrolled.');
        } else if (data.message === 'cancelled') {
            alert('Enrollment cancelled.');
        } else if (data.message === 'cancelled_and_promoted') {
            alert('Your cancellation promoted the next student from the waitlist.');
        } else if (data.message === 'waitlist_removed') {
            alert('You have been removed from the waitlist.');
        } else {
            alert(data.message || 'Request successful.');
        }

        // Optionally refresh the page or UI
        location.reload();
    } catch (e) {
        console.error('enroll failed', e);
        alert('Enrollment request failed.');
    }
});