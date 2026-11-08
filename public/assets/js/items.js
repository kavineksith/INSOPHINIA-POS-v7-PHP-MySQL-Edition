/* items.js — Items + Variants + Sub-items CRUD with soft-delete + trashed filter */
$(function () {
    let page = 1, limit = 20, search = '', categoryFilter = '', typeFilter = '', trashed = 'hide';
    const tbody = $('#item-tbody');
    const trashedSelect = $('#item-trashed-filter');
    let categoriesCache = null;

    function loadCategories() {
        return Insophinia.api.get('/api/categories?limit=1000').then(function (r) {
            categoriesCache = r.data.items;
            const filterSelect = $('#item-category-filter');
            filterSelect.find('option:not(:first)').remove();
            categoriesCache.forEach(function (c) { filterSelect.append('<option value="' + c.id + '">' + esc(c.name) + '</option>'); });
            const formSelect = $('#item-category');
            formSelect.empty();
            categoriesCache.forEach(function (c) { formSelect.append('<option value="' + c.id + '">' + esc(c.name) + '</option>'); });
        });
    }

    function load(p) {
        page = p || 1;
        const params = new URLSearchParams({ page: String(page), limit: String(limit), trashed: trashed });
        if (search) params.set('search', search);
        if (categoryFilter) params.set('category_id', categoryFilter);
        if (typeFilter) params.set('item_type', typeFilter);
        Insophinia.api.get('/api/items?' + params.toString()).then(function (r) {
            const items = r.data.items, pg = r.data.pagination;
            trashedSelect.toggleClass('trashed-only-active', trashed === 'only');
            trashedSelect.toggleClass('trashed-with-active', trashed === 'with');
            if (!items.length) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted-fg py-4">' + (trashed === 'only' ? 'No deleted items.' : 'No items.') + '</td></tr>');
            } else {
                tbody.empty();
                items.forEach(function (it) {
                    const isDel = !!it.deleted_at;
                    const stockClass = parseFloat(it.stock_quantity) <= parseFloat(it.low_stock_threshold) ? 'text-danger' : '';
                    const tr = $('<tr>').toggleClass('row-deleted', isDel);
                    tr.append('<td><code>' + esc(it.plu_code) + '</code></td>');
                    tr.append('<td class="fw-semibold">' + esc(it.name) + (it.variants_count ? ' <span class="badge bg-secondary">×' + it.variants_count + '</span>' : '') + (it.sub_items_count ? ' <span class="badge bg-info">S' + it.sub_items_count + '</span>' : '') + (isDel ? '<span class="badge-deleted">Deleted</span>' : '') + '</td>');
                    tr.append('<td>' + esc(it.category_name || '') + '</td>');
                    tr.append('<td><span class="badge bg-secondary">' + esc(it.item_type) + '</span></td>');
                    tr.append('<td class="text-end">Rs.' + num(it.price) + '</td>');
                    tr.append('<td class="text-end ' + stockClass + '">' + it.stock_quantity + '</td>');
                    tr.append('<td>' + (isDel ? '<span class="badge bg-secondary">Deleted</span>' : (it.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')) + '</td>');
                    const activeActions = '<span class="row-active-actions">' +
                        '<button class="btn btn-sm btn-ghost variants-btn" data-id="' + it.id + '" data-name="' + esc(it.name) + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-layers"></i></button> ' +
                        '<button class="btn btn-sm btn-ghost edit-item" data-id="' + it.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-pencil"></i></button> ' +
                        '<button class="btn btn-sm btn-ghost text-destructive del-item" data-id="' + it.id + '"' + (isDel ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                        '</span>';
                    const deletedActions = '<span class="row-deleted-actions">' +
                        '<button class="btn btn-sm btn-restore restore-item" data-id="' + it.id + '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>' +
                        (window.CAN_FORCE_DELETE ? ' <button class="btn btn-sm btn-force-delete force-del-item" data-id="' + it.id + '" data-name="' + esc(it.name) + '" title="Permanently delete"><i class="bi bi-x-octagon"></i></button>' : '') +
                        '</span>';
                    tr.append('<td class="text-end">' + activeActions + deletedActions + '</td>');
                    tbody.append(tr);
                });
            }
            Insophinia.renderPagination(pg, '#item-pagination', '#item-pagination-info', load);
        });
    }
    let st = null;
    $('#item-search').on('input', function () { clearTimeout(st); const v = $(this).val().trim(); st = setTimeout(function () { search = v; load(1); }, 300); });
    $('#item-category-filter').on('change', function () { categoryFilter = $(this).val(); load(1); });
    $('#item-type-filter').on('change', function () { typeFilter = $(this).val(); load(1); });
    trashedSelect.on('change', function () { trashed = $(this).val(); load(1); });

    $('#btn-new-item').on('click', function () {
        $('#item-modal-title').text('New Item'); $('#item-form')[0].reset(); $('#item-id').val('');
        $('#item-type').val('product'); $('#item-unit').val('pcs'); $('#item-price').val('0');
        $('#item-cost').val('0'); $('#item-stock').val('0'); $('#item-lowstock').val('10'); $('#item-warranty').val('0');
        $('#item-discount').prop('checked', false); $('#item-discount-pct').val('0');
        new bootstrap.Modal('#item-modal').show();
    });
    tbody.on('click', '.edit-item', function () {
        const id = $(this).data('id');
        Insophinia.api.get('/api/items/' + id).then(function (r) {
            const it = r.data;
            $('#item-modal-title').text('Edit Item'); $('#item-id').val(it.id);
            $('#item-plu').val(it.plu_code); $('#item-barcode').val(it.barcode || ''); $('#item-qr').val(it.qr_code || '');
            $('#item-name').val(it.name); $('#item-category').val(it.category_id);
            $('#item-type').val(it.item_type); $('#item-unit').val(it.unit);
            $('#item-price').val(it.price); $('#item-cost').val(it.cost_price);
            $('#item-stock').val(it.stock_quantity); $('#item-lowstock').val(it.low_stock_threshold); $('#item-warranty').val(it.warranty_days);
            $('#item-discount').prop('checked', !!it.has_discount); $('#item-discount-pct').val(it.discount_percentage);
            $('#item-description').val(it.description || '');
            new bootstrap.Modal('#item-modal').show();
        });
    });
    $('#item-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#item-id').val();
        const payload = {
            plu_code: $('#item-plu').val(), name: $('#item-name').val(),
            category_id: $('#item-category').val(),
            barcode: $('#item-barcode').val() || null,
            qr_code: $('#item-qr').val() || null,
            item_type: $('#item-type').val(), unit: $('#item-unit').val(),
            price: parseFloat($('#item-price').val()) || 0, cost_price: parseFloat($('#item-cost').val()) || 0,
            stock_quantity: parseFloat($('#item-stock').val()) || 0, low_stock_threshold: parseFloat($('#item-lowstock').val()) || 10,
            warranty_days: parseInt($('#item-warranty').val(), 10) || 0,
            has_discount: $('#item-discount').is(':checked'),
            discount_percentage: parseFloat($('#item-discount-pct').val()) || 0,
            description: $('#item-description').val() || null
        };
        const req = id ? Insophinia.api.put('/api/items/' + id, payload) : Insophinia.api.post('/api/items', payload);
        req.then(function (r) {
            if (r.success) { Insophinia.toast.success(id ? 'Item updated' : 'Item created'); bootstrap.Modal.getInstance(document.getElementById('item-modal')).hide(); load(page); }
        });
    });
    tbody.on('click', '.del-item', function () {
        if (!confirm('Soft-delete this item? You can restore it later from the trash.')) return;
        Insophinia.api.delete('/api/items/' + $(this).data('id')).then(function () { Insophinia.toast.success('Item deleted (soft)'); load(page); });
    });
    tbody.on('click', '.restore-item', function () {
        Insophinia.api.patch('/api/items/' + $(this).data('id') + '/restore', {}).then(function () { Insophinia.toast.success('Item restored'); load(page); });
    });
    tbody.on('click', '.force-del-item', function () {
        const name = $(this).data('name') || '';
        if (!confirm('PERMANENTLY DELETE item "' + name + '"?\n\nThis cannot be undone. Variants, sub-items, and references on past bills will be lost.')) return;
        Insophinia.api.delete('/api/items/' + $(this).data('id') + '?force=true').then(function () { Insophinia.toast.success('Item permanently deleted'); load(page); });
    });

    // Variants modal
    let currentItemId = null;
    tbody.on('click', '.variants-btn', function () {
        currentItemId = $(this).data('id');
        $('#variants-modal-title').text($(this).data('name') + ' — Variants & Sub-items');
        loadVariants(); loadSubItems();
        new bootstrap.Modal('#variants-modal').show();
    });

    function loadVariants() {
        Insophinia.api.get('/api/items/' + currentItemId + '/variants').then(function (r) {
            const items = r.data.items || [];
            const tb = $('#variants-tbody'); tb.empty();
            if (!items.length) tb.html('<tr><td colspan="5" class="text-center text-muted-fg py-3">No variants.</td></tr>');
            else items.forEach(function (v) {
                tb.append('<tr><td><code>' + esc(v.sku) + '</code></td><td>' + esc(v.name) + '</td><td class="text-end">Rs.' + num(v.price_adjustment) + '</td><td class="text-end">' + v.stock_quantity + '</td><td class="text-end"><button class="btn btn-sm btn-ghost text-destructive del-variant" data-id="' + v.id + '"><i class="bi bi-trash"></i></button></td></tr>');
            });
        });
    }
    function loadSubItems() {
        Insophinia.api.get('/api/items/' + currentItemId + '/sub-items').then(function (r) {
            const items = r.data.items || [];
            const tb = $('#subitems-tbody'); tb.empty();
            if (!items.length) tb.html('<tr><td colspan="5" class="text-center text-muted-fg py-3">No sub-items.</td></tr>');
            else items.forEach(function (s) {
                tb.append('<tr><td>' + esc(s.name) + '</td><td class="text-end">Rs.' + num(s.base_price) + '</td><td class="text-center">' + s.revisions_included + '</td><td class="text-end">Rs.' + num(s.revision_price) + '</td><td class="text-end"><button class="btn btn-sm btn-ghost text-destructive del-subitem" data-id="' + s.id + '"><i class="bi bi-trash"></i></button></td></tr>');
            });
        });
    }
    $('#btn-new-variant').on('click', function () {
        const sku = prompt('Variant SKU:'); if (!sku) return;
        const name = prompt('Variant name:'); if (!name) return;
        const adj = parseFloat(prompt('Price adjustment (Rs.):', '0') || '0') || 0;
        const stock = parseFloat(prompt('Stock quantity:', '0') || '0') || 0;
        Insophinia.api.post('/api/items/' + currentItemId + '/variants', { item_id: currentItemId, sku: sku, name: name, price_adjustment: adj, stock_quantity: stock, is_active: true, specs: {}, warranty_days: 0 }).then(function () { loadVariants(); });
    });
    $('#variants-tbody').on('click', '.del-variant', function () {
        if (!confirm('Deactivate this variant?')) return;
        Insophinia.api.delete('/api/items/' + currentItemId + '/variants/' + $(this).data('id')).then(function () { loadVariants(); });
    });
    $('#btn-new-subitem').on('click', function () {
        const name = prompt('Sub-item name:'); if (!name) return;
        const basePrice = parseFloat(prompt('Base price (Rs.):', '0') || '0') || 0;
        const revisionsIncluded = parseInt(prompt('Revisions included:', '2') || '2', 10);
        const revisionPrice = parseFloat(prompt('Extra revision price (Rs.):', '0') || '0') || 0;
        Insophinia.api.post('/api/items/' + currentItemId + '/sub-items', { item_id: currentItemId, name: name, base_price: basePrice, revisions_included: revisionsIncluded, revision_price: revisionPrice, is_active: true }).then(function () { loadSubItems(); });
    });
    $('#subitems-tbody').on('click', '.del-subitem', function () {
        if (!confirm('Deactivate this sub-item?')) return;
        Insophinia.api.delete('/api/items/' + currentItemId + '/sub-items/' + $(this).data('id')).then(function () { loadSubItems(); });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function num(n) { return Number(n || 0).toFixed(2); }

    loadCategories().then(function () { load(1); });
});
