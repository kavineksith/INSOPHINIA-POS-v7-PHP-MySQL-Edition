<?php /** Items & Variants page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:items');
?>
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Items & Variants</h1>
            <p class="text-muted-fg mb-0">Manage products, variants, and sub-items (freelance deliverables).</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="btn-new-item"><i class="bi bi-plus-lg me-1"></i> New Item</button>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="item-search" placeholder="Search by name, PLU, or barcode…">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" id="item-category-filter"><option value="">All categories</option></select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" id="item-type-filter">
                        <option value="">All types</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                        <option value="composite">Composite</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" id="item-trashed-filter" title="Show soft-deleted records">
                        <option value="hide" selected>Hide deleted (default)</option>
                        <option value="with">Show with deleted</option>
                        <option value="only">Only deleted</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>PLU</th><th>Name</th><th>Category</th><th>Type</th>
                            <th class="text-end">Price</th><th class="text-end">Stock</th>
                            <th>Status</th>
                            <?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="item-tbody">
                        <tr><td colspan="<?= $canManage ? 8 : 7 ?>" class="text-center text-muted-fg py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="item-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="item-pagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Item modal -->
<div class="modal fade" id="item-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="item-form">
        <div class="modal-header">
          <h5 class="modal-title" id="item-modal-title">New Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="item-id">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">PLU Code *</label><input type="text" class="form-control" id="item-plu" required maxlength="50"></div>
            <div class="col-md-4"><label class="form-label">Barcode</label><input type="text" class="form-control" id="item-barcode" maxlength="100"></div>
            <div class="col-md-4"><label class="form-label">QR Code</label><input type="text" class="form-control" id="item-qr" maxlength="100"></div>
            <div class="col-md-8"><label class="form-label">Name *</label><input type="text" class="form-control" id="item-name" required maxlength="255"></div>
            <div class="col-md-4"><label class="form-label">Category *</label><select class="form-select" id="item-category" required></select></div>
            <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" id="item-type"><option value="product">Product</option><option value="service">Service</option><option value="composite">Composite</option></select></div>
            <div class="col-md-3"><label class="form-label">Unit</label><input type="text" class="form-control" id="item-unit" value="pcs" maxlength="50"></div>
            <div class="col-md-3"><label class="form-label">Price (Rs.)</label><input type="number" step="0.01" class="form-control" id="item-price" min="0" value="0" required></div>
            <div class="col-md-3"><label class="form-label">Cost Price (Rs.)</label><input type="number" step="0.01" class="form-control" id="item-cost" min="0" value="0" required></div>
            <div class="col-md-3"><label class="form-label">Stock Qty</label><input type="number" step="0.001" class="form-control" id="item-stock" min="0" value="0"></div>
            <div class="col-md-3"><label class="form-label">Low Stock Threshold</label><input type="number" step="0.001" class="form-control" id="item-lowstock" min="0" value="10"></div>
            <div class="col-md-3"><label class="form-label">Warranty (days)</label><input type="number" class="form-control" id="item-warranty" min="0" max="3650" value="0"></div>
            <div class="col-md-3">
              <div class="form-check form-switch mt-4">
                <input class="form-check-input" type="checkbox" id="item-discount">
                <label class="form-check-label" for="item-discount">Has Discount</label>
              </div>
            </div>
            <div class="col-md-3"><label class="form-label">Discount %</label><input type="number" step="0.01" class="form-control" id="item-discount-pct" min="0" max="100" value="0"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" id="item-description" rows="2" maxlength="2000"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Variants & sub-items modal -->
<div class="modal fade" id="variants-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="variants-modal-title">Variants & Sub-items</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-variants">Variants</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-subitems">Sub-items</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-variants">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-sm btn-primary" id="btn-new-variant"><i class="bi bi-plus"></i> Add Variant</button>
            </div>
            <table class="table table-sm"><thead><tr><th>SKU</th><th>Name</th><th class="text-end">Price Adj</th><th class="text-end">Stock</th><th class="text-end">Actions</th></tr></thead>
              <tbody id="variants-tbody"></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="tab-subitems">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-sm btn-primary" id="btn-new-subitem"><i class="bi bi-plus"></i> Add Sub-item</button>
            </div>
            <table class="table table-sm"><thead><tr><th>Name</th><th class="text-end">Base Price</th><th class="text-center">Revisions Included</th><th class="text-end">Revision Price</th><th class="text-end">Actions</th></tr></thead>
              <tbody id="subitems-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/items.js"></script>
