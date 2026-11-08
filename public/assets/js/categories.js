/* ============================================================================
 * categories.js — CRUD for categories with soft-delete + trashed filter
 * ==========================================================================*/
$(function () {
    let currentPage = 1;
    let currentLimit = 20;
    let currentSearch = '';
    let currentTrashed = 'hide'; // 'hide' | 'with' | 'only'

    const tbody = $('#cat-tbody');
    const paginationEl = $('#cat-pagination');
    const paginationInfo = $('#cat-pagination-info');
    const trashedSelect = $('#cat-trashed-filter');

    function load(page) {
        page = page || 1;
        currentPage = page;
        tbody.html('<tr><td colspan="5" class="text-center text-muted-fg py-4">Loading…</td></tr>');
        const params = new URLSearchParams({ page: String(currentPage), limit: String(currentLimit), trashed: currentTrashed });
        if (currentSearch) params.set('search', currentSearch);
        Insophinia.api.get('/api/categories?' + params.toString()).then(function (r) {
            render(r.data.items, r.data.pagination);
        });
    }

    function render(items, p) {
        // Highlight trashed filter when active
        trashedSelect.toggleClass('trashed-only-active', currentTrashed === 'only');
        trashedSelect.toggleClass('trashed-with-active', currentTrashed === 'with');

        if (!items.length) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted-fg py-4">' +
                (currentTrashed === 'only' ? 'No deleted categories.' : 'No categories found.') +
                '</td></tr>');
            paginationEl.empty(); paginationInfo.text('0 / 0'); return;
        }
        tbody.empty();
        items.forEach(function (c) {
            const isDeleted = !!c.deleted_at;
            const tr = $('<tr>').toggleClass('row-deleted', isDeleted);
            tr.append('<td class="fw-semibold">' + esc(c.name) + (isDeleted ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
            tr.append('<td class="text-muted-fg">' + esc(c.description || '') + '</td>');
            tr.append('<td class="text-center">' + (c.items_count || 0) + '</td>');
            tr.append('<td>' + (isDeleted ? '<span class="badge bg-secondary">Deleted</span>' : (c.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')) + '</td>');
            // Active row actions: edit + delete (soft)
            // Trashed row actions: restore + force-delete (master_admin only)
            const activeActions = '<span class="row-active-actions">' +
                '<button class="btn btn-sm btn-ghost edit-cat" data-id="' + c.id + '"><i class="bi bi-pencil"></i></button> ' +
                '<button class="btn btn-sm btn-ghost text-destructive del-cat" data-id="' + c.id + '"' + (isDeleted ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                '</span>';
            const deletedActions = '<span class="row-deleted-actions">' +
                '<button class="btn btn-sm btn-restore restore-cat" data-id="' + c.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-cat" data-id="' + c.id + '" data-name="' + esc(c.name) + '" title="Permanently delete (master admin only)"><i class="bi bi-x-octagon"></i></button>' : '') +
                '</span>';
            tr.append('<td class="text-end">' + activeActions + deletedActions + '</td>');
            tbody.append(tr);
        });
        renderPagination(p);
    }

    function renderPagination(p) {
        Insophinia.renderPagination(p, '#cat-pagination', '#cat-pagination-info', load);
    }

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }

    // Search debounce
    let searchTimer = null;
    $('#cat-search').on('input', function () {
        clearTimeout(searchTimer);
        const v = $(this).val().trim();
        searchTimer = setTimeout(function () { currentSearch = v; load(1); }, 300);
    });
    $('#cat-page-size').on('change', function () { currentLimit = parseInt($(this).val(), 10) || 20; load(1); });
    trashedSelect.on('change', function () { currentTrashed = $(this).val(); load(1); });

    // New / edit
    $('#btn-new-category').on('click', function () {
        $('#cat-modal-title').text('New Category');
        $('#cat-form')[0].reset();
        $('#cat-id').val('');
        new bootstrap.Modal('#cat-modal').show();
    });
    tbody.on('click', '.edit-cat', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/categories/' + id).then(function (r) {
            const c = r.data;
            $('#cat-modal-title').text('Edit Category');
            $('#cat-id').val(c.id);
            $('#cat-name').val(c.name);
            $('#cat-description').val(c.description || '');
            new bootstrap.Modal('#cat-modal').show();
        });
    });
    $('#cat-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#cat-id').val();
        const payload = { name: $('#cat-name').val(), description: $('#cat-description').val() };
        const req = id ? Insophinia.api.put('/api/categories/' + id, payload) : Insophinia.api.post('/api/categories', payload);
        req.then(function (r) {
            if (r.success) {
                Insophinia.toast.success(id ? 'Category updated' : 'Category created');
                bootstrap.Modal.getInstance(document.getElementById('cat-modal')).hide();
                load(currentPage);
            }
        });
    });

    // Soft delete
    tbody.on('click', '.del-cat', function () {
        if (!confirm('Soft-delete this category? You can restore it later from the trash.')) return;
        Insophinia.api.delete('/api/categories/' + $(this).data('id')).then(function () {
            Insophinia.toast.success('Category deleted (soft)');
            load(currentPage);
        });
    });

    // Restore
    tbody.on('click', '.restore-cat', function () {
        Insophinia.api.patch('/api/categories/' + $(this).data('id') + '/restore', {}).then(function () {
            Insophinia.toast.success('Category restored');
            load(currentPage);
        });
    });

    // Hard (permanent) delete — master_admin only
    tbody.on('click', '.force-del-cat', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE category "' + name + '"?\n\nThis action cannot be undone. The record will be removed from the database entirely.')) return;
        Insophinia.api.delete('/api/categories/' + $(this).data('id') + '?force=true').then(function () {
            Insophinia.toast.success('Category permanently deleted');
            load(currentPage);
        });
    });

    load(1);
});
