<?php /** Settings page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:settings');
$allSettings = $settings ?? [];
function setting_value($arr, $key, $default = '') { return $arr[$key] ?? $default; }
?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Settings</h1>
        <p class="text-muted-fg mb-0">Shop profile, bill content, terms & conditions, and email configuration.</p>
    </div>
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-shop">Shop Profile</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-bill-content">Bill Content</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-terms">Terms & Conditions</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-email">Email / SMTP</a></li>
    </ul>

    <div class="tab-content">
        <!-- Shop Profile -->
        <div class="tab-pane fade show active" id="tab-shop">
            <div class="card"><div class="card-body">
                <form id="shop-form">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Shop Name</label><input type="text" class="form-control" name="shop_name" value="<?= e(setting_value($allSettings, 'shop_name', 'INSOPHINIA')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Shop Phone</label><input type="text" class="form-control" name="shop_phone" value="<?= e(setting_value($allSettings, 'shop_phone')) ?>"></div>
                        <div class="col-12"><label class="form-label">Shop Address</label><textarea class="form-control" name="shop_address" rows="2"><?= e(setting_value($allSettings, 'shop_address')) ?></textarea></div>
                        <div class="col-md-6"><label class="form-label">Shop Email</label><input type="email" class="form-control" name="shop_email" value="<?= e(setting_value($allSettings, 'shop_email')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Shop Website</label><input type="text" class="form-control" name="shop_website" value="<?= e(setting_value($allSettings, 'shop_website')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Loyalty Point Value (Rs. per point)</label><input type="number" class="form-control" name="loyalty_point_value" value="<?= e(setting_value($allSettings, 'loyalty_point_value', 100)) ?>" min="1"></div>
                        <div class="col-md-4"><label class="form-label">VAT Rate (%)</label><input type="number" step="0.1" class="form-control" name="vat_rate" value="<?= e(setting_value($allSettings, 'vat_rate', 18)) ?>"></div>
                        <div class="col-md-4"><label class="form-label">SSCL Rate (%)</label><input type="number" step="0.1" class="form-control" name="sscl_rate" value="<?= e(setting_value($allSettings, 'sscl_rate', 2.5)) ?>"></div>
                        <div class="col-md-4"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="vat_enabled" value="true" <?= setting_value($allSettings, 'vat_enabled') === true || setting_value($allSettings, 'vat_enabled') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Enable VAT</label></div></div>
                        <div class="col-md-4"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="sscl_enabled" value="true" <?= setting_value($allSettings, 'sscl_enabled') === true || setting_value($allSettings, 'sscl_enabled') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Enable SSCL</label></div></div>
                        <div class="col-md-4"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="whatsapp_enabled" value="true" <?= setting_value($allSettings, 'whatsapp_enabled', true) === true || setting_value($allSettings, 'whatsapp_enabled', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Enable WhatsApp Sharing</label></div></div>
                    </div>
                    <div class="mt-3">
                        <?php if ($canManage): ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
                        <?php else: ?>
                        <div class="alert alert-info">Read-only view (insufficient permissions to edit).</div>
                        <?php endif; ?>
                    </div>
                </form>
            </div></div>
        </div>

        <!-- Bill Content -->
        <div class="tab-pane fade" id="tab-bill-content">
            <div class="card"><div class="card-body">
                <form id="bill-content-form">
                    <h6 class="mb-3">A4 Bill Content</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_a4_show_variants" value="true" <?= setting_value($allSettings, 'bill_a4_show_variants', true) === true || setting_value($allSettings, 'bill_a4_show_variants', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show variant specs on A4 bills</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_a4_show_subitems" value="true" <?= setting_value($allSettings, 'bill_a4_show_subitems', true) === true || setting_value($allSettings, 'bill_a4_show_subitems', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show sub-items breakdown</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_a4_show_warranty" value="true" <?= setting_value($allSettings, 'bill_a4_show_warranty', true) === true || setting_value($allSettings, 'bill_a4_show_warranty', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show warranty info</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_a4_show_revisions" value="true" <?= setting_value($allSettings, 'bill_a4_show_revisions', true) === true || setting_value($allSettings, 'bill_a4_show_revisions', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show revisions pricing</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_a4_show_terms" value="true" <?= setting_value($allSettings, 'bill_a4_show_terms', true) === true || setting_value($allSettings, 'bill_a4_show_terms', true) === 'true' ? 'checked' : '' ?>><label class="form-check-label">Append T&C to A4 bills</label></div></div>
                    </div>
                    <hr class="my-4">
                    <h6 class="mb-3">Thermal Bill Content</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_thermal_show_variants" value="true" <?= setting_value($allSettings, 'bill_thermal_show_variants') === true || setting_value($allSettings, 'bill_thermal_show_variants') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show variants on thermal</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_thermal_show_subitems" value="true" <?= setting_value($allSettings, 'bill_thermal_show_subitems') === true || setting_value($allSettings, 'bill_thermal_show_subitems') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show sub-items on thermal</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_thermal_show_warranty" value="true" <?= setting_value($allSettings, 'bill_thermal_show_warranty') === true || setting_value($allSettings, 'bill_thermal_show_warranty') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Show warranty on thermal</label></div></div>
                        <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bill_thermal_show_terms" value="true" <?= setting_value($allSettings, 'bill_thermal_show_terms') === true || setting_value($allSettings, 'bill_thermal_show_terms') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Append T&C to thermal</label></div></div>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Bill Content</button></div>
                    <?php endif; ?>
                </form>
            </div></div>
        </div>

        <!-- Terms & Conditions -->
        <div class="tab-pane fade" id="tab-terms">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0">Terms & Conditions Templates</h5>
                        <select class="form-select form-select-sm" id="terms-trashed-filter" title="Show soft-deleted templates" style="width:auto;">
                            <option value="hide" selected>Hide deleted</option>
                            <option value="with">With deleted</option>
                            <option value="only">Only deleted</option>
                        </select>
                    </div>
                    <?php if ($canManage): ?>
                    <button class="btn btn-primary btn-sm" id="btn-new-terms"><i class="bi bi-plus-lg"></i> New Template</button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Format</th><th>Version</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="terms-tbody"><tr><td colspan="5" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Email / SMTP -->
        <div class="tab-pane fade" id="tab-email">
            <div class="card"><div class="card-body">
                <form id="email-form">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="email_enabled" value="true" <?= setting_value($allSettings, 'email_enabled') === true || setting_value($allSettings, 'email_enabled') === 'true' ? 'checked' : '' ?>><label class="form-check-label">Enable Email Sending</label></div></div>
                        <div class="col-md-6"><label class="form-label">From Address</label><input type="text" class="form-control" name="email_from" value="<?= e(setting_value($allSettings, 'email_from')) ?>"></div>
                    </div>
                    <p class="text-muted-fg small mt-3 mb-2">SMTP credentials (host, port, username, password) are set in <code>config/config.php</code>.</p>
                    <hr>
                    <h6>Send Test Email</h6>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8"><label class="form-label">Recipient</label><input type="email" class="form-control" id="test-email-recipient" placeholder="you@example.com"></div>
                        <div class="col-md-4"><button type="button" class="btn btn-outline-secondary w-100" id="btn-test-email"><i class="bi bi-send"></i> Send Test</button></div>
                    </div>
                </form>
            </div></div>
        </div>
    </div>
</div>

<!-- Terms modal -->
<div class="modal fade" id="terms-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="terms-form">
        <div class="modal-header"><h5 class="modal-title">Terms Template</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="terms-id">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Name *</label><input type="text" class="form-control" id="terms-name" required></div>
            <div class="col-md-4"><label class="form-label">Format</label><select class="form-select" id="terms-format"><option value="html">HTML</option><option value="markdown">Markdown</option></select></div>
            <div class="col-12"><label class="form-label">Content *</label><textarea class="form-control" id="terms-content" rows="8" required></textarea></div>
            <div class="col-md-6"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="terms-active" checked><label class="form-check-label">Active</label></div></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<script src="/assets/js/settings.js"></script>
