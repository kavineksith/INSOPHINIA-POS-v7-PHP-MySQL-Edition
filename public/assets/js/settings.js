/* settings.js — shop, bill content, terms, email test */
$(function () {
    $('#shop-form, #bill-content-form').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        const data = {};
        $(form).find('input, textarea, select').each(function () {
            const name = $(this).attr('name'); if (!name) return;
            if ($(this).attr('type') === 'checkbox') data[name] = $(this).is(':checked');
            else if ($(this).attr('type') === 'number') data[name] = parseFloat($(this).val()) || 0;
            else data[name] = $(this).val();
        });
        Insophinia.api.put('/api/settings', data).then(function (r) {
            if (r.success) Insophinia.toast.success('Settings saved');
        });
    });

    $('#btn-test-email').on('click', function () {
        const recipient = $('#test-email-recipient').val().trim();
        if (!recipient) { Insophinia.toast.error('Enter a recipient email'); return; }
        const btn = $(this); const orig = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');
        Insophinia.api.post('/api/settings/test-email', { recipient: recipient }).then(function () {
            Insophinia.toast.success('Test email sent');
        }).finally(function () { btn.prop('disabled', false).html(orig); });
    });

    // Terms CRUD
    let termsTrashed = 'hide';
    const termsTrashedSelect = $('#terms-trashed-filter');
    function loadTerms() {
        const params = new URLSearchParams({ page: '1', limit: '50', trashed: termsTrashed });
        Insophinia.api.get('/api/terms?' + params.toString()).then(function (r) {
            const items = r.data.items;
            const tb = $('#terms-tbody');
            termsTrashedSelect.toggleClass('trashed-only-active', termsTrashed === 'only');
            termsTrashedSelect.toggleClass('trashed-with-active', termsTrashed === 'with');
            if (!items.length) tb.html('<tr><td colspan="5" class="text-center text-muted-fg py-4">' + (termsTrashed === 'only' ? 'No deleted terms templates.' : 'No terms templates.') + '</td></tr>');
            else {
                tb.empty();
                items.forEach(function (t) {
                    const isDel = !!t.deleted_at;
                    const tr = $('<tr>').toggleClass('row-deleted', isDel);
                    tr.append('<td>' + esc(t.name) + (isDel ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
                    tr.append('<td><span class="badge bg-secondary">' + esc(t.format) + '</span></td>');
                    tr.append('<td>v' + t.version + '</td>');
                    tr.append('<td>' + (isDel ? '<span class="badge bg-secondary">Deleted</span>' : (t.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')) + '</td>');
                    const activeActions = '<span class="row-active-actions">' +
                        '<button class="btn btn-sm btn-ghost edit-terms" data-id="' + t.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-pencil"></i></button> ' +
                        '<button class="btn btn-sm btn-ghost text-destructive del-terms" data-id="' + t.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                        '</span>';
                    const deletedActions = '<span class="row-deleted-actions">' +
                        '<button class="btn btn-sm btn-restore restore-terms" data-id="' + t.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                        (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-terms" data-id="' + t.id + '" data-name="' + esc(t.name) + '" title="Permanently delete"><i class="bi bi-x-octagon"></i></button>' : '') +
                        '</span>';
                    tr.append('<td class="text-end">' + activeActions + deletedActions + '</td>');
                    tb.append(tr);
                });
            }
        });
    }
    termsTrashedSelect.on('change', function () { termsTrashed = $(this).val(); loadTerms(); });
    $('#btn-new-terms').on('click', function () {
        $('#terms-form')[0].reset(); $('#terms-id').val(''); $('#terms-active').prop('checked', true);
        new bootstrap.Modal('#terms-modal').show();
    });
    $('#terms-tbody').on('click', '.edit-terms', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/terms/' + id).then(function (r) {
            const t = r.data;
            $('#terms-id').val(t.id); $('#terms-name').val(t.name); $('#terms-format').val(t.format);
            $('#terms-content').val(t.content); $('#terms-active').prop('checked', !!t.is_active);
            new bootstrap.Modal('#terms-modal').show();
        });
    });
    $('#terms-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#terms-id').val();
        const payload = {
            name: $('#terms-name').val(), format: $('#terms-format').val(),
            content: $('#terms-content').val(), is_active: $('#terms-active').is(':checked'),
            applies_to: ['a4']
        };
        const req = id ? Insophinia.api.put('/api/terms/' + id, payload) : Insophinia.api.post('/api/terms', payload);
        req.then(function () {
            Insophinia.toast.success(id ? 'Template updated' : 'Template created');
            bootstrap.Modal.getInstance(document.getElementById('terms-modal')).hide();
            loadTerms();
        });
    });
    $('#terms-tbody').on('click', '.del-terms', function () {
        if (!confirm('Soft-delete this terms template? You can restore it later.')) return;
        Insophinia.api.delete('/api/terms/' + $(this).data('id')).then(function () { Insophinia.toast.success('Template deleted (soft)'); loadTerms(); });
    });
    $('#terms-tbody').on('click', '.restore-terms', function () {
        Insophinia.api.patch('/api/terms/' + $(this).data('id') + '/restore', {}).then(function () { Insophinia.toast.success('Template restored'); loadTerms(); });
    });
    $('#terms-tbody').on('click', '.force-del-terms', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE terms template "' + name + '"?\n\nThis cannot be undone.')) return;
        Insophinia.api.delete('/api/terms/' + $(this).data('id') + '?force=true').then(function () { Insophinia.toast.success('Template permanently deleted'); loadTerms(); });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    loadTerms();
});
