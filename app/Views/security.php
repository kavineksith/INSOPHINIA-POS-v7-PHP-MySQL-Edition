<?php /** Security events page */ ?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Security</h1>
        <p class="text-muted-fg mb-0">Recent security events — logins, logouts, lockouts, suspicious activity.</p>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-title">Login Success</div><div class="stat-card-value" id="sec-login-success">0</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-title">Login Failed</div><div class="stat-card-value" id="sec-login-failed">0</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-title">Suspicious</div><div class="stat-card-value" id="sec-suspicious">0</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-title">Logout</div><div class="stat-card-value" id="sec-logout">0</div></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Recent Events</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Type</th><th>IP</th><th>User</th><th>Severity</th><th>Time</th><th>Details</th></tr></thead>
                    <tbody id="sec-tbody"><tr><td colspan="6" class="text-center text-muted-fg py-4">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/security.js"></script>
