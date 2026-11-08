/* customers.js — CRUD for customers with soft-delete + trashed filter */
$(function () {
    let page = 1, limit = 20, search = '', optin = '', trashed = 'hide';
    const tbody = $('#cust-tbody');
    const trashedSelect = $('#cust-trashed-filter');

    function load(p) {
        page = p || 1;
        const params = new URLSearchParams({ page: String(page), limit: String(limit), trashed: trashed });
        if (search) params.set('search', search);
        if (optin) params.set('opted_in', optin);
        Insophinia.api.get('/api/customers?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            trashedSelect.toggleClass('trashed-only-active', trashed === 'only');
            trashedSelect.toggleClass('trashed-with-active', trashed === 'with');
            if (!items.length) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted-fg py-4">' + (trashed === 'only' ? 'No deleted customers.' : 'No customers.') + '</td></tr>');
            } else {
                tbody.empty();
                items.forEach(function (c) {
                    const isDel = !!c.deleted_at;
                    const tr = $('<tr>').toggleClass('row-deleted', isDel);
                    tr.append('<td class="fw-semibold">' + esc(c.name) + (isDel ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
                    tr.append('<td>' + esc(c.phone || '') + '</td>');
                    tr.append('<td>' + esc(c.email || '') + '</td>');
                    tr.append('<td class="text-end">' + (c.loyalty_points || 0) + '</td>');
                    tr.append('<td>' + (c.marketing_opt_in ? '<span class="badge bg-success">In</span>' : '<span class="badge bg-secondary">Out</span>') + '</td>');
                    tr.append('<td>' + Insophinia.format.dateOnly(c.created_at) + '</td>');
                    const activeActions = '<span class="row-active-actions">' +
                        '<button class="btn btn-sm btn-ghost edit-cust" data-id="' + c.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-pencil"></i></button> ' +
                        '<button class="btn btn-sm btn-ghost text-destructive del-cust" data-id="' + c.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                        '</span>';
                    const deletedActions = '<span class="row-deleted-actions">' +
                        '<button class="btn btn-sm btn-restore restore-cust" data-id="' + c.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                        (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-cust" data-id="' + c.id + '" data-name="' + esc(c.name) + '" title="Permanently delete"><i class="bi bi-x-octagon"></i></button>' : '') +
                        '</span>';
                    tr.append('<td class="text-end">' + activeActions + deletedActions + '</td>');
                    tbody.append(tr);
                });
            }
            Insophinia.renderPagination(pg, '#cust-pagination', '#cust-pagination-info', load);
        });
    }
    let st = null;
    $('#cust-search').on('input', function () { clearTimeout(st); const v = $(this).val().trim(); st = setTimeout(function () { search = v; load(1); }, 300); });
    $('#cust-filter-optin').on('change', function () { optin = $(this).val(); load(1); });
    trashedSelect.on('change', function () { trashed = $(this).val(); load(1); });

    $('#btn-new-customer').on('click', function () {
        $('#cust-modal-title').text('New Customer'); $('#cust-form')[0].reset(); $('#cust-id').val('');
        new bootstrap.Modal('#cust-modal').show();
    });
    tbody.on('click', '.edit-cust', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/customers/' + id).then(function (r) {
            const c = r.data;
            $('#cust-modal-title').text('Edit Customer'); $('#cust-id').val(c.id);
            $('#cust-name').val(c.name); $('#cust-phone').val(c.phone || '');
            $('#cust-email').val(c.email || ''); $('#cust-loyalty').val(c.loyalty_points || 0);
            $('#cust-address').val(c.address || ''); $('#cust-optin').prop('checked', !!c.marketing_opt_in);
            new bootstrap.Modal('#cust-modal').show();
        });
    });
    $('#cust-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#cust-id').val();
        const payload = {
            name: $('#cust-name').val(), phone: $('#cust-phone').val() || null,
            email: $('#cust-email').val() || null, address: $('#cust-address').val() || null,
            loyalty_points: parseInt($('#cust-loyalty').val(), 10) || 0,
            marketing_opt_in: $('#cust-optin').is(':checked')
        };
        const req = id ? Insophinia.api.put('/api/customers/' + id, payload) : Insophinia.api.post('/api/customers', payload);
        req.then(function (r) {
            if (r.success) {
                Insophinia.toast.success(id ? 'Customer updated' : 'Customer created');
                bootstrap.Modal.getInstance(document.getElementById('cust-modal')).hide();
                load(page);
            }
        });
    });
    tbody.on('click', '.del-cust', function () {
        if (!confirm('Soft-delete this customer? You can restore them later from the trash.')) return;
        Insophinia.api.delete('/api/customers/' + $(this).data('id')).then(function () { Insophinia.toast.success('Customer deleted (soft)'); load(page); });
    });
    tbody.on('click', '.restore-cust', function () {
        Insophinia.api.patch('/api/customers/' + $(this).data('id') + '/restore', {}).then(function () { Insophinia.toast.success('Customer restored'); load(page); });
    });
    tbody.on('click', '.force-del-cust', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE customer "' + name + '"?\n\nThis cannot be undone.')) return;
        Insophinia.api.delete('/api/customers/' + $(this).data('id') + '?force=true').then(function () { Insophinia.toast.success('Customer permanently deleted'); load(page); });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    load(1);
});
