/* marketing.js — campaigns + templates with soft-delete + trashed filter */
$(function () {
    let tplTrashed = 'hide';
    const tplTrashedSelect = $('#tpl-trashed-filter');

    function loadCampaigns() {
        const search = $('#camp-search').val().trim();
        const params = new URLSearchParams({ page: '1', limit: '20' });
        if (search) params.set('search', search);
        Insophinia.api.get('/api/marketing/campaigns?' + params.toString()).then(function (r) {
            const items = r.data.items;
            const tb = $('#camp-tbody');
            if (!items.length) tb.html('<tr><td colspan="7" class="text-center text-muted-fg py-4">No campaigns.</td></tr>');
            else {
                tb.empty();
                items.forEach(function (c) {
                    const badgeClass = { draft: 'bg-secondary', scheduled: 'bg-info', sending: 'bg-warning', sent: 'bg-success', failed: 'bg-danger', partial: 'bg-warning' }[c.status] || 'bg-secondary';
                    tb.append('<tr><td>' + esc(c.name) + '</td><td>' + esc(c.template_name || '') + '</td><td><span class="badge ' + badgeClass + '">' + esc(c.status) + '</span></td><td class="text-center">' + c.sent_count + '</td><td class="text-center">' + c.failed_count + '</td><td>' + Insophinia.format.date(c.created_at) + '</td><td class="text-end"><button class="btn btn-sm btn-ghost preview-camp" data-id="' + c.id + '"><i class="bi bi-eye"></i></button> <button class="btn btn-sm btn-ghost text-destructive del-camp" data-id="' + c.id + '"><i class="bi bi-trash"></i></button></td></tr>');
                });
            }
        });
    }
    function loadTemplates() {
        const search = $('#tpl-search').val().trim();
        const params = new URLSearchParams({ page: '1', limit: '50', trashed: tplTrashed });
        if (search) params.set('search', search);
        Insophinia.api.get('/api/marketing/templates?' + params.toString()).then(function (r) {
            const items = r.data.items;
            const tb = $('#tpl-tbody');
            tplTrashedSelect.toggleClass('trashed-only-active', tplTrashed === 'only');
            tplTrashedSelect.toggleClass('trashed-with-active', tplTrashed === 'with');
            if (!items.length) tb.html('<tr><td colspan="5" class="text-center text-muted-fg py-4">' + (tplTrashed === 'only' ? 'No deleted templates.' : 'No templates.') + '</td></tr>');
            else {
                tb.empty();
                items.forEach(function (t) {
                    const isDel = !!t.deleted_at;
                    const tr = $('<tr>').toggleClass('row-deleted', isDel);
                    tr.append('<td>' + esc(t.name) + (isDel ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
                    tr.append('<td><span class="badge bg-secondary">' + esc(t.type) + '</span></td>');
                    tr.append('<td>' + esc(t.subject) + '</td>');
                    tr.append('<td>' + (isDel ? '<span class="badge bg-secondary">Deleted</span>' : (t.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')) + '</td>');
                    const activeActions = '<span class="row-active-actions">' +
                        '<button class="btn btn-sm btn-ghost edit-tpl" data-id="' + t.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-pencil"></i></button> ' +
                        '<button class="btn btn-sm btn-ghost text-destructive del-tpl" data-id="' + t.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                        '</span>';
                    const deletedActions = '<span class="row-deleted-actions">' +
                        '<button class="btn btn-sm btn-restore restore-tpl" data-id="' + t.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                        (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-tpl" data-id="' + t.id + '" data-name="' + esc(t.name) + '" title="Permanently delete"><i class="bi bi-x-octagon"></i></button>' : '') +
                        '</span>';
                    tr.append('<td class="text-end">' + activeActions + deletedActions + '</td>');
                    tb.append(tr);
                });
            }
        });
    }
    let st = null;
    $('#camp-search').on('input', function () { clearTimeout(st); st = setTimeout(loadCampaigns, 300); });
    let st2 = null;
    $('#tpl-search').on('input', function () { clearTimeout(st2); st2 = setTimeout(loadTemplates, 300); });
    tplTrashedSelect.on('change', function () { tplTrashed = $(this).val(); loadTemplates(); });

    // Load templates for campaign dropdown
    function loadTemplateOptions() {
        return Insophinia.api.get('/api/marketing/templates?limit=200').then(function (r) {
            const sel = $('#camp-template'); sel.empty();
            (r.data.items || []).forEach(function (t) { sel.append('<option value="' + t.id + '">' + esc(t.name) + '</option>'); });
        });
    }
    $('#btn-new-campaign').on('click', function () {
        $('#camp-form')[0].reset();
        loadTemplateOptions().then(function () { new bootstrap.Modal('#camp-modal').show(); });
    });
    $('#camp-form').on('submit', function (e) {
        e.preventDefault();
        const payload = {
            name: $('#camp-name').val(),
            template_id: $('#camp-template').val(),
            audience_type: $('#camp-audience-type').val(),
            recipient_email: $('#camp-recipient-email').val() || null,
            recipient_name: $('#camp-recipient-name').val() || null,
            subject_override: $('#camp-subject').val() || null,
            audience_filter: $('#camp-filter').val() || null,
            custom_variables: $('#camp-vars').val() || null
        };
        Insophinia.api.post('/api/marketing/campaigns', payload).then(function () {
            Insophinia.toast.success('Campaign created');
            bootstrap.Modal.getInstance(document.getElementById('camp-modal')).hide();
            loadCampaigns();
        });
    });

    $('#btn-new-template').on('click', function () {
        $('#tpl-form')[0].reset(); $('#tpl-id').val('');
        new bootstrap.Modal('#tpl-modal').show();
    });
    $('#tpl-tbody').on('click', '.edit-tpl', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/marketing/templates/' + id).then(function (r) {
            const t = r.data;
            $('#tpl-id').val(t.id); $('#tpl-name').val(t.name); $('#tpl-type').val(t.type);
            $('#tpl-subject').val(t.subject); $('#tpl-html').val(t.html_body);
            $('#tpl-text').val(t.text_body || ''); $('#tpl-default-vars').val(t.default_variables || '');
            $('#tpl-active').prop('checked', !!t.is_active);
            new bootstrap.Modal('#tpl-modal').show();
        });
    });
    $('#tpl-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#tpl-id').val();
        const payload = {
            name: $('#tpl-name').val(), type: $('#tpl-type').val(),
            subject: $('#tpl-subject').val(), html_body: $('#tpl-html').val(),
            text_body: $('#tpl-text').val() || null,
            default_variables: $('#tpl-default-vars').val() || null,
            is_active: $('#tpl-active').is(':checked')
        };
        const req = id ? Insophinia.api.put('/api/marketing/templates/' + id, payload) : Insophinia.api.post('/api/marketing/templates', payload);
        req.then(function () {
            Insophinia.toast.success(id ? 'Template updated' : 'Template created');
            bootstrap.Modal.getInstance(document.getElementById('tpl-modal')).hide();
            loadTemplates();
        });
    });
    $('#tpl-tbody').on('click', '.del-tpl', function () {
        if (!confirm('Soft-delete this template? You can restore it later from the trash.')) return;
        Insophinia.api.delete('/api/marketing/templates/' + $(this).data('id')).then(function () { Insophinia.toast.success('Template deleted (soft)'); loadTemplates(); });
    });
    $('#tpl-tbody').on('click', '.restore-tpl', function () {
        Insophinia.api.patch('/api/marketing/templates/' + $(this).data('id') + '/restore', {}).then(function () { Insophinia.toast.success('Template restored'); loadTemplates(); });
    });
    $('#tpl-tbody').on('click', '.force-del-tpl', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE template "' + name + '"?\n\nThis cannot be undone.')) return;
        Insophinia.api.delete('/api/marketing/templates/' + $(this).data('id') + '?force=true').then(function () { Insophinia.toast.success('Template permanently deleted'); loadTemplates(); });
    });

    // Campaign preview + send
    let currentCampId = null;
    $('#camp-tbody').on('click', '.preview-camp', function () {
        currentCampId = $(this).data('id');
        Insophinia.api.get('/api/marketing/campaigns/' + currentCampId + '/preview').then(function (r) {
            $('#preview-body').html('<h6>Subject: ' + esc(r.data.subject) + '</h6><hr><div>' + r.data.html + '</div>');
            new bootstrap.Modal('#preview-modal').show();
        });
    });
    $('#btn-send-campaign').on('click', function () {
        if (!confirm('Send this campaign now?')) return;
        const btn = $(this); const orig = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');
        Insophinia.api.post('/api/marketing/campaigns/' + currentCampId + '/send', {}).then(function (r) {
            if (r.success) {
                Insophinia.toast.success('Sent: ' + r.data.sent + ' OK, ' + r.data.failed + ' failed (' + r.data.status + ')');
                bootstrap.Modal.getInstance(document.getElementById('preview-modal')).hide();
                loadCampaigns();
            }
        }).finally(function () { btn.prop('disabled', false).html(orig); });
    });
    $('#camp-tbody').on('click', '.del-camp', function () {
        if (!confirm('Delete this campaign?')) return;
        Insophinia.api.delete('/api/marketing/campaigns/' + $(this).data('id')).then(function () { Insophinia.toast.success('Deleted'); loadCampaigns(); });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    loadCampaigns(); loadTemplates();
});
