<?php /** Customers page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:customers');
?>
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Customers</h1>
            <p class="text-muted-fg mb-0">Manage customer database and loyalty points.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="btn-new-customer"><i class="bi bi-plus-lg me-1"></i> New Customer</button>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="cust-search" placeholder="Search by name, phone, or email…">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select class="form-select" id="cust-filter-optin">
                        <option value="">All customers</option>
                        <option value="true">Marketing opted-in</option>
                        <option value="false">Marketing opted-out</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <select class="form-select" id="cust-trashed-filter" title="Show soft-deleted records">
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
                            <th>Name</th><th>Phone</th><th>Email</th><th class="text-end">Loyalty Points</th>
                            <th>Marketing</th><th>Created</th>
                            <?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="cust-tbody">
                        <tr><td colspan="<?= $canManage ? 7 : 6 ?>" class="text-center text-muted-fg py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="cust-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="cust-pagination"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="cust-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="cust-form">
        <div class="modal-header">
          <h5 class="modal-title" id="cust-modal-title">New Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="cust-id">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name *</label><input type="text" class="form-control" id="cust-name" required maxlength="255"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" id="cust-phone" maxlength="20"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" id="cust-email" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label">Loyalty Points</label><input type="number" class="form-control" id="cust-loyalty" min="0" value="0"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" id="cust-address" rows="2" maxlength="500"></textarea></div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="cust-optin">
                <label class="form-check-label" for="cust-optin">Marketing opt-in (customer consents to receive promotional emails)</label>
              </div>
            </div>
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

<script src="/assets/js/customers.js"></script>
