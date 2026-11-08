<?php
/** Dashboard layout — sidebar + header + main content area. */
use Insophinia\POS\Lib\Permissions;
use Insophinia\POS\Lib\Csrf;

$user = $user ?? null;
$userRecord = $user ? \Insophinia\POS\Controllers\PageController::getUserRecord($user['user_id'] ?? null) : null;
$userRole = $user['role'] ?? '';
$userFirst = $userRecord['first_name'] ?? ($user['username'] ?? '');
$userLast = $userRecord['last_name'] ?? '';
$csrfToken = Csrf::currentToken();

// Navigation items (mirror Sidebar.tsx)
$navItems = [
    ['href' => '/dashboard',  'label' => 'Dashboard',          'icon' => 'bi-grid-1x2',       'permission' => 'view:dashboard'],
    ['href' => '/billing',    'label' => 'Billing',             'icon' => 'bi-cart3',          'permission' => 'view:billing'],
    ['href' => '/bills',      'label' => 'Bills',               'icon' => 'bi-receipt',        'permission' => 'view:bills'],
    ['href' => '/items',      'label' => 'Items & Variants',    'icon' => 'bi-box-seam',       'permission' => 'view:items'],
    ['href' => '/categories', 'label' => 'Categories',          'icon' => 'bi-tag',            'permission' => 'view:categories'],
    ['href' => '/customers',  'label' => 'Customers',           'icon' => 'bi-people',         'permission' => 'view:customers'],
    ['href' => '/warranties', 'label' => 'Warranty & Claims',   'icon' => 'bi-shield-check',   'permission' => 'view:warranties'],
    ['href' => '/repairs',    'label' => 'Repairs & Maintenance','icon' => 'bi-wrench',        'permission' => 'view:repairs'],
    ['href' => '/marketing',  'label' => 'Marketing',           'icon' => 'bi-megaphone',      'permission' => 'view:marketing'],
    ['href' => '/reports',    'label' => 'Reports',             'icon' => 'bi-bar-chart',      'permission' => 'view:reports'],
    ['href' => '/settings',   'label' => 'Settings',            'icon' => 'bi-gear',           'permission' => 'view:settings'],
    ['href' => '/security',   'label' => 'Security',            'icon' => 'bi-shield-exclamation','permission' => 'view:security'],
    ['href' => '/logs',       'label' => 'System Logs',         'icon' => 'bi-file-text',      'permission' => 'view:logs'],
];
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = parse_url($currentPath, PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#000000">
    <title><?= e($title ?? 'Dashboard') ?> — INSOPHINIA POS v7</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-layout" id="app-layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="app-main">
        <header class="app-header">
            <div class="app-header-spacer"></div>
            <div class="d-none d-lg-block">
                <p class="text-muted-fg mb-0" style="font-size:0.875rem;">
                    Welcome back, <span class="fw-semibold text-fg"><?= e($userFirst) ?> <?= e($userLast) ?></span>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle" type="button" aria-label="Toggle theme">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="role-badge">
                    <span class="role-badge-label">Role:</span>
                    <span class="role-badge-value"><?= e(str_replace('_', ' ', $userRole)) ?></span>
                </div>
            </div>
        </header>
        <div class="app-content">
            <div class="app-content-inner">
                <?= $content ?>
            </div>
        </div>
    </main>
</div>
<input type="hidden" id="csrf-token-hidden" value="<?= e($csrfToken) ?>">
<script>
// Expose permission flags for use by page-specific JS
window.CAN_MANAGE_CATEGORY = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:categories') ? 'true' : 'false' ?>;
window.CAN_MANAGE_ITEM = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:items') ? 'true' : 'false' ?>;
window.CAN_MANAGE_CUSTOMER = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:customers') ? 'true' : 'false' ?>;
window.CAN_MANAGE_BILLING = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:billing') ? 'true' : 'false' ?>;
window.CAN_MANAGE_WARRANTY = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:warranties') ? 'true' : 'false' ?>;
window.CAN_MANAGE_REPAIR = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:repairs') ? 'true' : 'false' ?>;
window.CAN_MANAGE_MARKETING = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:marketing') ? 'true' : 'false' ?>;
window.CAN_MANAGE_TEMPLATE = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:templates') ? 'true' : 'false' ?>;
window.CAN_MANAGE_SETTINGS = <?= \Insophinia\POS\Lib\Permissions::has($userRole, 'manage:settings') ? 'true' : 'false' ?>;
window.CAN_FORCE_DELETE = <?= \Insophinia\POS\Lib\Permissions::canForceDelete($userRole) ? 'true' : 'false' ?>;
window.USER_ROLE = <?= json_encode($userRole) ?>;
</script>
<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/theme.js"></script>
</body>
</html>
