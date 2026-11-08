<?php /** Repairs page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:repairs');
?>
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Repairs & Maintenance</h1>
            <p class="text-muted-fg mb-0">Track incoming repairs, status, parts, and delivery.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="btn-new-repair"><i class="bi bi-plus-lg me-1"></i> New Repair</button>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6"><div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="rep-search" placeholder="Search by job number, customer, item…">
                </div></div>
                <div class="col-12 col-md-3">
                    <select class="form-select" id="rep-status-filter">
                        <option value="">All status</option>
                        <option value="received">Received</option>
                        <option value="in_progress">In Progress</option>
                        <option value="awaiting_parts">Awaiting Parts</option>
                        <option value="ready_for_pickup">Ready for Pickup</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="returned_unrepaired">Returned Unrepaired</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Job No.</th><th>Customer</th><th>Item</th><th>Status</th><th class="text-end">Total Cost</th><th>Received</th><th class="text-end">Actions</th></tr></thead>
                <tbody id="rep-tbody"><tr><td colspan="7" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="rep-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="rep-pagination"></ul></nav>
        </div>
    </div>
</div>

<!-- New repair modal -->
<div class="modal fade" id="rep-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="rep-form">
        <div class="modal-header"><h5 class="modal-title">New Repair Job</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Customer Name *</label><input type="text" class="form-control" id="rep-customer-name" required maxlength="255"></div>
            <div class="col-md-3"><label class="form-label">Phone</label><input type="tel" class="form-control" id="rep-customer-phone"></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" id="rep-customer-email"></div>
            <div class="col-md-6"><label class="form-label">Item Description *</label><input type="text" class="form-control" id="rep-item-desc" required maxlength="500"></div>
            <div class="col-md-3"><label class="form-label">Item Serial</label><input type="text" class="form-control" id="rep-item-serial"></div>
            <div class="col-md-3"><label class="form-label">Access Password</label><input type="text" class="form-control" id="rep-access-pw"></div>
            <div class="col-12"><label class="form-label">Fault Description *</label><textarea class="form-control" id="rep-fault" rows="3" required maxlength="2000"></textarea></div>
            <div class="col-md-6"><label class="form-label">Accessories</label><textarea class="form-control" id="rep-accessories" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label">Condition Notes</label><textarea class="form-control" id="rep-condition" rows="2"></textarea></div>
            <div class="col-md-3"><label class="form-label">Estimated Cost</label><input type="number" step="0.01" class="form-control" id="rep-est-cost" value="0" min="0"></div>
            <div class="col-md-3"><label class="form-label">Estimated Hours</label><input type="number" step="0.01" class="form-control" id="rep-est-hours" value="0" min="0"></div>
            <div class="col-md-3"><label class="form-label">Labor Cost</label><input type="number" step="0.01" class="form-control" id="rep-labor" value="0" min="0"></div>
            <div class="col-md-3"><label class="form-label">Warranty (days)</label><input type="number" class="form-control" id="rep-warranty" value="0" min="0" max="3650"></div>
            <div class="col-12"><label class="form-label">Scope of Work</label><textarea class="form-control" id="rep-scope" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" id="rep-notes" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Job</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="rep-detail-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Repair Detail</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="rep-detail-body">Loading…</div>
      <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script src="/assets/js/repairs.js"></script>
