/* billing.js — POS checkout page */
$(function () {
    const cart = []; // {item_id, item, variant, sub_item, quantity, unit_price, subtotal, revisions_purchased}
    let customerId = null;
    let debounceTimer = null;

    // ---- Item search ----
    $('#bill-item-search').on('input', function () {
        clearTimeout(debounceTimer);
        const q = $(this).val().trim();
        const results = $('#bill-search-results');
        if (!q || q.length < 1) { results.empty().hide(); return; }
        debounceTimer = setTimeout(function () {
            Insophinia.api.get('/api/items?page=1&limit=10&search=' + encodeURIComponent(q)).then(function (r) {
                const items = r.data.items || [];
                results.empty();
                if (!items.length) { results.html('<div class="list-group-item small text-muted-fg">No items found.</div>').show(); return; }
                items.forEach(function (it) {
                    results.append('<a href="#" class="list-group-item list-group-item-action small add-to-cart" data-id="' + it.id + '"><div class="d-flex justify-content-between"><span><code>' + esc(it.plu_code) + '</code> ' + esc(it.name) + '</span><span class="text-muted-fg">Rs.' + num(it.price) + ' · Stock: ' + it.stock_quantity + '</span></div></a>');
                });
                results.show();
            });
        }, 250);
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#bill-item-search, #bill-search-results').length) $('#bill-search-results').hide();
    });

    $('#bill-search-results').on('click', '.add-to-cart', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        $('#bill-search-results').hide();
        $('#bill-item-search').val('');
        Insophinia.api.get('/api/items/' + id).then(function (r) { promptAddItem(r.data); });
    });

    function promptAddItem(item) {
        // If composite or has variants, prompt for variant
        if (item.variants && item.variants.length) {
            const list = $('#variant-list').empty();
            list.append('<a href="#" class="list-group-item list-group-item-action variant-pick" data-variant-id=""><strong>' + esc(item.name) + '</strong> (no variant) — Rs.' + num(item.price) + '</a>');
            item.variants.forEach(function (v) {
                list.append('<a href="#" class="list-group-item list-group-item-action variant-pick" data-variant-id="' + v.id + '" data-adjustment="' + v.price_adjustment + '" data-name="' + esc(v.name) + '"><strong>' + esc(v.name) + '</strong> — Rs.' + num(parseFloat(item.price) + parseFloat(v.price_adjustment)) + ' (Stock: ' + v.stock_quantity + ')</a>');
            });
            $('#variant-modal').data('item', item);
            new bootstrap.Modal('#variant-modal').show();
        } else {
            addToCart(item, null);
        }
    }

    $('#variant-list').on('click', '.variant-pick', function (e) {
        e.preventDefault();
        const variantId = $(this).data('variant-id') || null;
        const item = $('#variant-modal').data('item');
        let variant = null;
        if (variantId) {
            variant = item.variants.find(function (v) { return v.id === variantId; });
        }
        bootstrap.Modal.getInstance(document.getElementById('variant-modal')).hide();
        // If item has sub-items (composite), prompt for sub-item
        if (item.sub_items && item.sub_items.length) {
            const list = $('#subitem-list').empty();
            list.append('<a href="#" class="list-group-item list-group-item-action subitem-pick" data-sub-id=""><strong>No sub-item</strong></a>');
            item.sub_items.forEach(function (s) {
                list.append('<a href="#" class="list-group-item list-group-item-action subitem-pick" data-sub-id="' + s.id + '" data-name="' + esc(s.name) + '" data-base-price="' + s.base_price + '" data-revisions-included="' + s.revisions_included + '" data-revision-price="' + s.revision_price + '"><strong>' + esc(s.name) + '</strong> — Base Rs.' + num(s.base_price) + ' (incl. ' + s.revisions_included + ' rev @ Rs.' + num(s.revision_price) + ')</a>');
            });
            $('#subitem-modal').data('item', item).data('variant', variant);
            new bootstrap.Modal('#subitem-modal').show();
        } else {
            addToCart(item, variant);
        }
    });

    $('#subitem-list').on('click', '.subitem-pick', function (e) {
        e.preventDefault();
        const subId = $(this).data('sub-id') || null;
        const item = $('#subitem-modal').data('item');
        const variant = $('#subitem-modal').data('variant');
        let subItem = null;
        if (subId) {
            subItem = item.sub_items.find(function (s) { return s.id === subId; });
            const extraRev = parseInt(prompt('Extra revisions purchased (additional cost Rs.' + (subItem ? subItem.revision_price : 0) + ' each):', '0') || '0', 10);
            subItem._extra_revisions = extraRev;
        }
        bootstrap.Modal.getInstance(document.getElementById('subitem-modal')).hide();
        addToCart(item, variant, subItem);
    });

    function addToCart(item, variant, subItem) {
        const qty = parseFloat(prompt('Quantity:', '1') || '1') || 1;
        let unitPrice = parseFloat(item.price);
        if (variant) unitPrice += parseFloat(variant.price_adjustment || 0);
        let lineSubtotal = unitPrice * qty;
        let revisionsSubtotal = 0;
        if (subItem) {
            lineSubtotal += parseFloat(subItem.base_price);
            revisionsSubtotal = (subItem._extra_revisions || 0) * parseFloat(subItem.revision_price);
        }
        const existing = cart.find(function (c) { return c.item_id === item.id && c.variant_id === (variant ? variant.id : null) && c.sub_item_id === (subItem ? subItem.id : null); });
        if (existing) {
            existing.quantity += qty;
            existing.subtotal = existing.unit_price * existing.quantity + (existing.sub_item ? existing.sub_item.base_price : 0) + existing.revisions_subtotal;
        } else {
            cart.push({
                item_id: item.id,
                item: item,
                variant: variant,
                variant_id: variant ? variant.id : null,
                sub_item: subItem,
                sub_item_id: subItem ? subItem.id : null,
                quantity: qty,
                unit_price: unitPrice,
                subtotal: lineSubtotal + revisionsSubtotal,
                revisions_subtotal: revisionsSubtotal,
                revisions_purchased: subItem ? (subItem._extra_revisions || 0) : 0
            });
        }
        renderCart();
        recalc();
    }

    function renderCart() {
        const tb = $('#cart-tbody');
        if (!cart.length) {
            tb.html('<tr><td colspan="5" class="text-center text-muted-fg py-4">Cart is empty. Search and add items above.</td></tr>');
        } else {
            tb.empty();
            cart.forEach(function (c, i) {
                let name = c.item.name;
                if (c.variant) name += ' (' + c.variant.name + ')';
                if (c.sub_item) name += ' · Sub: ' + c.sub_item.name + (c.revisions_purchased ? ' +' + c.revisions_purchased + ' rev' : '');
                tb.append('<tr>' +
                    '<td>' + esc(name) + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm cart-qty" data-i="' + i + '" value="' + c.quantity + '" step="0.001" min="0.001"></td>' +
                    '<td class="text-end">Rs.' + num(c.unit_price) + '</td>' +
                    '<td class="text-end">Rs.' + num(c.subtotal) + '</td>' +
                    '<td class="text-end"><button class="btn btn-sm btn-ghost text-destructive cart-remove" data-i="' + i + '"><i class="bi bi-x"></i></button></td>' +
                    '</tr>');
            });
        }
        $('#cart-count').text(cart.length);
    }

    $('#cart-tbody').on('input', '.cart-qty', function () {
        const i = parseInt($(this).data('i'), 10);
        const newQty = parseFloat($(this).val()) || 0;
        if (newQty <= 0) { cart.splice(i, 1); }
        else {
            cart[i].quantity = newQty;
            cart[i].subtotal = cart[i].unit_price * newQty + (cart[i].sub_item ? parseFloat(cart[i].sub_item.base_price) : 0) + cart[i].revisions_subtotal;
        }
        renderCart(); recalc();
    });
    $('#cart-tbody').on('click', '.cart-remove', function () {
        cart.splice(parseInt($(this).data('i'), 10), 1);
        renderCart(); recalc();
    });
    $('#btn-clear-cart').on('click', function () { cart.length = 0; renderCart(); recalc(); });

    function recalc() {
        let subtotal = 0, discount = 0, loyaltyRedeem = 0;
        cart.forEach(function (c) {
            subtotal += c.subtotal;
            if (c.item.has_discount && parseFloat(c.item.discount_percentage) > 0) {
                discount += c.subtotal * (parseFloat(c.item.discount_percentage) / 100);
            }
        });
        const totalBeforeTax = subtotal - discount;
        let vat = 0, sscl = 0;
        // VAT/SSCL are computed server-side; we approximate for display only
        const total = totalBeforeTax;
        const points = parseInt($('#bill-points').val(), 10) || 0;
        if (points > 0) loyaltyRedeem = points;
        const finalTotal = total - loyaltyRedeem;
        $('#t-subtotal').text('Rs.' + num(subtotal));
        $('#t-discount').text('-Rs.' + num(discount));
        $('#t-loyalty').text('-Rs.' + num(loyaltyRedeem));
        $('#t-total').text('Rs.' + num(finalTotal));
        const paid = parseFloat($('#bill-paid').val()) || 0;
        $('#bill-balance').val('Rs.' + num(paid - finalTotal));
        // Show loyalty auth block if points > 0
        $('#loyalty-auth-block').toggle(points >= 100);
    }
    $('#bill-paid, #bill-points').on('input', recalc);

    // ---- Customer search ----
    $('#bill-customer-search').on('input', function () {
        clearTimeout(debounceTimer);
        const q = $(this).val().trim();
        if (!q || q.length < 2) return;
        debounceTimer = setTimeout(function () {
            Insophinia.api.get('/api/customers?page=1&limit=5&search=' + encodeURIComponent(q)).then(function (r) {
                const items = r.data.items || [];
                if (items.length) {
                    const c = items[0];
                    customerId = c.id;
                    $('#bill-customer-id').val(c.id);
                    $('#bill-customer-info').html('<strong>' + esc(c.name) + '</strong>' + (c.phone ? ' · ' + esc(c.phone) : '') + ' · ' + (c.loyalty_points || 0) + ' pts');
                }
            });
        }, 300);
    });

    // ---- Checkout ----
    $('#btn-checkout').on('click', function () {
        if (!cart.length) { Insophinia.toast.error('Cart is empty'); return; }
        const points = parseInt($('#bill-points').val(), 10) || 0;
        if (points >= 100 && (!$('#auth-username').val() || !$('#auth-password').val())) {
            Insophinia.toast.error('Authorizer credentials required for loyalty redemption');
            return;
        }
        const payload = {
            customer_id: customerId || null,
            customer_name: 'Customer',
            items: cart.map(function (c) {
                return {
                    item_id: c.item_id,
                    quantity: c.quantity,
                    unit: c.item.unit,
                    variant_id: c.variant_id || null,
                    sub_item_id: c.sub_item_id || null,
                    revisions_purchased: c.revisions_purchased || 0,
                    line_notes: null
                };
            }),
            paid_amount: parseFloat($('#bill-paid').val()) || 0,
            points_redeemed: points,
            authorizer_username: points >= 100 ? $('#auth-username').val() : null,
            authorizer_password: points >= 100 ? $('#auth-password').val() : null,
            remarks: $('#bill-remarks').val() || null,
            print_format: $('#bill-print-format').val()
        };
        const btn = $(this); const orig = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing…');
        Insophinia.api.post('/api/billing', payload).then(function (r) {
            if (r.success) {
                Insophinia.toast.success('Bill created: ' + r.data.bill_number);
                $('#send-bill-id').val(r.data.id);
                $('#send-bill-number').text(r.data.bill_number);
                cart.length = 0; renderCart(); recalc();
                new bootstrap.Modal('#send-modal').show();
            }
        }).finally(function () { btn.prop('disabled', false).html(orig); });
    });

    // ---- Send actions ----
    $('#btn-print-thermal').on('click', function () {
        const id = $('#send-bill-id').val();
        window.open('/api/billing/' + id + '/pdf?format=thermal', '_blank');
        Insophinia.api.patch('/api/billing/' + id, { format: 'thermal' }).catch(function(){});
    });
    $('#btn-print-a4').on('click', function () {
        const id = $('#send-bill-id').val();
        window.open('/api/billing/' + id + '/pdf?format=a4-preview', '_blank');
        Insophinia.api.patch('/api/billing/' + id, { format: 'a4' }).catch(function(){});
    });
    $('#btn-whatsapp').on('click', function () {
        const id = $('#send-bill-id').val();
        Insophinia.api.post('/api/billing/' + id + '/send', { channel: 'whatsapp_text' }).then(function (r) {
            if (r.success) window.open(r.data.url, '_blank');
        });
    });
    $('#btn-email').on('click', function () {
        const id = $('#send-bill-id').val();
        const email = prompt('Recipient email:'); if (!email) return;
        Insophinia.api.post('/api/billing/' + id + '/send', { channel: 'email_pdf', recipient_email: email }).then(function () {
            Insophinia.toast.success('Email sent');
        });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function num(n) { return Number(n || 0).toFixed(2); }
});
