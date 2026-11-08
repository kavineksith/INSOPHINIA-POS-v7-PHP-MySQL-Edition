<?php
/**
 * PageController — renders HTML pages.
 *
 * All non-API requests fall through to render(). The method maps URL path to
 * a View file. Auth is enforced for all dashboard pages; login/logout pages
 * are public.
 *
 * Layout strategy:
 *   - /login → layouts/auth.php
 *   - everything else (after auth check) → layouts/dashboard.php
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\Permissions;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Settings;
use Insophinia\POS\Lib\Csrf;
use Insophinia\POS\Models\User;

class PageController extends Controller
{
    /** Map URL path → [view file, required permission, page title]. */
    private static array $pages = [
        '/login'      => ['login.php', null, 'Login'],
        '/dashboard'  => ['dashboard.php', 'view:dashboard', 'Dashboard'],
        '/billing'    => ['billing.php', 'view:billing', 'Billing'],
        '/bills'      => ['bills.php', 'view:bills', 'Bills'],
        '/items'      => ['items.php', 'view:items', 'Items & Variants'],
        '/categories' => ['categories.php', 'view:categories', 'Categories'],
        '/customers'  => ['customers.php', 'view:customers', 'Customers'],
        '/warranties' => ['warranties.php', 'view:warranties', 'Warranty & Claims'],
        '/repairs'    => ['repairs.php', 'view:repairs', 'Repairs'],
        '/marketing'  => ['marketing.php', 'view:marketing', 'Marketing'],
        '/reports'    => ['reports.php', 'view:reports', 'Reports'],
        '/settings'   => ['settings.php', 'view:settings', 'Settings'],
        '/security'   => ['security.php', 'view:security', 'Security'],
        '/logs'       => ['logs.php', 'view:logs', 'System Logs'],
    ];

    /** Render the page for the given path. */
    public function render(string $path): void
    {
        // Root → redirect to /dashboard or /login
        if ($path === '/' || $path === '') {
            $token = Auth::getTokenFromRequest($this->request);
            if ($token) {
                try {
                    Auth::authenticate($this->request);
                    header('Location: /dashboard');
                } catch (\Throwable) {
                    header('Location: /login');
                }
            } else {
                header('Location: /login');
            }
            exit;
        }

        if (!isset(self::$pages[$path])) {
            // 404
            http_response_code(404);
            $this->renderLayout('auth.php', ['title' => '404 Not Found', 'content' => '<div class="text-center p-5"><h1>404</h1><p>Page not found.</p><a href="/dashboard" class="btn btn-primary mt-3">Go to Dashboard</a></div>']);
            return;
        }

        [$viewFile, $permission, $title] = self::$pages[$path];

        // Auth: login is public; everything else requires a valid JWT
        if ($path !== '/login') {
            try {
                $result = Auth::authenticate($this->request);
                $this->user = $result['user'];
                $this->token = $result['token'];
                // Refresh cookie if rotated
                if (!empty($result['newCookie'])) {
                    header('Set-Cookie: ' . $result['newCookie'], false);
                }
            } catch (HttpException $e) {
                // Redirect to login
                header('Location: /login');
                exit;
            }
            // Permission check
            if ($permission !== null && !Permissions::has($this->userRole(), $permission)) {
                http_response_code(403);
                $this->renderLayout('auth.php', ['title' => '403 Forbidden', 'content' => '<div class="text-center p-5"><h1>403</h1><p>You do not have permission to access this page.</p><a href="/dashboard" class="btn btn-primary mt-3">Go to Dashboard</a></div>']);
                return;
            }
        } else {
            // If user is already logged in and visits /login, redirect to dashboard
            try {
                Auth::authenticate($this->request);
                header('Location: /dashboard');
                exit;
            } catch (\Throwable) {
                // Not logged in — proceed to render login page
            }
        }

        // Render the view, captured into a string
        $content = $this->captureView($viewFile);

        // Choose layout
        $layout = ($path === '/login') ? 'auth.php' : 'dashboard.php';

        // Send CSRF cookie on every page (so JS can read it)
        Csrf::sendCookie();

        $this->renderLayout($layout, [
            'title'   => $title,
            'content' => $content,
            'user'    => $this->user,
        ]);
    }

    /** Capture a view file's output into a string. */
    private function captureView(string $viewFile): string
    {
        $viewPath = __DIR__ . '/../Views/' . $viewFile;
        if (!is_file($viewPath)) {
            return "<div class='alert alert-danger'>View file not found: {$viewFile}</div>";
        }
        ob_start();
        // Make $user, $request, and helper functions available to view
        $user = $this->user;
        $request = $this->request;
        $csrfToken = Csrf::currentToken();
        $settings = Settings::all();
        require $viewPath;
        return (string)ob_get_clean();
    }

    /** Render a layout file with the given vars. */
    private function renderLayout(string $layoutFile, array $vars): void
    {
        $layoutPath = __DIR__ . '/../Views/layouts/' . $layoutFile;
        if (!is_file($layoutPath)) {
            echo "Layout not found: {$layoutFile}";
            return;
        }
        extract($vars, EXTR_SKIP);
        require $layoutPath;
    }

    /** Helper used by Sidebar view to fetch the current user's full record. */
    public static function getUserRecord(?string $userId): ?array
    {
        if (!$userId) return null;
        return (new User())->findActiveById($userId);
    }
}
