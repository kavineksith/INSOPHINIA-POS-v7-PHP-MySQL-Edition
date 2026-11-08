<?php /** Bills list page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:billing');
?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Bills</h1>
        <p class="text-muted-fg mb-0">Browse, print, and send bills.</p>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="bill-search" placeholder="Search by bill number, customer…">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" id="bill-status-filter">
                        <option value="">All status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="returned">Returned</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" id="bill-trashed-filter" title="Show soft-deleted bills">
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
                            <th>Bill No.</th><th>Customer</th><th>Cashier</th>
                            <th class="text-end">Total</th><th>Status</th><th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bill-tbody">
                        <tr><td colspan="7" class="text-center text-muted-fg py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="bill-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="bill-pagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Bill detail modal -->
<div class="modal fade" id="bill-detail-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="bill-detail-title">Bill Detail</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="bill-detail-body">Loading…</div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" id="btn-bill-print-thermal"><i class="bi bi-printer"></i> Thermal</button>
        <button class="btn btn-outline-secondary btn-sm" id="btn-bill-print-a4"><i class="bi bi-file-text"></i> A4 Preview</button>
        <button class="btn btn-outline-secondary btn-sm" id="btn-bill-whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</button>
        <button class="btn btn-outline-secondary btn-sm" id="btn-bill-email"><i class="bi bi-envelope"></i> Email</button>
        <button class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/bills.js"></script>
