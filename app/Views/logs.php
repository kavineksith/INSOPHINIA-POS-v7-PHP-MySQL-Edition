<?php /** Audit logs page */ ?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">System Logs</h1>
        <p class="text-muted-fg mb-0">Audit trail of all user actions across the system.</p>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="log-search" placeholder="Search by action, entity, or details…">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Action</th><th>Entity</th><th>User</th><th>IP</th><th>Time</th><th>Details</th></tr></thead>
                    <tbody id="log-tbody"><tr><td colspan="6" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted-fg" style="font-size:0.8125rem;" id="log-pagination-info">—</span>
            <nav><ul class="pagination mb-0" id="log-pagination"></ul></nav>
        </div>
    </div>
</div>
<script src="/assets/js/logs.js"></script>
