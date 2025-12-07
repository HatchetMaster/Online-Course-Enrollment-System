document.addEventListener('DOMContentLoaded', async () => {
    const emailEl = document.getElementById('profileEmail');
    const firstEl = document.getElementById('profileFirstName');
    const lastEl = document.getElementById('profileLastName');
    const enrolledList = document.getElementById('enrolledList');
    const coursesTbody = document.getElementById('coursesTbody');
    const addCourseForm = document.getElementById('addCourseForm');
    const addCourseMsg = document.getElementById('addCourseMsg');
    // escape html  
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    try {
        // Fetch profile (includes enrolled list)
        const res = await fetch('/OCES/backend/api/profile_get.php', {
            method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) {
            emailEl.textContent = '—';
            firstEl.textContent = '—';
            lastEl.textContent = '—';
            enrolledList.innerHTML = '<li>You must be logged in to view your profile.</li>';
            coursesTbody.innerHTML = '<tr><td colspan="6">Login to see courses.</td></tr>';
            return;
        }
        const profileJson = await res.json();
        const u = profileJson.data || {};

        emailEl.textContent = u.email ?? '';
        firstEl.textContent = u.firstName ?? '';
        lastEl.textContent = u.lastName ?? '';

        // enrolled list
        const enrolled = (u.enrolled && u.enrolled.length) ? u.enrolled : [];
        enrolledList.innerHTML = enrolled.length ? enrolled.map(c => `<li>${escapeHtml(c.course_name)} (${escapeHtml(c.course_code)})</li>`).join('') : '<li>None</li>';

        // Fetch course catalog and user's enrollments/waitlist
        const [coursesRes, myEnrollRes] = await Promise.all([
            fetch('/OCES/backend/api/courses.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }),
            fetch('/OCES/backend/api/enrollments.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        ]);
        const coursesJson = coursesRes.ok ? await coursesRes.json() : { data: { courses: [] } };
        const myEnrollJson = myEnrollRes.ok ? await myEnrollRes.json() : { data: { enrolled: [], waitlist_positions: {} } };

        const courses = coursesJson.data?.courses || [];
        const myEnrolledIds = (myEnrollJson.data?.enrolled || []).map(e => Number(e.id));
        const myWaitlists = myEnrollJson.data?.waitlist_positions || {};

        // render courses rows
        if (!courses.length) {
            coursesTbody.innerHTML = '<tr><td colspan="6">No courses available.</td></tr>';
        } else {
            coursesTbody.innerHTML = courses.map(c => {
                const seatsLeft = (c.capacity === 0) ? 'Unlimited' : Math.max(0, c.capacity - c.enrolled_count);
                const enrolled = myEnrolledIds.includes(c.id);
                const waitPos = myWaitlists[c.id] ? ` (waitlist pos ${myWaitlists[c.id]})` : '';
                const action = enrolled ? 'cancel' : 'enroll';
                const btnClass = enrolled ? 'btn-danger' : 'btn-primary';
                return `
                  <tr>
                    <td>${escapeHtml(c.course_name)}</td>
                    <td>${escapeHtml(c.course_code)}</td>
                    <td>${c.capacity === 0 ? 'Unlimited' : c.capacity}</td>
                    <td>${c.enrolled_count}${c.capacity > 0 ? ' / ' + c.capacity : ''}</td>
                    <td>${seatsLeft}${waitPos}</td>
                    <td>${escapeHtml(c.course_start_date)}</td>
                    <td>${escapeHtml(c.course_end_date)}</td>
                    <td><button class="enrollBtn btn ${btnClass} btn-sm" data-course-id="${c.id}" data-action="${action}">${enrolled ? 'Cancel' : 'Enroll'}</button></td>
                  </tr>
                `;
            }).join('');
        }

        // Add course handler
        if (addCourseForm) {
            addCourseForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                addCourseMsg.textContent = '';
                const btn = document.getElementById('addCourseBtn');
                btn.disabled = true;
                const fd = new FormData(addCourseForm);
                const payload = {
                    course_name: (fd.get('course_name') || '').toString().trim(),
                    course_code: (fd.get('course_code') || '').toString().trim(),
                    capacity: Number(fd.get('capacity') || 0)
                };
                try {
                    // acquire CSRF
                    const tokenRes = await fetch('/OCES/backend/api/csrf.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    const tokenJson = await tokenRes.json();
                    const csrf = tokenJson?.data?.csrf_token;
                    if (!csrf) throw new Error('no csrf token');
                    payload.csrf_token = csrf;

                    const res = await fetch('/OCES/backend/api/course_add.php', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const j = await res.json().catch(() => null);
                    if (!res.ok) {
                        addCourseMsg.textContent = j?.error?.message || 'Could not add course.';
                        addCourseMsg.className = 'text-danger';
                        return;
                    }
                    addCourseMsg.textContent = 'Course added.';
                    addCourseMsg.className = 'text-success';
                    setTimeout(() => location.reload(), 600);
                } catch (err) {
                    addCourseMsg.textContent = 'Error adding course.';
                    addCourseMsg.className = 'text-danger';
                    console.error(err);
                } finally {
                    btn.disabled = false;
                }
            });
        }

    } catch (err) {
        console.error('profile load error', err);
    }
});
