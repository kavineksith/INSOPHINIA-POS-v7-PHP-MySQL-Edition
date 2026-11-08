/* bills.js — bills list + detail + send + trashed filter */
$(function () {
    let page = 1, limit = 20, search = '', status = '', trashed = 'hide';
    const tbody = $('#bill-tbody');
    const trashedSelect = $('#bill-trashed-filter');
    function load(p) {
        page = p || 1;
        const params = new URLSearchParams({ page: String(page), limit: String(limit), trashed: trashed });
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        Insophinia.api.get('/api/billing?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            trashedSelect.toggleClass('trashed-only-active', trashed === 'only');
            trashedSelect.toggleClass('trashed-with-active', trashed === 'with');
            if (!items.length) tbody.html('<tr><td colspan="7" class="text-center text-muted-fg py-4">' + (trashed === 'only' ? 'No deleted bills.' : 'No bills.') + '</td></tr>');
            else {
                tbody.empty();
                items.forEach(function (b) {
                    const isDel = !!b.deleted_at;
                    const badgeClass = { pending: 'bg-secondary', completed: 'bg-success', returned: 'bg-warning', cancelled: 'bg-danger' }[b.status] || 'bg-secondary';
                    const tr = $('<tr>').toggleClass('row-deleted', isDel);
                    tr.append('<td><code>' + esc(b.bill_number) + '</code>' + (isDel ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
                    tr.append('<td>' + esc(b.customer_name) + '</td>');
                    tr.append('<td>' + esc(b.staff_name) + '</td>');
                    tr.append('<td class="text-end fw-semibold">Rs.' + num(b.total_amount) + '</td>');
                    tr.append('<td><span class="badge ' + badgeClass + '">' + esc(b.status) + '</span></td>');
                    tr.append('<td>' + Insophinia.format.date(b.created_at) + '</td>');
                    tr.append('<td class="text-end">' +
                        '<span class="row-active-actions"><button class="btn btn-sm btn-ghost view-bill" data-id="' + b.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-eye"></i></button></span>' +
                        (isDel ? '<span class="row-deleted-actions">' +
                            '<button class="btn btn-sm btn-restore restore-bill" data-id="' + b.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                            (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-bill" data-id="' + b.id + '" data-name="' + esc(b.bill_number) + '" title="Permanently delete"><i class="bi bi-x-octagon"></i></button>' : '') +
                        '</span>' : '') +
                        '</td>');
                    tbody.append(tr);
                });
            }
            Insophinia.renderPagination(pg, '#bill-pagination', '#bill-pagination-info', load);
        });
    }
    let st = null;
    $('#bill-search').on('input', function () { clearTimeout(st); const v = $(this).val().trim(); st = setTimeout(function () { search = v; load(1); }, 300); });
    $('#bill-status-filter').on('change', function () { status = $(this).val(); load(1); });
    trashedSelect.on('change', function () { trashed = $(this).val(); load(1); });

    tbody.on('click', '.view-bill', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/billing/' + id).then(function (r) {
            const b = r.data;
            $('#bill-detail-title').text('Bill ' + b.bill_number);
            let html = '<div class="row g-3"><div class="col-md-6"><strong>Customer:</strong> ' + esc(b.customer_name) + (b.customer_phone ? ' · ' + esc(b.customer_phone) : '') + (b.customer_email ? ' · ' + esc(b.customer_email) : '') + '</div>';
            html += '<div class="col-md-6"><strong>Cashier:</strong> ' + esc(b.staff_name) + '</div>';
            html += '<div class="col-md-6"><strong>Date:</strong> ' + Insophinia.format.date(b.created_at) + '</div>';
            html += '<div class="col-md-6"><strong>Status:</strong> ' + esc(b.status) + '</div></div>';
            html += '<table class="table table-sm mt-3"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            (b.items || []).forEach(function (it) {
                let name = it.item_name;
                if (it.variant_name) name += ' (' + it.variant_name + ')';
                html += '<tr><td>' + esc(name) + '</td><td class="text-end">' + it.quantity + '</td><td class="text-end">Rs.' + num(it.actual_price) + '</td><td class="text-end">Rs.' + num(it.subtotal) + '</td></tr>';
            });
            html += '</tbody></table>';
            html += '<div class="row"><div class="col-md-6 offset-md-6"><table class="table table-sm"><tr><td>Subtotal</td><td class="text-end">Rs.' + num(b.subtotal) + '</td></tr>';
            if (parseFloat(b.total_discount) > 0) html += '<tr><td>Discount</td><td class="text-end">-Rs.' + num(b.total_discount) + '</td></tr>';
            if (parseFloat(b.vat_amount) > 0) html += '<tr><td>VAT</td><td class="text-end">Rs.' + num(b.vat_amount) + '</td></tr>';
            if (parseFloat(b.sscl_amount) > 0) html += '<tr><td>SSCL</td><td class="text-end">Rs.' + num(b.sscl_amount) + '</td></tr>';
            if (parseFloat(b.points_value) > 0) html += '<tr><td>Loyalty</td><td class="text-end">-Rs.' + num(b.points_value) + '</td></tr>';
            html += '<tr class="fw-bold border-top"><td>Total</td><td class="text-end">Rs.' + num(b.total_amount) + '</td></tr>';
            html += '<tr><td>Paid</td><td class="text-end">Rs.' + num(b.paid_amount) + '</td></tr>';
            if (parseFloat(b.balance) > 0) html += '<tr><td>Balance</td><td class="text-end">Rs.' + num(b.balance) + '</td></tr>';
            html += '</table></div></div>';
            $('#bill-detail-body').html(html);
            $('#bill-detail-modal').data('id', id);
            new bootstrap.Modal('#bill-detail-modal').show();
        });
    });
    tbody.on('click', '.restore-bill', function () {
        Insophinia.api.patch('/api/billing/' + $(this).data('id') + '/restore', {}).then(function () { Insophinia.toast.success('Bill restored'); load(page); });
    });
    tbody.on('click', '.force-del-bill', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE bill "' + name + '"?\n\nThis cannot be undone. The bill and all its items will be removed entirely.')) return;
        Insophinia.api.delete('/api/billing/' + $(this).data('id') + '?force=true').then(function () { Insophinia.toast.success('Bill permanently deleted'); load(page); });
    });
    $('#btn-bill-print-thermal').on('click', function () {
        const id = $('#bill-detail-modal').data('id');
        window.open('/api/billing/' + id + '/pdf?format=thermal', '_blank');
    });
    $('#btn-bill-print-a4').on('click', function () {
        const id = $('#bill-detail-modal').data('id');
        window.open('/api/billing/' + id + '/pdf?format=a4-preview', '_blank');
    });
    $('#btn-bill-whatsapp').on('click', function () {
        const id = $('#bill-detail-modal').data('id');
        Insophinia.api.post('/api/billing/' + id + '/send', { channel: 'whatsapp_text' }).then(function (r) {
            if (r.success) window.open(r.data.url, '_blank');
        });
    });
    $('#btn-bill-email').on('click', function () {
        const id = $('#bill-detail-modal').data('id');
        const email = prompt('Recipient email:');
        if (!email) return;
        Insophinia.api.post('/api/billing/' + id + '/send', { channel: 'email_pdf', recipient_email: email }).then(function () { Insophinia.toast.success('Email sent'); });
    });
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function num(n) { return Number(n || 0).toFixed(2); }
    load(1);
});
