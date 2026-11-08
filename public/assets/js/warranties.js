/* warranties.js — warranties + claims */
$(function () {
    function loadWarranties(p) {
        p = p || 1;
        const search = $('#war-search').val().trim();
        const params = new URLSearchParams({ page: String(p), limit: '20' });
        if (search) params.set('search', search);
        Insophinia.api.get('/api/warranties?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            const tb = $('#war-tbody');
            if (!items.length) tb.html('<tr><td colspan="6" class="text-center text-muted-fg py-4">No warranties.</td></tr>');
            else {
                tb.empty();
                items.forEach(function (w) {
                    const badgeClass = { active: 'bg-success', expired: 'bg-secondary', claimed: 'bg-warning', voided: 'bg-danger' }[w.status] || 'bg-secondary';
                    tb.append('<tr><td><code>' + esc(w.warranty_number) + '</code></td><td>' + esc(w.item_name) + '</td><td>' + esc(w.customer_name || '') + '</td><td>' + Insophinia.format.dateOnly(w.expires_at) + '</td><td><span class="badge ' + badgeClass + '">' + esc(w.status) + '</span></td><td class="text-end"><button class="btn btn-sm btn-ghost file-claim" data-id="' + w.id + '" data-item="' + esc(w.item_name) + '"><i class="bi bi-file-earmark-exclamation"></i></button></td></tr>');
                });
            }
            renderPagination(pg, '#war-pagination', '#war-pagination-info', loadWarranties);
        });
    }
    function loadClaims(p) {
        p = p || 1;
        const search = $('#claim-search').val().trim();
        const params = new URLSearchParams({ page: String(p), limit: '20' });
        if (search) params.set('search', search);
        Insophinia.api.get('/api/warranties/claims?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            const tb = $('#claim-tbody');
            if (!items.length) tb.html('<tr><td colspan="6" class="text-center text-muted-fg py-4">No claims.</td></tr>');
            else {
                tb.empty();
                items.forEach(function (c) {
                    const badgeClass = { filed: 'bg-info', under_review: 'bg-warning', approved: 'bg-success', rejected: 'bg-danger', repaired: 'bg-success', replaced: 'bg-success', closed: 'bg-secondary' }[c.status] || 'bg-secondary';
                    tb.append('<tr><td><code>' + esc(c.claim_number) + '</code></td><td><code>' + esc(c.warranty_number) + '</code></td><td>' + esc(c.item_name) + '</td><td>' + Insophinia.format.dateOnly(c.filed_at) + '</td><td><span class="badge ' + badgeClass + '">' + esc(c.status) + '</span></td>' + (window.CAN_MANAGE_WARRANTY ? '<td class="text-end"><button class="btn btn-sm btn-ghost review-claim" data-id="' + c.id + '"><i class="bi bi-pencil"></i></button></td>' : '<td></td>') + '</tr>');
                });
            }
            renderPagination(pg, '#claim-pagination', '#claim-pagination-info', loadClaims);
        });
    }
    let st = null;
    $('#war-search').on('input', function () { clearTimeout(st); st = setTimeout(loadWarranties, 300); });
    let st2 = null;
    $('#claim-search').on('input', function () { clearTimeout(st2); st2 = setTimeout(loadClaims, 300); });

    // File claim
    $('#war-tbody').on('click', '.file-claim', function () {
        $('#claim-warranty-id').val($(this).data('id'));
        $('#file-claim-form')[0].reset();
        $('#file-claim-modal .modal-title').text('File Claim — ' + $(this).data('item'));
        new bootstrap.Modal('#file-claim-modal').show();
    });
    $('#file-claim-form').on('submit', function (e) {
        e.preventDefault();
        const payload = {
            warranty_id: $('#claim-warranty-id').val(),
            issue_description: $('#claim-issue').val(),
            fault_category: $('#claim-fault').val() || null
        };
        Insophinia.api.post('/api/warranties/claims', payload).then(function (r) {
            if (r.success) { Insophinia.toast.success('Claim filed'); bootstrap.Modal.getInstance(document.getElementById('file-claim-modal')).hide(); loadWarranties(); }
        });
    });

    // Review claim
    $('#claim-tbody').on('click', '.review-claim', function () {
        const id = $(this).data('id');
        $('#review-claim-id').val(id);
        $('#review-claim-form')[0].reset();
        new bootstrap.Modal('#review-claim-modal').show();
    });
    $('#review-claim-form').on('submit', function (e) {
        e.preventDefault();
        const payload = {
            status: $('#review-status').val(),
            assessment_notes: $('#review-assessment').val() || null,
            resolution_notes: $('#review-resolution').val() || null,
            resolution_type: $('#review-resolution-type').val() || null
        };
        Insophinia.api.patch('/api/warranties/claims/' + $('#review-claim-id').val() + '/review', payload).then(function (r) {
            if (r.success) { Insophinia.toast.success('Claim reviewed'); bootstrap.Modal.getInstance(document.getElementById('review-claim-modal')).hide(); loadClaims(); }
        });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    loadWarranties(); loadClaims();
});
