<?php /** Categories page */
$userRole = $user['role'] ?? '';
$canManage = \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:categories');
?>
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Categories</h1>
            <p class="text-muted-fg mb-0">Manage product categories.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="btn-new-category"><i class="bi bi-plus-lg me-1"></i> New Category</button>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="cat-search" placeholder="Search categories…">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" id="cat-page-size">
                        <option value="10">10 / page</option>
                        <option value="20" selected>20 / page</option>
                        <option value="50">50 / page</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" id="cat-trashed-filter" title="Show soft-deleted records">
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
                            <th>Name</th><th>Description</th><th class="text-center">Items</th><th>Status</th>
                            <?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="cat-tbody">
                        <tr><td colspan="<?= $canManage ? 5 : 4 ?>" class="text-center text-muted-fg py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="cat-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="cat-pagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Modal: Category form -->
<div class="modal fade" id="cat-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="cat-form">
        <div class="modal-header">
          <h5 class="modal-title" id="cat-modal-title">New Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="cat-id">
          <div class="mb-3">
            <label class="form-label">Name *</label>
            <input type="text" class="form-control" id="cat-name" required maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="cat-description" rows="3" maxlength="1000"></textarea>
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

<script src="/assets/js/categories.js"></script>
