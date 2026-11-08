<?php /** Sidebar partial — driven by $navItems + role permission check */
use Insophinia\POS\Lib\Permissions;
$user = $user ?? null;
$userRecord = $user ? \Insophinia\POS\Controllers\PageController::getUserRecord($user['user_id'] ?? null) : null;
$userFirst = $userRecord['first_name'] ?? '';
$userLast = $userRecord['last_name'] ?? '';
$userRole = $user['role'] ?? '';
$initials = strtoupper(substr($userFirst, 0, 1) . substr($userLast, 0, 1));
?>
<!-- Mobile toggle -->
<button class="sidebar-mobile-toggle" type="button" id="sidebar-mobile-toggle" aria-label="Toggle menu">
    <i class="bi bi-list"></i>
</button>
<!-- Overlay -->
<div class="sidebar-overlay d-lg-none" id="sidebar-overlay" style="display:none;"></div>

<aside class="app-sidebar" id="app-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-shield-check"></i>
        </div>
        <div class="sidebar-label">
            <h1 class="sidebar-brand-name">INSOPHINIA</h1>
            <p class="sidebar-brand-tagline">POS v7</p>
        </div>
        <button class="btn btn-ghost btn-sm ms-auto d-lg-none" id="sidebar-close-mobile" type="button">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item):
            if ($userRole && !Permissions::has($userRole, $item['permission'])) continue;
            $isActive = $currentPath === $item['href'] || str_starts_with($currentPath, $item['href'] . '/');
        ?>
            <a href="<?= e($item['href']) ?>" class="sidebar-nav-item <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span class="sidebar-label"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer / user -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card sidebar-label">
            <div class="sidebar-user-avatar"><?= e($initials) ?></div>
            <div class="min-w-0 flex-fill">
                <p class="sidebar-user-name text-truncate"><?= e($userFirst) ?> <?= e($userLast) ?></p>
                <p class="sidebar-user-role"><?= e($userRole) ?></p>
            </div>
        </div>
        <button class="btn btn-ghost btn-sm w-100 text-destructive" id="logout-btn" type="button">
            <i class="bi bi-box-arrow-right me-1"></i> <span class="sidebar-label">Logout</span>
        </button>
        <button class="btn btn-ghost btn-sm w-100 d-none d-lg-inline-flex mt-1" id="sidebar-collapse-btn" type="button">
            <i class="bi bi-chevron-left" id="sidebar-collapse-icon"></i>
            <span class="sidebar-label ms-2">Collapse</span>
        </button>
    </div>
</aside>

<!-- Spacer for fixed sidebar on desktop -->
<div class="sidebar-spacer" id="sidebar-spacer"></div>

<script>
// Sidebar interactions
(function () {
    const sidebar = document.getElementById('app-sidebar');
    const spacer = document.getElementById('sidebar-spacer');
    const collapseBtn = document.getElementById('sidebar-collapse-btn');
    const collapseIcon = document.getElementById('sidebar-collapse-icon');
    const mobileToggle = document.getElementById('sidebar-mobile-toggle');
    const mobileClose = document.getElementById('sidebar-close-mobile');
    const overlay = document.getElementById('sidebar-overlay');

    // Collapse (desktop only)
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            spacer.classList.toggle('collapsed');
            const collapsed = sidebar.classList.contains('collapsed');
            collapseIcon.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
            try { localStorage.setItem('insophinia-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
        });
        // Restore on load
        try {
            if (localStorage.getItem('insophinia-sidebar-collapsed') === '1') {
                sidebar.classList.add('collapsed');
                spacer.classList.add('collapsed');
                collapseIcon.className = 'bi bi-chevron-right';
            }
        } catch (e) {}
    }

    // Mobile open/close
    function openMobile() {
        sidebar.classList.add('mobile-open');
        if (overlay) overlay.style.display = 'block';
    }
    function closeMobile() {
        sidebar.classList.remove('mobile-open');
        if (overlay) overlay.style.display = 'none';
    }
    if (mobileToggle) mobileToggle.addEventListener('click', openMobile);
    if (mobileClose) mobileClose.addEventListener('click', closeMobile);
    if (overlay) overlay.addEventListener('click', closeMobile);

    // Logout
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function () {
            if (window.Insophinia && Insophinia.auth) {
                Insophinia.auth.logout();
            } else {
                window.location.href = '/login';
            }
        });
    }
})();
</script>
