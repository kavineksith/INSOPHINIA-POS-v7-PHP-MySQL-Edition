<?php
/**
 * DashboardController — KPI dashboard
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;

class DashboardController extends Controller
{
    /** GET /api/dashboard */
    public function index(Request $request, array $params = []): Response
    {
        $db = Database::getInstance();

        $todaySales = (float)$db->fetchColumn(
            "SELECT COALESCE(SUM(total_amount), 0) FROM bills
             WHERE DATE(created_at) = CURDATE() AND status = 'completed' AND deleted_at IS NULL"
        );
        $todayBills = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM bills WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL"
        );
        $totalItems = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM items WHERE deleted_at IS NULL AND is_active = 1"
        );
        $lowStockItems = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM items
             WHERE deleted_at IS NULL AND is_active = 1 AND stock_quantity <= low_stock_threshold"
        );
        $activeWarranties = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM warranties WHERE status = 'active'"
        );
        $pendingClaims = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM warranty_claims WHERE status IN ('filed','under_review')"
        );
        $activeRepairs = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM repair_jobs
             WHERE status IN ('received','in_progress','awaiting_parts','ready_for_pickup')"
        );
        $totalCustomers = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL"
        );

        return Response::success([
            'today_sales'      => round($todaySales, 2),
            'today_bill_count' => $todayBills,
            'total_items'      => $totalItems,
            'low_stock_items'  => $lowStockItems,
            'active_warranties'=> $activeWarranties,
            'pending_claims'   => $pendingClaims,
            'active_repairs'   => $activeRepairs,
            'total_customers'  => $totalCustomers,
        ]);
    }
}
