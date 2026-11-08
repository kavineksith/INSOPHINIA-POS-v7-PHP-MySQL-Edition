<?php
/**
 * INSOPHINIA POS v7 — Front Controller
 * ----------------------------------------------------------------------------
 * All requests are routed through this file (via .htaccess mod_rewrite).
 *
 * Workflow:
 *   1. Bootstrap composer autoloader
 *   2. Load config
 *   3. Instantiate App
 *   4. Register routes (API + page fallback)
 *   5. Dispatch
 */

declare(strict_types=1);

// Project root is one level up from /public
define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

use Insophinia\POS\Core\App;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Middleware\AuthMiddleware;
use Insophinia\POS\Middleware\BodySizeMiddleware;
use Insophinia\POS\Middleware\CsrfMiddleware;

$config = require APP_ROOT . '/config/config.php';

$app = new App($config);
$router = $app->getRouter();

// ---- Body size middleware applies to all POST/PUT/PATCH/DELETE ----
$bodySize = BodySizeMiddleware::check();
$csrf = CsrfMiddleware::verify();
$auth = AuthMiddleware::requireAuth();

// Public route (no auth)
$public = AuthMiddleware::publicRoute();

// ============================================================================
// API ROUTES
// ============================================================================

// Root
$router->get('/api', fn() => Response::success(['message' => 'INSOPHINIA POS v7 API']));

// CSRF token
$router->get('/api/csrf', [\Insophinia\POS\Controllers\CsrfController::class, 'index']);

// Auth
$router->post('/api/auth/login', [\Insophinia\POS\Controllers\AuthController::class, 'login'], [$public, $bodySize]);
$router->post('/api/auth/logout', [\Insophinia\POS\Controllers\AuthController::class, 'logout'], [AuthMiddleware::requireAuth(), $bodySize, $csrf]);
$router->get('/api/auth/me', [\Insophinia\POS\Controllers\AuthController::class, 'me'], [AuthMiddleware::requireAuth()]);

// Dashboard
$router->get('/api/dashboard', [\Insophinia\POS\Controllers\DashboardController::class, 'index'], [AuthMiddleware::requireAuth()]);

// Categories
$router->get('/api/categories', [\Insophinia\POS\Controllers\CategoryController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:categories'])]);
$router->post('/api/categories', [\Insophinia\POS\Controllers\CategoryController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:categories']), $bodySize, $csrf]);
$router->get('/api/categories/{id}', [\Insophinia\POS\Controllers\CategoryController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:categories'])]);
$router->put('/api/categories/{id}', [\Insophinia\POS\Controllers\CategoryController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:categories']), $bodySize, $csrf]);
$router->delete('/api/categories/{id}', [\Insophinia\POS\Controllers\CategoryController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:categories']), $csrf]);
$router->patch('/api/categories/{id}/restore', [\Insophinia\POS\Controllers\CategoryController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:categories']), $csrf]);

// Items
$router->get('/api/items', [\Insophinia\POS\Controllers\ItemController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:items'])]);
$router->post('/api/items', [\Insophinia\POS\Controllers\ItemController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->get('/api/items/{id}', [\Insophinia\POS\Controllers\ItemController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:items'])]);
$router->put('/api/items/{id}', [\Insophinia\POS\Controllers\ItemController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->delete('/api/items/{id}', [\Insophinia\POS\Controllers\ItemController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $csrf]);
$router->patch('/api/items/{id}/restore', [\Insophinia\POS\Controllers\ItemController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $csrf]);

// Item variants
$router->get('/api/items/{id}/variants', [\Insophinia\POS\Controllers\ItemController::class, 'variantsIndex'], [AuthMiddleware::requireAuth(['permission' => 'view:items'])]);
$router->post('/api/items/{id}/variants', [\Insophinia\POS\Controllers\ItemController::class, 'variantsStore'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->put('/api/items/{id}/variants/{variantId}', [\Insophinia\POS\Controllers\ItemController::class, 'variantsUpdate'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->delete('/api/items/{id}/variants/{variantId}', [\Insophinia\POS\Controllers\ItemController::class, 'variantsDestroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $csrf]);

// Item sub-items
$router->get('/api/items/{id}/sub-items', [\Insophinia\POS\Controllers\ItemController::class, 'subItemsIndex'], [AuthMiddleware::requireAuth(['permission' => 'view:items'])]);
$router->post('/api/items/{id}/sub-items', [\Insophinia\POS\Controllers\ItemController::class, 'subItemsStore'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->put('/api/items/{id}/sub-items/{subItemId}', [\Insophinia\POS\Controllers\ItemController::class, 'subItemsUpdate'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $bodySize, $csrf]);
$router->delete('/api/items/{id}/sub-items/{subItemId}', [\Insophinia\POS\Controllers\ItemController::class, 'subItemsDestroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:items']), $csrf]);

// Customers
$router->get('/api/customers', [\Insophinia\POS\Controllers\CustomerController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:customers'])]);
$router->post('/api/customers', [\Insophinia\POS\Controllers\CustomerController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:customers']), $bodySize, $csrf]);
$router->get('/api/customers/{id}', [\Insophinia\POS\Controllers\CustomerController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:customers'])]);
$router->put('/api/customers/{id}', [\Insophinia\POS\Controllers\CustomerController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:customers']), $bodySize, $csrf]);
$router->delete('/api/customers/{id}', [\Insophinia\POS\Controllers\CustomerController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:customers']), $csrf]);
$router->patch('/api/customers/{id}/restore', [\Insophinia\POS\Controllers\CustomerController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:customers']), $csrf]);

// Billing
$router->get('/api/billing', [\Insophinia\POS\Controllers\BillingController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:bills'])]);
$router->post('/api/billing', [\Insophinia\POS\Controllers\BillingController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:billing']), $bodySize, $csrf]);
$router->get('/api/billing/{id}', [\Insophinia\POS\Controllers\BillingController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:bills'])]);
$router->patch('/api/billing/{id}', [\Insophinia\POS\Controllers\BillingController::class, 'markPrinted'], [AuthMiddleware::requireAuth(['permission' => 'manage:billing']), $bodySize, $csrf]);
$router->delete('/api/billing/{id}', [\Insophinia\POS\Controllers\BillingController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:billing']), $csrf]);
$router->patch('/api/billing/{id}/restore', [\Insophinia\POS\Controllers\BillingController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:billing']), $csrf]);
$router->get('/api/billing/{id}/pdf', [\Insophinia\POS\Controllers\BillingController::class, 'pdf'], [AuthMiddleware::requireAuth(['permission' => 'view:bills'])]);
$router->post('/api/billing/{id}/send', [\Insophinia\POS\Controllers\BillingController::class, 'send'], [AuthMiddleware::requireAuth(['permission' => 'manage:billing']), $bodySize, $csrf]);

// Warranties
$router->get('/api/warranties', [\Insophinia\POS\Controllers\WarrantyController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:warranties'])]);
$router->get('/api/warranties/{id}', [\Insophinia\POS\Controllers\WarrantyController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:warranties'])]);
$router->patch('/api/warranties/{id}', [\Insophinia\POS\Controllers\WarrantyController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:warranties']), $bodySize, $csrf]);

// Warranty claims
$router->get('/api/warranties/claims', [\Insophinia\POS\Controllers\WarrantyController::class, 'claimsIndex'], [AuthMiddleware::requireAuth(['permission' => 'view:warranties'])]);
$router->post('/api/warranties/claims', [\Insophinia\POS\Controllers\WarrantyController::class, 'claimsStore'], [AuthMiddleware::requireAuth(['permission' => 'manage:warranties']), $bodySize, $csrf]);
$router->patch('/api/warranties/claims/{id}/review', [\Insophinia\POS\Controllers\WarrantyController::class, 'claimsReview'], [AuthMiddleware::requireAuth(['permission' => 'manage:warranties']), $bodySize, $csrf]);

// Repairs
$router->get('/api/repairs', [\Insophinia\POS\Controllers\RepairController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:repairs'])]);
$router->post('/api/repairs', [\Insophinia\POS\Controllers\RepairController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:repairs']), $bodySize, $csrf]);
$router->get('/api/repairs/{id}', [\Insophinia\POS\Controllers\RepairController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:repairs'])]);
$router->put('/api/repairs/{id}', [\Insophinia\POS\Controllers\RepairController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:repairs']), $bodySize, $csrf]);
$router->post('/api/repairs/{id}', [\Insophinia\POS\Controllers\RepairController::class, 'addPart'], [AuthMiddleware::requireAuth(['permission' => 'manage:repairs']), $bodySize, $csrf]);

// Marketing — templates
$router->get('/api/marketing/templates', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:templates'])]);
$router->post('/api/marketing/templates', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:templates']), $bodySize, $csrf]);
$router->get('/api/marketing/templates/{id}', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:templates'])]);
$router->put('/api/marketing/templates/{id}', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:templates']), $bodySize, $csrf]);
$router->delete('/api/marketing/templates/{id}', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:templates']), $csrf]);
$router->patch('/api/marketing/templates/{id}/restore', [\Insophinia\POS\Controllers\MarketingTemplateController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:templates']), $csrf]);

// Marketing — campaigns
$router->get('/api/marketing/campaigns', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:marketing'])]);
$router->post('/api/marketing/campaigns', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:marketing']), $bodySize, $csrf]);
$router->get('/api/marketing/campaigns/{id}', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:marketing'])]);
$router->delete('/api/marketing/campaigns/{id}', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:marketing']), $csrf]);
$router->get('/api/marketing/campaigns/{id}/preview', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'preview'], [AuthMiddleware::requireAuth(['permission' => 'view:marketing'])]);
$router->post('/api/marketing/campaigns/{id}/send', [\Insophinia\POS\Controllers\MarketingCampaignController::class, 'send'], [AuthMiddleware::requireAuth(['permission' => 'manage:marketing']), $bodySize, $csrf]);

// Terms
$router->get('/api/terms', [\Insophinia\POS\Controllers\TermsController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:settings'])]);
$router->post('/api/terms', [\Insophinia\POS\Controllers\TermsController::class, 'store'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $bodySize, $csrf]);
$router->get('/api/terms/{id}', [\Insophinia\POS\Controllers\TermsController::class, 'show'], [AuthMiddleware::requireAuth(['permission' => 'view:settings'])]);
$router->put('/api/terms/{id}', [\Insophinia\POS\Controllers\TermsController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $bodySize, $csrf]);
$router->delete('/api/terms/{id}', [\Insophinia\POS\Controllers\TermsController::class, 'destroy'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $csrf]);
$router->patch('/api/terms/{id}/restore', [\Insophinia\POS\Controllers\TermsController::class, 'restore'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $csrf]);

// Settings
$router->get('/api/settings', [\Insophinia\POS\Controllers\SettingsController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:settings'])]);
$router->put('/api/settings', [\Insophinia\POS\Controllers\SettingsController::class, 'update'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $bodySize, $csrf]);
$router->post('/api/settings/test-email', [\Insophinia\POS\Controllers\SettingsController::class, 'testEmail'], [AuthMiddleware::requireAuth(['permission' => 'manage:settings']), $bodySize, $csrf]);

// Security
$router->get('/api/security/events', [\Insophinia\POS\Controllers\SecurityController::class, 'events'], [AuthMiddleware::requireAuth(['permission' => 'view:security'])]);

// Logs
$router->get('/api/logs', [\Insophinia\POS\Controllers\LogController::class, 'index'], [AuthMiddleware::requireAuth(['permission' => 'view:logs'])]);

// ============================================================================
// DISPATCH
// ============================================================================
$app->run();
