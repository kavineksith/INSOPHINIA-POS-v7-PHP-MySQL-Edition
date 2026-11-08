<?php
/**
 * BillingController — List, create bill, get by ID, mark printed, PDF gen, send.
 *
 * The POST /api/billing endpoint is the most complex in the system:
 *   - Resolves customer (existing or new)
 *   - Resolves items / variants / sub-items in batch
 *   - Verifies stock availability
 *   - Computes subtotal, discount, revisions, warranty, VAT, SSCL, total
 *   - Loyalty redemption (requires supervisor/admin password)
 *   - Atomic stock decrement via UPDATE...WHERE stock >= qty
 *   - Creates Warranty records for items with warranty_days > 0
 *   - Stores terms_snapshot JSON if A4 bill
 *   - Retries on bill_number race conditions
 *   - All inside a transaction
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\BillTemplate;
use Insophinia\POS\Lib\Email;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Settings;
use Insophinia\POS\Lib\TempPdfStore;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Lib\WhatsApp;
use Insophinia\POS\Models\Bill;
use Insophinia\POS\Models\BillItem;
use Insophinia\POS\Models\Customer;
use Insophinia\POS\Models\Item;
use Insophinia\POS\Models\ItemVariant;
use Insophinia\POS\Models\ItemSubItem;
use Insophinia\POS\Models\StockMovement;
use Insophinia\POS\Models\Warranty;
use Insophinia\POS\Models\TermsTemplate;
use Insophinia\POS\Models\User;

class BillingController extends Controller
{
    /** GET /api/billing */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $status = $request->query('status');
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));
        $result = (new Bill())->listWithRelations($p['page'], $p['limit'], $p['search'], $status, $trashed);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** GET /api/billing/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $bill = (new Bill())->findFull($params['id']);
        if (!$bill || $bill['deleted_at']) {
            throw HttpException::notFound('Bill not found');
        }
        // Decode variant_specs JSON for each item
        foreach ($bill['items'] as &$item) {
            if (!empty($item['variant_specs'])) {
                $item['variant_specs'] = Util::jsonDecode($item['variant_specs'], []);
            }
        }
        return Response::success($bill);
    }

    /** POST /api/billing */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_BILL, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        $db = Database::getInstance();
        $userId = $this->userId();
        $staffName = trim(($this->user['first_name'] ?? '') . ' ' . ($this->user['last_name'] ?? ''));
        if ($staffName === '') $staffName = $this->user['username'] ?? '';

        // Resolve customer
        $customerId = null;
        if (!empty($data['customer_id']) && Validation::isValidUuid($data['customer_id'])) {
            $customer = (new Customer())->find($data['customer_id']);
            if (!$customer || $customer['deleted_at']) {
                throw HttpException::badRequest('Customer not found');
            }
            $customerId = $customer['id'];
            // Use customer's stored info if not overridden in the bill
            $data['customer_name'] = $data['customer_name'] ?? $customer['name'];
            $data['customer_phone'] = $data['customer_phone'] ?? $customer['phone'];
            $data['customer_email'] = $data['customer_email'] ?? $customer['email'];
            $data['customer_address'] = $data['customer_address'] ?? $customer['address'];
        } else {
            $data['customer_name'] = $data['customer_name'] ?? 'Customer';
        }

        // Batch-fetch item IDs
        $itemIds = array_filter(array_map(fn($i) => $i['item_id'] ?? null, $data['items']));
        $variantIds = array_filter(array_map(fn($i) => $i['variant_id'] ?? null, $data['items']));
        $subItemIds = array_filter(array_map(fn($i) => $i['sub_item_id'] ?? null, $data['items']));

        $itemsMap = [];
        if (!empty($itemIds)) {
            $inClause = implode(',', array_fill(0, count($itemIds), '?'));
            $rows = $db->fetchAll("SELECT * FROM items WHERE id IN ($inClause) AND deleted_at IS NULL", $itemIds);
            foreach ($rows as $r) $itemsMap[$r['id']] = $r;
        }
        $variantsMap = [];
        if (!empty($variantIds)) {
            $inClause = implode(',', array_fill(0, count($variantIds), '?'));
            $rows = $db->fetchAll("SELECT * FROM item_variants WHERE id IN ($inClause) AND is_active = 1", $variantIds);
            foreach ($rows as $r) $variantsMap[$r['id']] = $r;
        }
        $subItemsMap = [];
        if (!empty($subItemIds)) {
            $inClause = implode(',', array_fill(0, count($subItemIds), '?'));
            $rows = $db->fetchAll("SELECT * FROM item_sub_items WHERE id IN ($inClause) AND is_active = 1", $subItemIds);
            foreach ($rows as $r) $subItemsMap[$r['id']] = $r;
        }

        // Verify all items exist
        foreach ($data['items'] as $i => $line) {
            if (!isset($itemsMap[$line['item_id']])) {
                throw HttpException::badRequest("Item at position {$i} not found or inactive");
            }
        }

        // ---- Compute subtotal, discount, revisions, warranty ----
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $revisionsSubtotalTotal = 0.0;
        $preparedItems = [];

        foreach ($data['items'] as $line) {
            $item = $itemsMap[$line['item_id']];
            $variant = !empty($line['variant_id']) ? ($variantsMap[$line['variant_id']] ?? null) : null;
            $subItem = !empty($line['sub_item_id']) ? ($subItemsMap[$line['sub_item_id']] ?? null) : null;

            $unitPrice = (float)$item['price'];
            if ($variant) {
                $unitPrice += (float)$variant['price_adjustment'];
            }

            $qty = (float)$line['quantity'];
            $lineSubtotal = $unitPrice * $qty;

            // Item discount (per-line)
            $discount = 0.0;
            if (!empty($item['has_discount']) && (float)$item['discount_percentage'] > 0) {
                $discount = $lineSubtotal * ((float)$item['discount_percentage'] / 100);
            }
            $actualPrice = $unitPrice;
            $lineTotal = $lineSubtotal - $discount;

            // Revisions (sub-items only)
            $revisionsIncluded = 0;
            $revisionsPurchased = 0;
            $revisionPrice = 0.0;
            $lineRevisionSubtotal = 0.0;
            if ($subItem) {
                $revisionsIncluded = (int)$subItem['revisions_included'];
                $revisionsPurchased = (int)($line['revisions_purchased'] ?? 0);
                $revisionPrice = (float)$subItem['revision_price'];
                $extra = max(0, $revisionsPurchased);
                $lineRevisionSubtotal = $extra * $revisionPrice;
                // Add base sub-item price to line
                $lineSubtotal += (float)$subItem['base_price'];
                $lineTotal += (float)$subItem['base_price'];
            }

            $subtotal += $lineSubtotal;
            $totalDiscount += $discount;
            $revisionsSubtotalTotal += $lineRevisionSubtotal;

            // Warranty days (variant overrides item)
            $warrantyDays = (int)$item['warranty_days'];
            if ($variant && (int)$variant['warranty_days'] > 0) {
                $warrantyDays = (int)$variant['warranty_days'];
            }

            $preparedItems[] = [
                'item'                => $item,
                'variant'             => $variant,
                'sub_item'            => $subItem,
                'quantity'            => $qty,
                'unit_price'          => $unitPrice,
                'actual_price'        => $actualPrice,
                'discount'            => $discount,
                'subtotal'            => $lineTotal + $lineRevisionSubtotal,
                'revisions_included'  => $revisionsIncluded,
                'revisions_purchased' => $revisionsPurchased,
                'revision_price'      => $revisionPrice,
                'revisions_subtotal'  => $lineRevisionSubtotal,
                'warranty_days'       => $warrantyDays,
                'line_notes'          => $line['line_notes'] ?? null,
                'unit'                => $line['unit'] ?? $item['unit'],
            ];
        }

        $totalBeforeTax = $subtotal - $totalDiscount + $revisionsSubtotalTotal;

        // Taxes
        $vatAmount = 0.0;
        $ssclAmount = 0.0;
        if (Settings::get('vat_enabled', false)) {
            $vatRate = (float)Settings::get('vat_rate', 18);
            $vatAmount = $totalBeforeTax * ($vatRate / 100);
        }
        if (Settings::get('sscl_enabled', false)) {
            $ssclRate = (float)Settings::get('sscl_rate', 2.5);
            $ssclAmount = $totalBeforeTax * ($ssclRate / 100);
        }
        $taxTotal = $vatAmount + $ssclAmount;

        // Loyalty redemption
        $pointsRedeemed = (int)($data['points_redeemed'] ?? 0);
        $pointsValue = 0.0;
        $pointsAuthorizedBy = null;
        $pointsAuthorizedAt = null;
        if ($pointsRedeemed > 0) {
            if ($pointsRedeemed < 100) {
                throw HttpException::badRequest('Minimum 100 loyalty points required to redeem');
            }
            // Require supervisor/admin password
            if (empty($data['authorizer_username']) || empty($data['authorizer_password'])) {
                throw HttpException::badRequest('Authorizer credentials required for loyalty redemption');
            }
            $authorizer = (new User())->findByUsername($data['authorizer_username']);
            if (!$authorizer || !Auth::checkRole($authorizer['role'], 'supervisor')) {
                throw HttpException::forbidden('Authorizer must be a supervisor or higher');
            }
            if (!Auth::verifyPassword($data['authorizer_password'], $authorizer['password'])) {
                Security::logSecurityEvent([
                    'event_type'  => Security::EVENT_SUSPICIOUS_ACTIVITY,
                    'ip_address'  => Security::getClientIp($request),
                    'user_agent'  => Security::getUserAgent($request),
                    'user_id'     => $userId,
                    'details'     => 'Failed loyalty redemption authorization',
                    'severity'    => 'high',
                ]);
                throw HttpException::forbidden('Invalid authorizer credentials');
            }
            // Check customer has enough points
            if (!$customerId) {
                throw HttpException::badRequest('Customer required for loyalty redemption');
            }
            $customer = (new Customer())->find($customerId);
            if (!$customer || (int)$customer['loyalty_points'] < $pointsRedeemed) {
                throw HttpException::badRequest('Customer has insufficient loyalty points');
            }
            $pointsValue = (float)$pointsRedeemed; // 1 point = Rs. 1
            $pointsAuthorizedBy = $authorizer['first_name'] . ' ' . $authorizer['last_name'];
            $pointsAuthorizedAt = date('Y-m-d H:i:s');
        }

        $totalAmount = $totalBeforeTax + $taxTotal - $pointsValue;
        $paidAmount = (float)$data['paid_amount'];
        $balance = $paidAmount - $totalAmount;

        // Loyalty earned (1 point per Rs. 100 spent)
        $loyaltyValue = (int)Settings::get('loyalty_point_value', 100);
        $earnedPoints = $loyaltyValue > 0 ? (int)floor($totalAmount / $loyaltyValue) : 0;

        // Terms snapshot (A4 only)
        $termsSnapshot = null;
        $printFormat = $data['print_format'] ?? 'thermal';
        if ($printFormat === 'a4' && Settings::get('bill_a4_show_terms', true)) {
            $termsId = (string)Settings::get('bill_a4_active_terms_id', '');
            if ($termsId !== '') {
                $terms = (new TermsTemplate())->find($termsId);
                if ($terms && $terms['is_active'] && !$terms['deleted_at']) {
                    $termsSnapshot = Util::jsonEncode([
                        'template_id' => $terms['id'],
                        'name'        => $terms['name'],
                        'format'      => $terms['format'],
                        'content'     => $terms['content'],
                        'version'     => (int)$terms['version'],
                    ]);
                }
            }
        }

        // ---- Begin transaction ----
        $billModel = new Bill();
        $db->beginTransaction();
        try {
            // Generate bill number (retry on dup)
            $billNumber = $billModel->nextBillNumber();
            $billId = Util::uuid();

            // Atomic stock decrement + create BillItems + Warranties
            $warrantiesCreated = [];
            foreach ($preparedItems as $p) {
                $item = $p['item'];

                // Stock decrement (only for products/composites, not services)
                if ($item['item_type'] !== 'service') {
                    if (!empty($p['variant'])) {
                        $affected = (new ItemVariant())->decrementStock($p['variant']['id'], $p['quantity']);
                        if ($affected === 0) {
                            throw HttpException::conflict("Insufficient stock for variant SKU: " . $p['variant']['sku']);
                        }
                    } else {
                        $affected = (new Item())->decrementStock($item['id'], $p['quantity']);
                        if ($affected === 0) {
                            throw HttpException::conflict("Insufficient stock for item: " . $item['name']);
                        }
                    }
                    // Create stock movement
                    (new StockMovement())->insert([
                        'id'             => Util::uuid(),
                        'item_id'        => $item['id'],
                        'movement_type'  => 'out',
                        'quantity'       => $p['quantity'],
                        'reference_type' => 'bill',
                        'reference_id'   => $billId,
                        'notes'          => 'Bill ' . $billNumber,
                        'movement_date'  => date('Y-m-d H:i:s'),
                        'created_by'     => $userId,
                    ]);
                }

                // Create BillItem
                $billItemId = Util::uuid();
                $warrantyExpiresAt = $p['warranty_days'] > 0
                    ? date('Y-m-d H:i:s', time() + $p['warranty_days'] * 86400)
                    : null;

                (new BillItem())->insert([
                    'id'                  => $billItemId,
                    'bill_id'             => $billId,
                    'item_id'             => $item['id'],
                    'plu_code'            => $item['plu_code'],
                    'item_name'           => $item['name'],
                    'quantity'            => $p['quantity'],
                    'unit'                => $p['unit'],
                    'unit_price'          => $p['unit_price'],
                    'actual_price'        => $p['actual_price'],
                    'discount'            => $p['discount'],
                    'discount_percentage' => $item['discount_percentage'],
                    'subtotal'            => $p['subtotal'],
                    'variant_id'          => $p['variant']['id'] ?? null,
                    'variant_sku'         => $p['variant']['sku'] ?? null,
                    'variant_name'        => $p['variant']['name'] ?? null,
                    'variant_specs'       => $p['variant'] ? ($p['variant']['specs'] ?? '{}') : null,
                    'sub_item_id'         => $p['sub_item']['id'] ?? null,
                    'sub_item_name'       => $p['sub_item']['name'] ?? null,
                    'revisions_included'  => $p['revisions_included'],
                    'revisions_purchased' => $p['revisions_purchased'],
                    'revision_price'      => $p['revision_price'],
                    'revisions_subtotal'  => $p['revisions_subtotal'],
                    'warranty_days'       => $p['warranty_days'],
                    'warranty_expires_at' => $warrantyExpiresAt,
                    'line_notes'          => $p['line_notes'],
                ]);

                // Warranty
                if ($p['warranty_days'] > 0) {
                    $warrantyId = Util::uuid();
                    $warrantyNumber = (new Warranty())->nextWarrantyNumber();
                    (new Warranty())->insert([
                        'id'              => $warrantyId,
                        'warranty_number' => $warrantyNumber,
                        'bill_id'         => $billId,
                        'bill_item_id'    => $billItemId,
                        'customer_id'     => $customerId,
                        'item_id'         => $item['id'],
                        'item_name'       => $item['name'],
                        'variant_sku'     => $p['variant']['sku'] ?? null,
                        'serial_number'   => null,
                        'purchase_date'   => date('Y-m-d H:i:s'),
                        'warranty_days'   => $p['warranty_days'],
                        'expires_at'      => $warrantyExpiresAt,
                        'status'          => 'active',
                    ]);
                    $warrantiesCreated[] = $warrantyId;
                }
            }

            // Loyalty: decrement redeemed, increment earned
            $totalPointsAfter = 0;
            if ($customerId) {
                if ($pointsRedeemed > 0) {
                    (new Customer())->decrementLoyaltyPoints($customerId, $pointsRedeemed);
                }
                if ($earnedPoints > 0) {
                    (new Customer())->incrementLoyaltyPoints($customerId, $earnedPoints);
                }
                $cust = (new Customer())->find($customerId);
                $totalPointsAfter = (int)($cust['loyalty_points'] ?? 0);
            }

            // Create bill
            $billModel->insert([
                'id'                  => $billId,
                'bill_number'         => $billNumber,
                'customer_id'         => $customerId,
                'customer_name'       => $data['customer_name'] ?? 'Customer',
                'customer_phone'      => $data['customer_phone'] ?? null,
                'customer_email'      => !empty($data['customer_email']) ? strtolower($data['customer_email']) : null,
                'customer_address'    => $data['customer_address'] ?? null,
                'staff_id'            => $userId,
                'staff_name'          => $staffName,
                'start_time'          => date('Y-m-d H:i:s'),
                'end_time'            => date('Y-m-d H:i:s'),
                'subtotal'            => $subtotal,
                'total_discount'      => $totalDiscount,
                'vat_amount'          => $vatAmount,
                'sscl_amount'         => $ssclAmount,
                'tax_total'           => $taxTotal,
                'total_amount'        => $totalAmount,
                'paid_amount'         => $paidAmount,
                'balance'             => $balance,
                'status'              => 'completed',
                'remarks'             => $data['remarks'] ?? null,
                'is_printed'          => 0,
                'earned_points'       => $earnedPoints,
                'points_redeemed'     => $pointsRedeemed,
                'points_value'        => $pointsValue,
                'points_authorized_by'=> $pointsAuthorizedBy,
                'points_authorized_at'=> $pointsAuthorizedAt,
                'total_points_after'  => $totalPointsAfter,
                'terms_snapshot'      => $termsSnapshot,
            ]);

            $db->commit();
        } catch (HttpException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Security::auditLog([
            'user_id'    => $userId,
            'action'     => 'BILL_CREATE',
            'entity'     => 'bill',
            'entity_id'  => $billId,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
            'details'    => "bill_number={$billNumber}; total={$totalAmount}",
        ]);

        return Response::success($billModel->findFull($billId), 'Bill created', 201);
    }

    /** PATCH /api/billing/{id} — mark printed. */
    public function markPrinted(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $bill = (new Bill())->find($id);
        if (!$bill || $bill['deleted_at']) {
            throw HttpException::notFound('Bill not found');
        }
        $body = $request->json();
        $format = $body['format'] ?? 'thermal';
        if (!in_array($format, ['thermal', 'a4'], true)) {
            throw HttpException::badRequest('Invalid format');
        }
        (new Bill())->update($id, [
            'is_printed'         => 1,
            'last_print_format'  => $format,
        ]);
        return Response::success($bill, 'Bill marked as printed');
    }

    /** DELETE /api/billing/{id} — soft delete a bill. */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $bill = (new Bill())->find($id);
        if (!$bill) throw HttpException::notFound('Bill not found');

        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            (new Bill())->forceDelete($id);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'BILL_FORCE_DELETE',
                'entity' => 'bill', 'entity_id' => $id,
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
                'details' => 'bill_number=' . ($bill['bill_number'] ?? ''),
            ]);
            return Response::success(null, 'Bill permanently deleted');
        }

        (new Bill())->softDelete($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'BILL_DELETE',
            'entity' => 'bill', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Bill deleted');
    }

    /** PATCH /api/billing/{id}/restore — restore a soft-deleted bill. */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $bill = (new Bill())->find($id);
        if (!$bill) throw HttpException::notFound('Bill not found');
        if (empty($bill['deleted_at'])) throw HttpException::badRequest('Bill is not deleted');
        (new Bill())->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'BILL_RESTORE',
            'entity' => 'bill', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Bill restored');
    }

    /** GET /api/billing/{id}/pdf — generate PDF or HTML preview. */
    public function pdf(Request $request, array $params = []): Response
    {
        $bill = (new Bill())->findFull($params['id']);
        if (!$bill || $bill['deleted_at']) {
            throw HttpException::notFound('Bill not found');
        }
        $format = $request->query('format', 'a4');
        $key = $request->query('key');

        // If a temp PDF key is provided, return that cached PDF
        if ($format === 'cached' && $key) {
            $pdfBytes = TempPdfStore::get($key);
            if (!$pdfBytes) throw HttpException::notFound('PDF expired or not found');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="bill.pdf"');
            echo $pdfBytes;
            exit;
        }

        $shop = Settings::shopData();
        $contentSettings = Settings::billContentSettings($format === 'thermal' ? 'thermal' : 'a4');
        $loyalty = [
            'earned'   => (int)$bill['earned_points'],
            'redeemed' => (int)$bill['points_redeemed'],
        ];
        $data = BillTemplate::buildData($bill, $bill['items'], $bill['warranties'], $shop, $contentSettings, $loyalty);

        if ($format === 'thermal') {
            header('Content-Type: text/html; charset=utf-8');
            echo BillTemplate::generateThermalHtml($data);
            exit;
        }
        if ($format === 'a4-preview') {
            header('Content-Type: text/html; charset=utf-8');
            echo BillTemplate::generateA4PreviewHtml($data);
            exit;
        }
        // Default: A4 PDF
        $pdfBytes = BillTemplate::generateA4Pdf($data);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $bill['bill_number'] . '.pdf"');
        echo $pdfBytes;
        exit;
    }

    /** POST /api/billing/{id}/send — send bill via WhatsApp or email. */
    public function send(Request $request, array $params = []): Response
    {
        $bill = (new Bill())->findFull($params['id']);
        if (!$bill || $bill['deleted_at']) {
            throw HttpException::notFound('Bill not found');
        }
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_SEND_BILL, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $channel = $data['channel'];

        $shop = Settings::shopData();
        $contentSettings = Settings::billContentSettings('a4');
        $loyalty = ['earned' => (int)$bill['earned_points'], 'redeemed' => (int)$bill['points_redeemed']];
        $billData = BillTemplate::buildData($bill, $bill['items'], $bill['warranties'], $shop, $contentSettings, $loyalty);

        switch ($channel) {
            case 'whatsapp_text':
                if (!WhatsApp::isEnabled()) {
                    throw HttpException::forbidden('WhatsApp sending is disabled');
                }
                $phone = $bill['customer_phone'];
                if (!$phone) throw HttpException::badRequest('Customer phone required for WhatsApp');
                $msgTemplate = (string)Settings::get('whatsapp_default_message', 'Thank you for your purchase!');
                $msg = Email::renderTemplate($msgTemplate, ['shop_name' => $shop['name']]);
                if (!empty($data['custom_message'])) $msg = $data['custom_message'];
                $fullMsg = $msg . "\n\n" . BillTemplate::generateWhatsAppText($billData);
                $url = WhatsApp::buildUrl($phone, $fullMsg);
                return Response::success(['url' => $url], 'WhatsApp URL generated');

            case 'whatsapp_pdf':
                if (!WhatsApp::isEnabled()) {
                    throw HttpException::forbidden('WhatsApp sending is disabled');
                }
                $phone = $bill['customer_phone'];
                if (!$phone) throw HttpException::badRequest('Customer phone required for WhatsApp');
                $pdfBytes = BillTemplate::generateA4Pdf($billData);
                $key = TempPdfStore::put($pdfBytes, $bill['id']);
                // Build absolute URL
                $cfg = require __DIR__ . '/../../config/config.php';
                $baseUrl = rtrim($cfg['app_url'] ?? '', '/');
                $pdfUrl = $baseUrl . '/api/billing/' . urlencode($bill['id']) . '/pdf?format=cached&key=' . $key;
                $msgTemplate = (string)Settings::get('whatsapp_default_message', 'Thank you!');
                $msg = Email::renderTemplate($msgTemplate, ['shop_name' => $shop['name']]);
                if (!empty($data['custom_message'])) $msg = $data['custom_message'];
                $url = WhatsApp::buildPdfUrl($phone, $msg, $pdfUrl);
                return Response::success(['url' => $url, 'pdf_url' => $pdfUrl], 'WhatsApp PDF URL generated');

            case 'email_text':
                $to = $data['recipient_email'] ?? $bill['customer_email'];
                if (!$to) throw HttpException::badRequest('Recipient email required');
                $html = BillTemplate::generateA4PreviewHtml($billData);
                Email::send($to, 'Your receipt from ' . $shop['name'] . ' — ' . $bill['bill_number'], $html);
                Security::auditLog([
                    'user_id'    => $this->userId(),
                    'action'     => 'BILL_SEND',
                    'entity'     => 'bill',
                    'entity_id'  => $bill['id'],
                    'ip_address' => Security::getClientIp($request),
                    'user_agent' => Security::getUserAgent($request),
                    'details'    => "channel=email_text; recipient={$to}",
                ]);
                return Response::success(null, 'Email sent');

            case 'email_pdf':
                $to = $data['recipient_email'] ?? $bill['customer_email'];
                if (!$to) throw HttpException::badRequest('Recipient email required');
                $pdfBytes = BillTemplate::generateA4Pdf($billData);
                $html = BillTemplate::generateA4PreviewHtml($billData);
                Email::send($to, 'Your receipt from ' . $shop['name'] . ' — ' . $bill['bill_number'], $html, null, [
                    ['string' => $pdfBytes, 'name' => $bill['bill_number'] . '.pdf', 'type' => 'application/pdf'],
                ]);
                Security::auditLog([
                    'user_id'    => $this->userId(),
                    'action'     => 'BILL_SEND',
                    'entity'     => 'bill',
                    'entity_id'  => $bill['id'],
                    'ip_address' => Security::getClientIp($request),
                    'user_agent' => Security::getUserAgent($request),
                    'details'    => "channel=email_pdf; recipient={$to}",
                ]);
                return Response::success(null, 'Email with PDF sent');
        }

        throw HttpException::badRequest('Unknown channel');
    }
}
