/* logs.js — audit logs */
$(function () {
    let page = 1, limit = 20, search = '';
    function load(p) {
        page = p || 1;
        const params = new URLSearchParams({ page: String(page), limit: String(limit) });
        if (search) params.set('search', search);
        Insophinia.api.get('/api/logs?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            const tb = $('#log-tbody');
            if (!items.length) tb.html('<tr><td colspan="6" class="text-center text-muted-fg py-4">No audit logs.</td></tr>');
            else {
                tb.empty();
                items.forEach(function (l) {
                    tb.append('<tr><td><span class="badge bg-secondary">' + esc(l.action) + '</span></td><td>' + esc(l.entity) + (l.entity_id ? ' <small class="text-muted-fg">#' + esc(l.entity_id.substring(0, 8)) + '</small>' : '') + '</td><td>' + esc(l.user_username || '') + '</td><td>' + esc(l.ip_address || '') + '</td><td>' + Insophinia.format.date(l.created_at) + '</td><td class="small text-muted-fg">' + esc(l.details || '') + '</td></tr>');
                });
            }
            renderPagination(pg, '#log-pagination', '#log-pagination-info', load);
        });
    }
    let st = null;
    $('#log-search').on('input', function () { clearTimeout(st); st = setTimeout(function () { search = $(this).val().trim(); load(1); }, 300); });
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    load(1);
});
