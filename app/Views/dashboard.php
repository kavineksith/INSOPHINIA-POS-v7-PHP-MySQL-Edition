<?php /** Dashboard page — KPI cards */
use Insophinia\POS\Lib\Settings;
$shopName = Settings::get('shop_name', 'INSOPHINIA');
?>
<div class="d-flex flex-column gap-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted-fg mb-0">Welcome to <?= e($shopName) ?> POS — overview of today's performance.</p>
    </div>

    <div class="row g-3" id="kpi-grid">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Today's Sales</div>
                        <div class="stat-card-value" id="kpi-today-sales">Rs.0.00</div>
                    </div>
                    <div class="stat-card-icon icon-blue"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Today's Bills</div>
                        <div class="stat-card-value" id="kpi-today-bills">0</div>
                    </div>
                    <div class="stat-card-icon icon-green"><i class="bi bi-receipt"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Total Items</div>
                        <div class="stat-card-value" id="kpi-total-items">0</div>
                    </div>
                    <div class="stat-card-icon icon-orange"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Low Stock Items</div>
                        <div class="stat-card-value" id="kpi-low-stock">0</div>
                    </div>
                    <div class="stat-card-icon icon-red"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Active Warranties</div>
                        <div class="stat-card-value" id="kpi-active-warranties">0</div>
                    </div>
                    <div class="stat-card-icon icon-purple"><i class="bi bi-shield-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Pending Claims</div>
                        <div class="stat-card-value" id="kpi-pending-claims">0</div>
                    </div>
                    <div class="stat-card-icon icon-orange"><i class="bi bi-file-earmark-exclamation"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Active Repairs</div>
                        <div class="stat-card-value" id="kpi-active-repairs">0</div>
                    </div>
                    <div class="stat-card-icon icon-blue"><i class="bi bi-wrench"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-card-title">Total Customers</div>
                        <div class="stat-card-value" id="kpi-total-customers">0</div>
                    </div>
                    <div class="stat-card-icon icon-green"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Feature Modules</h5>
                        <p class="card-description mb-0">Quick access to POS modules</p>
                    </div>
                    <i class="bi bi-grid-3x3-gap text-muted-fg" style="font-size:1.25rem;"></i>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-4"><a href="/billing" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-cart3 me-2"></i> New Bill</a></div>
                        <div class="col-6 col-md-4"><a href="/items" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-box-seam me-2"></i> Items</a></div>
                        <div class="col-6 col-md-4"><a href="/customers" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-people me-2"></i> Customers</a></div>
                        <div class="col-6 col-md-4"><a href="/warranties" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-shield-check me-2"></i> Warranties</a></div>
                        <div class="col-6 col-md-4"><a href="/repairs" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-wrench me-2"></i> Repairs</a></div>
                        <div class="col-6 col-md-4"><a href="/reports" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-bar-chart me-2"></i> Reports</a></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Security Posture</h5>
                        <p class="card-description mb-0">Current security status</p>
                    </div>
                    <i class="bi bi-shield-lock text-muted-fg" style="font-size:1.25rem;"></i>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between bg-transparent text-fg"><span><i class="bi bi-check-circle text-success me-2"></i> JWT auth with HttpOnly cookies</span><span class="badge bg-success">Active</span></li>
                        <li class="list-group-item d-flex justify-content-between bg-transparent text-fg"><span><i class="bi bi-check-circle text-success me-2"></i> CSRF token verification</span><span class="badge bg-success">Active</span></li>
                        <li class="list-group-item d-flex justify-content-between bg-transparent text-fg"><span><i class="bi bi-check-circle text-success me-2"></i> Rate limiting (per-IP)</span><span class="badge bg-success">Active</span></li>
                        <li class="list-group-item d-flex justify-content-between bg-transparent text-fg"><span><i class="bi bi-check-circle text-success me-2"></i> Audit logging</span><span class="badge bg-success">Active</span></li>
                        <li class="list-group-item d-flex justify-content-between bg-transparent text-fg"><span><i class="bi bi-check-circle text-success me-2"></i> RBAC (4 roles × 33 permissions)</span><span class="badge bg-success">Active</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    Insophinia.api.get('/api/dashboard').then(function (r) {
        if (!r.success) return;
        const d = r.data;
        $('#kpi-today-sales').text(Insophinia.format.money(d.today_sales));
        $('#kpi-today-bills').text(d.today_bill_count);
        $('#kpi-total-items').text(d.total_items);
        $('#kpi-low-stock').text(d.low_stock_items);
        $('#kpi-active-warranties').text(d.active_warranties);
        $('#kpi-pending-claims').text(d.pending_claims);
        $('#kpi-active-repairs').text(d.active_repairs);
        $('#kpi-total-customers').text(d.total_customers);
    });
});
</script>
