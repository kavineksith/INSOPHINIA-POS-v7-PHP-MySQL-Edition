<?php /** Billing page — POS checkout */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:billing');
?>
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Billing</h1>
            <p class="text-muted-fg mb-0">Create a new bill — search items, add to cart, checkout.</p>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left: Item search + cart -->
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="bill-item-search" placeholder="Scan barcode or search items…" autocomplete="off">
                    </div>
                    <div id="bill-search-results" class="list-group mt-2" style="position:relative; z-index:5; max-height:18rem; overflow:auto;"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-cart3 me-2"></i>Cart <span class="badge bg-secondary" id="cart-count">0</span></h5>
                    <button class="btn btn-sm btn-ghost text-destructive" id="btn-clear-cart"><i class="bi bi-trash"></i> Clear</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width:8rem">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cart-tbody">
                                <tr><td colspan="5" class="text-center text-muted-fg py-4">Cart is empty. Search and add items above.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Checkout -->
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Customer</h5></div>
                <div class="card-body">
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" id="bill-customer-search" placeholder="Search customer by phone…">
                    </div>
                    <div id="bill-customer-info" class="small text-muted-fg">Walk-in customer</div>
                    <input type="hidden" id="bill-customer-id">
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h5 class="card-title mb-0">Totals</h5></div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <tr><td>Subtotal</td><td class="text-end" id="t-subtotal">Rs.0.00</td></tr>
                        <tr><td>Discount</td><td class="text-end" id="t-discount">-Rs.0.00</td></tr>
                        <tr id="tr-vat" style="display:none"><td>VAT</td><td class="text-end" id="t-vat">Rs.0.00</td></tr>
                        <tr id="tr-sscl" style="display:none"><td>SSCL</td><td class="text-end" id="t-sscl">Rs.0.00</td></tr>
                        <tr><td>Loyalty Redeem</td><td class="text-end" id="t-loyalty">-Rs.0.00</td></tr>
                        <tr class="fw-bold border-top"><td>Total</td><td class="text-end" id="t-total">Rs.0.00</td></tr>
                    </table>
                    <div class="mb-2">
                        <label class="form-label small">Paid Amount</label>
                        <input type="number" step="0.01" class="form-control" id="bill-paid" value="0" min="0">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Balance</label>
                        <input type="text" class="form-control" id="bill-balance" value="Rs.0.00" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Loyalty Points to Redeem</label>
                        <input type="number" class="form-control" id="bill-points" value="0" min="0" step="1">
                        <small class="text-muted-fg">Min 100 points. Requires supervisor authorization.</small>
                    </div>
                    <div class="mb-2" id="loyalty-auth-block" style="display:none;">
                        <label class="form-label small">Authorizer Username</label>
                        <input type="text" class="form-control form-control-sm" id="auth-username">
                        <label class="form-label small mt-1">Authorizer Password</label>
                        <input type="password" class="form-control form-control-sm" id="auth-password">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Print Format</label>
                        <select class="form-select form-select-sm" id="bill-print-format">
                            <option value="thermal">Thermal (80mm)</option>
                            <option value="a4">A4</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Remarks</label>
                        <textarea class="form-control form-control-sm" id="bill-remarks" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" id="btn-checkout" <?= $canManage ? '' : 'disabled' ?>>
                        <i class="bi bi-bag-check me-1"></i> Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Post-checkout send dialog -->
<div class="modal fade" id="send-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Bill Created — Send Receipt</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted-fg">Bill <strong id="send-bill-number"></strong> created successfully.</p>
        <input type="hidden" id="send-bill-id">
        <div class="row g-2">
            <div class="col-6"><button class="btn btn-outline-secondary w-100" id="btn-print-thermal"><i class="bi bi-printer"></i> Print Thermal</button></div>
            <div class="col-6"><button class="btn btn-outline-secondary w-100" id="btn-print-a4"><i class="bi bi-file-text"></i> Print A4</button></div>
            <div class="col-6"><button class="btn btn-outline-secondary w-100" id="btn-whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</button></div>
            <div class="col-6"><button class="btn btn-outline-secondary w-100" id="btn-email"><i class="bi bi-envelope"></i> Email</button></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- Variant selector modal -->
<div class="modal fade" id="variant-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Select Variant</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div id="variant-list" class="list-group"></div></div>
    </div>
  </div>
</div>

<!-- Sub-item selector modal -->
<div class="modal fade" id="subitem-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Sub-item Options</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div id="subitem-list" class="list-group"></div></div>
    </div>
  </div>
</div>

<script src="/assets/js/billing.js"></script>
