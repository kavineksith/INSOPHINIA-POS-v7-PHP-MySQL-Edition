/* repairs.js — repair jobs CRUD + detail */
$(function () {
    let page = 1, limit = 20, search = '', status = '';
    const tbody = $('#rep-tbody');
    function load(p) {
        page = p || 1;
        const params = new URLSearchParams({ page: String(page), limit: String(limit) });
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        Insophinia.api.get('/api/repairs?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            if (!items.length) tbody.html('<tr><td colspan="7" class="text-center text-muted-fg py-4">No repair jobs.</td></tr>');
            else {
                tbody.empty();
                items.forEach(function (j) {
                    const statusColors = { received: 'bg-info', in_progress: 'bg-warning', awaiting_parts: 'bg-warning', ready_for_pickup: 'bg-success', completed: 'bg-success', cancelled: 'bg-danger', returned_unrepaired: 'bg-secondary' };
                    tbody.append('<tr><td><code>' + esc(j.job_number) + '</code></td><td>' + esc(j.customer_name) + '</td><td>' + esc(j.item_description) + '</td><td><span class="badge ' + (statusColors[j.status] || 'bg-secondary') + '">' + esc(j.status) + '</span></td><td class="text-end">Rs.' + num(j.total_cost) + '</td><td>' + Insophinia.format.dateOnly(j.received_at) + '</td><td class="text-end"><button class="btn btn-sm btn-ghost view-rep" data-id="' + j.id + '"><i class="bi bi-eye"></i></button></td></tr>');
                });
            }
            renderPagination(pg, '#rep-pagination', '#rep-pagination-info', load);
        });
    }
    let st = null;
    $('#rep-search').on('input', function () { clearTimeout(st); st = setTimeout(function () { search = $(this).val().trim(); load(1); }, 300); });
    $('#rep-status-filter').on('change', function () { status = $(this).val(); load(1); });

    $('#btn-new-repair').on('click', function () {
        $('#rep-form')[0].reset();
        new bootstrap.Modal('#rep-modal').show();
    });
    $('#rep-form').on('submit', function (e) {
        e.preventDefault();
        const payload = {
            customer_name: $('#rep-customer-name').val(),
            customer_phone: $('#rep-customer-phone').val() || null,
            customer_email: $('#rep-customer-email').val() || null,
            item_description: $('#rep-item-desc').val(),
            item_serial: $('#rep-item-serial').val() || null,
            access_password: $('#rep-access-pw').val() || null,
            fault_description: $('#rep-fault').val(),
            accessories: $('#rep-accessories').val() || null,
            condition_notes: $('#rep-condition').val() || null,
            scope_of_work: $('#rep-scope').val() || null,
            estimated_cost: parseFloat($('#rep-est-cost').val()) || 0,
            estimated_hours: parseFloat($('#rep-est-hours').val()) || 0,
            labor_cost: parseFloat($('#rep-labor').val()) || 0,
            repair_warranty_days: parseInt($('#rep-warranty').val(), 10) || 0,
            notes: $('#rep-notes').val() || null
        };
        Insophinia.api.post('/api/repairs', payload).then(function (r) {
            if (r.success) { Insophinia.toast.success('Repair job created'); bootstrap.Modal.getInstance(document.getElementById('rep-modal')).hide(); load(page); }
        });
    });

    tbody.on('click', '.view-rep', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/repairs/' + id).then(function (r) {
            const j = r.data;
            let html = '<div class="row g-3">';
            html += '<div class="col-md-6"><strong>Job:</strong> ' + esc(j.job_number) + '</div>';
            html += '<div class="col-md-6"><strong>Status:</strong> ' + esc(j.status) + '</div>';
            html += '<div class="col-md-6"><strong>Customer:</strong> ' + esc(j.customer_name) + (j.customer_phone ? ' · ' + esc(j.customer_phone) : '') + '</div>';
            html += '<div class="col-md-6"><strong>Item:</strong> ' + esc(j.item_description) + (j.item_serial ? ' (SN: ' + esc(j.item_serial) + ')' : '') + '</div>';
            html += '<div class="col-12"><strong>Fault:</strong> ' + esc(j.fault_description) + '</div>';
            if (j.scope_of_work) html += '<div class="col-12"><strong>Scope:</strong> ' + esc(j.scope_of_work) + '</div>';
            html += '<div class="col-md-3"><strong>Est. Cost:</strong> Rs.' + num(j.estimated_cost) + '</div>';
            html += '<div class="col-md-3"><strong>Labor:</strong> Rs.' + num(j.labor_cost) + '</div>';
            html += '<div class="col-md-3"><strong>Parts:</strong> Rs.' + num(j.parts_cost) + '</div>';
            html += '<div class="col-md-3"><strong>Total:</strong> Rs.' + num(j.total_cost) + '</div>';
            html += '</div>';
            html += '<hr><h6>Parts</h6><table class="table table-sm"><thead><tr><th>Part</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            (j.parts || []).forEach(function (p) {
                html += '<tr><td>' + esc(p.part_name) + '</td><td class="text-end">' + p.quantity + '</td><td class="text-end">Rs.' + num(p.unit_price) + '</td><td class="text-end">Rs.' + num(p.subtotal) + '</td></tr>';
            });
            if (!(j.parts || []).length) html += '<tr><td colspan="4" class="text-center text-muted-fg">No parts.</td></tr>';
            html += '</tbody></table>';
            if (window.CAN_MANAGE_REPAIR) {
                html += '<hr><h6>Update Status</h6><div class="row g-2"><div class="col-md-6"><select class="form-select form-select-sm" id="rep-update-status"><option value="received">Received</option><option value="in_progress">In Progress</option><option value="awaiting_parts">Awaiting Parts</option><option value="ready_for_pickup">Ready for Pickup</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="returned_unrepaired">Returned Unrepaired</option></select></div><div class="col-md-6"><button class="btn btn-sm btn-primary w-100" id="btn-update-rep">Update</button></div></div>';
            }
            $('#rep-detail-body').html(html);
            $('#rep-detail-modal').data('id', id);
            if (j.status) $('#rep-update-status').val(j.status);
            new bootstrap.Modal('#rep-detail-modal').show();
        });
    });
    $(document).on('click', '#btn-update-rep', function () {
        const id = $('#rep-detail-modal').data('id');
        Insophinia.api.put('/api/repairs/' + id, { status: $('#rep-update-status').val() }).then(function () {
            Insophinia.toast.success('Status updated');
            bootstrap.Modal.getInstance(document.getElementById('rep-detail-modal')).hide();
            load(page);
        });
    });
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function num(n) { return Number(n || 0).toFixed(2); }
    load(1);
});
