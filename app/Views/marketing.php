<?php /** Marketing page */
$userRole = $user['role'] ?? '';
$canManageTemplates = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:templates');
$canManageCampaigns = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:marketing');
?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Marketing</h1>
        <p class="text-muted-fg mb-0">Email templates and bulk campaigns.</p>
    </div>
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-campaigns">Campaigns</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-templates">Email Templates</a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-campaigns">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="input-group" style="max-width:24rem;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="camp-search" placeholder="Search campaigns…">
                    </div>
                    <?php if ($canManageCampaigns): ?>
                    <button class="btn btn-primary btn-sm" id="btn-new-campaign"><i class="bi bi-plus-lg"></i> New Campaign</button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Template</th><th>Status</th><th class="text-center">Sent</th><th class="text-center">Failed</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="camp-tbody"><tr><td colspan="7" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-templates">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="input-group" style="max-width:24rem;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="tpl-search" placeholder="Search templates…">
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="tpl-trashed-filter" title="Show soft-deleted templates" style="width:auto;">
                            <option value="hide" selected>Hide deleted</option>
                            <option value="with">With deleted</option>
                            <option value="only">Only deleted</option>
                        </select>
                        <?php if ($canManageTemplates): ?>
                        <button class="btn btn-primary btn-sm" id="btn-new-template"><i class="bi bi-plus-lg"></i> New Template</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Type</th><th>Subject</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="tpl-tbody"><tr><td colspan="5" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campaign modal -->
<div class="modal fade" id="camp-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="camp-form">
        <div class="modal-header"><h5 class="modal-title">New Campaign</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Campaign Name *</label><input type="text" class="form-control" id="camp-name" required></div>
            <div class="col-md-4"><label class="form-label">Template *</label><select class="form-select" id="camp-template" required></select></div>
            <div class="col-md-6"><label class="form-label">Audience Type</label>
              <select class="form-select" id="camp-audience-type">
                <option value="single">Single Recipient</option>
                <option value="bulk">Bulk (Opted-in Customers)</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Recipient Email (single)</label><input type="email" class="form-control" id="camp-recipient-email"></div>
            <div class="col-md-6"><label class="form-label">Recipient Name (single)</label><input type="text" class="form-control" id="camp-recipient-name"></div>
            <div class="col-md-6"><label class="form-label">Subject Override</label><input type="text" class="form-control" id="camp-subject"></div>
            <div class="col-12"><label class="form-label">Audience Filter (JSON, for bulk)</label><textarea class="form-control" id="camp-filter" rows="3" placeholder='{"has_email": true, "opted_in": true, "min_loyalty_points": 100}'></textarea></div>
            <div class="col-12"><label class="form-label">Custom Variables (JSON)</label><textarea class="form-control" id="camp-vars" rows="2" placeholder='{"promotion": "20% off"}'></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Template modal -->
<div class="modal fade" id="tpl-modal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="tpl-form">
        <div class="modal-header"><h5 class="modal-title">Email Template</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="tpl-id">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name *</label><input type="text" class="form-control" id="tpl-name" required></div>
            <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" id="tpl-type">
              <option value="marketing">Marketing</option><option value="bill">Bill</option><option value="warranty">Warranty</option><option value="repair">Repair</option>
            </select></div>
            <div class="col-md-3"><label class="form-label">Active</label>
              <div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="tpl-active" checked><label class="form-check-label" for="tpl-active">Active</label></div>
            </div>
            <div class="col-12"><label class="form-label">Subject *</label><input type="text" class="form-control" id="tpl-subject" required maxlength="200"></div>
            <div class="col-12"><label class="form-label">HTML Body *</label><textarea class="form-control" id="tpl-html" rows="8" required></textarea>
              <small class="text-muted-fg">Use <code>{{customer_name}}</code>, <code>{{shop_name}}</code> etc. for placeholders.</small></div>
            <div class="col-12"><label class="form-label">Text Body (optional)</label><textarea class="form-control" id="tpl-text" rows="4"></textarea></div>
            <div class="col-12"><label class="form-label">Default Variables (JSON)</label><textarea class="form-control" id="tpl-default-vars" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Preview modal -->
<div class="modal fade" id="preview-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Campaign Preview</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="preview-body">Loading…</div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btn-send-campaign"><i class="bi bi-send"></i> Send Now</button>
        <button class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/marketing.js"></script>
