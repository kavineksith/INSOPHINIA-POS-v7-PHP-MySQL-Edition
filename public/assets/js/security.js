/* security.js — security events dashboard */
$(function () {
    Insophinia.api.get('/api/security/events?page=1&limit=100').then(function (r) {
        const items = r.data.items;
        const counts = { LOGIN_SUCCESS: 0, LOGIN_FAILED: 0, SUSPICIOUS_ACTIVITY: 0, LOGOUT: 0 };
        const tb = $('#sec-tbody');
        if (!items.length) tb.html('<tr><td colspan="6" class="text-center text-muted-fg py-4">No security events.</td></tr>');
        else {
            tb.empty();
            items.forEach(function (e) {
                if (counts[e.event_type] !== undefined) counts[e.event_type]++;
                const sevClass = { low: 'bg-secondary', medium: 'bg-info', high: 'bg-warning', critical: 'bg-danger' }[e.severity] || 'bg-secondary';
                tb.append('<tr><td><span class="badge bg-secondary">' + esc(e.event_type) + '</span></td><td>' + esc(e.ip_address) + '</td><td>' + esc(e.user_username || '') + '</td><td><span class="badge ' + sevClass + '">' + esc(e.severity) + '</span></td><td>' + Insophinia.format.date(e.created_at) + '</td><td class="small text-muted-fg">' + esc(e.details || '') + '</td></tr>');
            });
        }
        $('#sec-login-success').text(counts.LOGIN_SUCCESS);
        $('#sec-login-failed').text(counts.LOGIN_FAILED);
        $('#sec-suspicious').text(counts.SUSPICIOUS_ACTIVITY);
        $('#sec-logout').text(counts.LOGOUT);
    });
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
});
