<?php /** Warranties & claims page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:warranties');
?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Warranty & Claims</h1>
        <p class="text-muted-fg mb-0">Track warranties and review filed claims.</p>
    </div>

    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-warranties">Warranties</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-claims">Claims</a></li>
    </ul>

    <div class="tab-content">
        <!-- Warranties tab -->
        <div class="tab-pane fade show active" id="tab-warranties">
            <div class="card">
                <div class="card-header">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="war-search" placeholder="Search by warranty number, item, or serial…">
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Warranty No.</th><th>Item</th><th>Customer</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="war-tbody"><tr><td colspan="6" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted-fg" style="font-size:0.8125rem;" id="war-pagination-info">—</span>
                    <nav><ul class="pagination mb-0" id="war-pagination"></ul></nav>
                </div>
            </div>
        </div>

        <!-- Claims tab -->
        <div class="tab-pane fade" id="tab-claims">
            <div class="card">
                <div class="card-header">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="claim-search" placeholder="Search by claim number or item…">
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Claim No.</th><th>Warranty</th><th>Item</th><th>Filed</th><th>Status</th>
                        <?php if ($canManage): ?><th class="text-end">Actions</th><?php else: ?><th></th><?php endif; ?></tr></thead>
                        <tbody id="claim-tbody"><tr><td colspan="6" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted-fg" style="font-size:0.8125rem;" id="claim-pagination-info">—</span>
                    <nav><ul class="pagination mb-0" id="claim-pagination"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File claim modal -->
<div class="modal fade" id="file-claim-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="file-claim-form">
        <div class="modal-header"><h5 class="modal-title">File Warranty Claim</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="claim-warranty-id">
          <div class="mb-3"><label class="form-label">Issue Description *</label><textarea class="form-control" id="claim-issue" rows="4" required maxlength="2000"></textarea></div>
          <div class="mb-3"><label class="form-label">Fault Category</label>
            <select class="form-select" id="claim-fault">
              <option value="">— Select —</option>
              <option value="hardware">Hardware</option>
              <option value="software">Software</option>
              <option value="power">Power</option>
              <option value="display">Display</option>
              <option value="battery">Battery</option>
              <option value="network">Network</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">File Claim</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Review claim modal -->
<div class="modal fade" id="review-claim-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="review-claim-form">
        <div class="modal-header"><h5 class="modal-title">Review Claim</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="review-claim-id">
          <div class="mb-3"><label class="form-label">Status *</label>
            <select class="form-select" id="review-status" required>
              <option value="under_review">Under Review</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="repaired">Repaired</option>
              <option value="replaced">Replaced</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Assessment Notes</label><textarea class="form-control" id="review-assessment" rows="3"></textarea></div>
          <div class="mb-3"><label class="form-label">Resolution Notes</label><textarea class="form-control" id="review-resolution" rows="3"></textarea></div>
          <div class="mb-3"><label class="form-label">Resolution Type</label>
            <select class="form-select" id="review-resolution-type">
              <option value="">—</option>
              <option value="repair">Repair</option>
              <option value="replace">Replace</option>
              <option value="refund">Refund</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit Review</button></div>
      </form>
    </div>
  </div>
</div>

<script src="/assets/js/warranties.js"></script>
