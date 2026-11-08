<?php
/**
 * BillTemplate — Renders bills in 4 formats:
 *   1. WhatsApp text (plain text)
 *   2. Thermal HTML (80mm receipt)
 *   3. A4 PDF (via mPDF — full invoice with optional terms page)
 *   4. A4 HTML preview (browser-rendered)
 *
 * Mirrors src/lib/bill-template.ts.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class BillTemplate
{
    /**
     * Build the data structure expected by the rendering functions.
     * $bill — full bill row from bills table (with bill_items and warranties already loaded).
     * $shop — shop data array (name, address, phone, email, website).
     * $contentSettings — BillContentSettings flags.
     */
    public static function buildData(array $bill, array $billItems, array $warranties, array $shop, array $contentSettings, array $loyalty): array
    {
        return [
            'bill'             => $bill,
            'bill_items'       => $billItems,
            'warranties'       => $warranties,
            'shop'             => $shop,
            'content_settings' => $contentSettings,
            'loyalty'          => $loyalty,
        ];
    }

    // ---- 1. WhatsApp text ----
    public static function generateWhatsAppText(array $data): string
    {
        $shop = $data['shop'];
        $bill = $data['bill'];
        $items = $data['bill_items'];

        $lines = [];
        $lines[] = '*' . strtoupper($shop['name']) . '*';
        $lines[] = $shop['address'];
        $lines[] = 'Tel: ' . $shop['phone'];
        $lines[] = 'Email: ' . $shop['email'];
        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bill: ' . $bill['bill_number'];
        $lines[] = 'Date: ' . date('Y-m-d H:i', strtotime($bill['created_at']));
        $lines[] = 'Cashier: ' . $bill['staff_name'];
        if (!empty($bill['customer_name'])) {
            $lines[] = 'Customer: ' . $bill['customer_name'];
        }
        $lines[] = str_repeat('-', 32);

        foreach ($items as $item) {
            $qty = (float)$item['quantity'];
            $price = (float)$item['actual_price'];
            $sub = (float)$item['subtotal'];
            $name = $item['item_name'];
            if (!empty($item['variant_name'])) {
                $name .= ' (' . $item['variant_name'] . ')';
            }
            $lines[] = sprintf("%s x%.0f", $name, $qty);
            $lines[] = sprintf("  Rs.%.2f @ Rs.%.2f", $sub, $price);
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = sprintf('Subtotal:    Rs.%.2f', (float)$bill['subtotal']);
        if ((float)$bill['total_discount'] > 0) {
            $lines[] = sprintf('Discount:   -Rs.%.2f', (float)$bill['total_discount']);
        }
        if ((float)$bill['vat_amount'] > 0) {
            $lines[] = sprintf('VAT:         Rs.%.2f', (float)$bill['vat_amount']);
        }
        if ((float)$bill['sscl_amount'] > 0) {
            $lines[] = sprintf('SSCL:        Rs.%.2f', (float)$bill['sscl_amount']);
        }
        $lines[] = sprintf('*TOTAL:      Rs.%.2f*', (float)$bill['total_amount']);
        $lines[] = sprintf('Paid:        Rs.%.2f', (float)$bill['paid_amount']);
        if ((float)$bill['balance'] > 0) {
            $lines[] = sprintf('Balance:     Rs.%.2f', (float)$bill['balance']);
        }

        $loyalty = $data['loyalty'] ?? [];
        if (!empty($loyalty['earned']) || !empty($loyalty['redeemed'])) {
            $lines[] = str_repeat('-', 32);
            if (!empty($loyalty['earned'])) {
                $lines[] = 'Points earned: ' . $loyalty['earned'];
            }
            if (!empty($loyalty['redeemed'])) {
                $lines[] = 'Points redeemed: ' . $loyalty['redeemed'];
            }
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Thank you for shopping with us!';
        $lines[] = $shop['website'];

        return implode("\n", $lines);
    }

    // ---- 2. Thermal HTML (80mm) ----
    public static function generateThermalHtml(array $data): string
    {
        $shop = $data['shop'];
        $bill = $data['bill'];
        $items = $data['bill_items'];

        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        $html .= '<title>Receipt ' . $esc($bill['bill_number']) . '</title>';
        $html .= '<style>
            @page { size: 80mm auto; margin: 2mm; }
            body { width: 76mm; margin: 0 auto; font-family: "Geist Mono", "Courier New", monospace; font-size: 11px; color: #000; }
            .center { text-align: center; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
            hr { border: none; border-top: 1px dashed #000; margin: 4px 0; }
            table { width: 100%; border-collapse: collapse; }
            td { vertical-align: top; padding: 1px 0; }
            .item-row { display: flex; justify-content: space-between; }
            .totals { width: 100%; }
            .totals td { padding: 1px 4px; }
            @media print { body { width: 76mm; } }
        </style></head><body>';
        $html .= '<div class="center bold" style="font-size:14px">' . $esc(strtoupper($shop['name'])) . '</div>';
        $html .= '<div class="center">' . $esc($shop['address']) . '</div>';
        $html .= '<div class="center">Tel: ' . $esc($shop['phone']) . '</div>';
        $html .= '<div class="center">Email: ' . $esc($shop['email']) . '</div>';
        $html .= '<hr>';
        $html .= '<table><tr><td>Bill:</td><td class="right">' . $esc($bill['bill_number']) . '</td></tr>';
        $html .= '<tr><td>Date:</td><td class="right">' . date('Y-m-d H:i', strtotime($bill['created_at'])) . '</td></tr>';
        $html .= '<tr><td>Cashier:</td><td class="right">' . $esc($bill['staff_name']) . '</td></tr>';
        if (!empty($bill['customer_name'])) {
            $html .= '<tr><td>Customer:</td><td class="right">' . $esc($bill['customer_name']) . '</td></tr>';
        }
        $html .= '</table><hr>';

        $html .= '<table>';
        foreach ($items as $item) {
            $name = $item['item_name'];
            if (!empty($item['variant_name'])) $name .= ' (' . $item['variant_name'] . ')';
            $html .= '<tr><td>' . $esc($name) . '</td><td class="right">Rs.' . number_format((float)$item['subtotal'], 2) . '</td></tr>';
            $html .= '<tr><td colspan="2" style="font-size:10px;color:#444">  ' . $esc($item['quantity']) . ' x Rs.' . number_format((float)$item['actual_price'], 2) . '</td></tr>';
        }
        $html .= '</table><hr>';

        $html .= '<table class="totals">';
        $html .= '<tr><td>Subtotal</td><td class="right">Rs.' . number_format((float)$bill['subtotal'], 2) . '</td></tr>';
        if ((float)$bill['total_discount'] > 0) {
            $html .= '<tr><td>Discount</td><td class="right">-Rs.' . number_format((float)$bill['total_discount'], 2) . '</td></tr>';
        }
        if ((float)$bill['vat_amount'] > 0) {
            $html .= '<tr><td>VAT</td><td class="right">Rs.' . number_format((float)$bill['vat_amount'], 2) . '</td></tr>';
        }
        if ((float)$bill['sscl_amount'] > 0) {
            $html .= '<tr><td>SSCL</td><td class="right">Rs.' . number_format((float)$bill['sscl_amount'], 2) . '</td></tr>';
        }
        $html .= '<tr class="bold"><td>TOTAL</td><td class="right">Rs.' . number_format((float)$bill['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><td>Paid</td><td class="right">Rs.' . number_format((float)$bill['paid_amount'], 2) . '</td></tr>';
        if ((float)$bill['balance'] > 0) {
            $html .= '<tr><td>Balance</td><td class="right">Rs.' . number_format((float)$bill['balance'], 2) . '</td></tr>';
        }
        $html .= '</table>';

        $loyalty = $data['loyalty'] ?? [];
        if (!empty($loyalty['earned']) || !empty($loyalty['redeemed'])) {
            $html .= '<hr>';
            if (!empty($loyalty['earned'])) $html .= '<div>Points earned: ' . (int)$loyalty['earned'] . '</div>';
            if (!empty($loyalty['redeemed'])) $html .= '<div>Points redeemed: ' . (int)$loyalty['redeemed'] . '</div>';
        }

        $html .= '<hr><div class="center">Thank you for shopping with us!</div>';
        $html .= '<div class="center">' . $esc($shop['website']) . '</div>';
        $html .= '<script>window.onload=function(){window.print();}</script>';
        $html .= '</body></html>';
        return $html;
    }

    // ---- 3. A4 PDF (mPDF) ----
    public static function generateA4Pdf(array $data): string
    {
        $shop = $data['shop'];
        $bill = $data['bill'];
        $items = $data['bill_items'];
        $content = $data['content_settings'];
        $warranties = $data['warranties'];
        $loyalty = $data['loyalty'] ?? [];
        $terms = $bill['terms_snapshot'] ?? null;

        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Configure mPDF
        $mpdfConfig = [
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 12,
            'margin_right'  => 12,
            'margin_top'    => 14,
            'margin_bottom' => 14,
            'tempDir'       => sys_get_temp_dir(),
            'fontDir'       => [__DIR__ . '/../../public/assets/vendor/google-fonts'],
            'fontdata'      => [
                'geist' => [
                    'R'  => 'GeistSans-Variable.woff2',
                    'B'  => 'GeistSans-Variable.woff2',
                    'I'  => 'GeistSans-Variable.woff2',
                    'BI' => 'GeistSans-Variable.woff2',
                ],
            ],
            'default_font'  => 'geist',
        ];

        $mpdf = new Mpdf($mpdfConfig);

        $html = '<style>
            body { font-family: geist, sans-serif; font-size: 11pt; color: #18181b; }
            .header-band { background: #6366f1; color: #fff; padding: 18pt 24pt; display: flex; justify-content: space-between; align-items: center; }
            .header-band h1 { margin: 0; font-size: 22pt; }
            .header-band .meta { text-align: right; font-size: 10pt; }
            .invoice-box { border: 1px solid #e4e4e7; padding: 14pt 18pt; margin: 14pt 0; background: #fafafa; }
            .invoice-box .label { color: #71717a; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em; }
            .invoice-box .value { font-size: 13pt; font-weight: bold; }
            table.items { width: 100%; border-collapse: collapse; margin: 14pt 0; }
            table.items th { background: #18181b; color: #fff; padding: 6pt 8pt; font-size: 9pt; text-align: left; }
            table.items td { padding: 6pt 8pt; border-bottom: 1px solid #e4e4e7; font-size: 10pt; vertical-align: top; }
            table.items tr.subrow td { font-size: 9pt; color: #71717a; background: #fafafa; }
            .totals-box { float: right; width: 280pt; margin-top: 14pt; }
            .totals-box table { width: 100%; border-collapse: collapse; }
            .totals-box td { padding: 4pt 8pt; font-size: 10pt; }
            .totals-box tr.total td { font-size: 14pt; font-weight: bold; border-top: 2px solid #18181b; padding-top: 8pt; }
            .warranty-box { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 10pt 14pt; margin: 14pt 0; }
            .warranty-box h4 { margin: 0 0 6pt 0; color: #b45309; }
            .warranty-box ul { margin: 0; padding-left: 18pt; font-size: 9pt; }
            .loyalty-box { background: #f0f9ff; border-left: 3px solid #0284c7; padding: 8pt 14pt; margin: 10pt 0; font-size: 10pt; }
            .terms-page { page-break-before: always; padding-top: 20pt; }
            .terms-page h2 { color: #18181b; border-bottom: 1px solid #e4e4e7; padding-bottom: 6pt; }
        </style>';

        // Header band
        $html .= '<div class="header-band">';
        $html .= '<h1>' . $esc(strtoupper($shop['name'])) . '</h1>';
        $html .= '<div class="meta">';
        $html .= '<div>' . $esc($shop['address']) . '</div>';
        $html .= '<div>Tel: ' . $esc($shop['phone']) . '</div>';
        $html .= '<div>' . $esc($shop['email']) . '</div>';
        $html .= '<div>' . $esc($shop['website']) . '</div>';
        $html .= '</div></div>';

        // Invoice box
        $html .= '<div class="invoice-box" style="display:flex;justify-content:space-between;">';
        $html .= '<div><div class="label">Invoice No.</div><div class="value">' . $esc($bill['bill_number']) . '</div></div>';
        $html .= '<div><div class="label">Date</div><div class="value">' . date('Y-m-d H:i', strtotime($bill['created_at'])) . '</div></div>';
        $html .= '<div><div class="label">Status</div><div class="value" style="text-transform:uppercase">' . $esc($bill['status']) . '</div></div>';
        $html .= '<div><div class="label">Cashier</div><div class="value">' . $esc($bill['staff_name']) . '</div></div>';
        $html .= '</div>';

        // Customer info
        $html .= '<div style="margin-bottom:10pt;"><strong>Bill To:</strong> ';
        $html .= $esc($bill['customer_name']);
        if (!empty($bill['customer_phone'])) $html .= ' &middot; ' . $esc($bill['customer_phone']);
        if (!empty($bill['customer_email'])) $html .= ' &middot; ' . $esc($bill['customer_email']);
        if (!empty($bill['customer_address'])) $html .= '<br>' . $esc($bill['customer_address']);
        $html .= '</div>';

        // Items table
        $html .= '<table class="items"><thead><tr>';
        $html .= '<th style="width:50%">Item</th><th>Qty</th><th>Unit Price</th><th>Disc</th><th style="text-align:right">Subtotal</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($items as $item) {
            $name = $item['item_name'];
            if (!empty($item['variant_name'])) $name .= ' — ' . $item['variant_name'];
            $html .= '<tr>';
            $html .= '<td><strong>' . $esc($name) . '</strong>';
            if ($content['show_variants'] && !empty($item['variant_specs'])) {
                $specs = is_string($item['variant_specs']) ? Util::jsonDecode($item['variant_specs'], []) : $item['variant_specs'];
                if (is_array($specs) && !empty($specs)) {
                    $html .= '<br><span style="font-size:9pt;color:#71717a;">';
                    $parts = [];
                    foreach ($specs as $k => $v) $parts[] = $esc($k) . ': ' . $esc($v);
                    $html .= implode(' · ', $parts);
                    $html .= '</span>';
                }
            }
            if ($content['show_subitems'] && !empty($item['sub_item_name'])) {
                $html .= '<br><span style="font-size:9pt;color:#71717a;">Sub-item: ' . $esc($item['sub_item_name']);
                if (!empty($item['revisions_purchased'])) {
                    $html .= ' (+' . (int)$item['revisions_purchased'] . ' revisions @ Rs.' . number_format((float)$item['revision_price'], 2) . ')';
                }
                $html .= '</span>';
            }
            if ($content['show_revisions'] && (float)($item['revisions_subtotal'] ?? 0) > 0) {
                $html .= '<br><span style="font-size:9pt;color:#71717a;">Revisions subtotal: Rs.' . number_format((float)$item['revisions_subtotal'], 2) . '</span>';
            }
            if (!empty($item['line_notes'])) {
                $html .= '<br><span style="font-size:9pt;color:#71717a;font-style:italic;">' . $esc($item['line_notes']) . '</span>';
            }
            $html .= '</td>';
            $html .= '<td>' . $esc($item['quantity']) . ' ' . $esc($item['unit']) . '</td>';
            $html .= '<td>Rs.' . number_format((float)$item['actual_price'], 2) . '</td>';
            $html .= '<td>' . ((float)$item['discount'] > 0 ? 'Rs.' . number_format((float)$item['discount'], 2) : '-') . '</td>';
            $html .= '<td style="text-align:right">Rs.' . number_format((float)$item['subtotal'], 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Totals box (right side)
        $html .= '<div class="totals-box"><table>';
        $html .= '<tr><td>Subtotal</td><td style="text-align:right">Rs.' . number_format((float)$bill['subtotal'], 2) . '</td></tr>';
        if ((float)$bill['total_discount'] > 0) {
            $html .= '<tr><td>Discount</td><td style="text-align:right">-Rs.' . number_format((float)$bill['total_discount'], 2) . '</td></tr>';
        }
        if ((float)$bill['vat_amount'] > 0) {
            $html .= '<tr><td>VAT (' . (int)(Settings::get('vat_rate', 0)) . '%)</td><td style="text-align:right">Rs.' . number_format((float)$bill['vat_amount'], 2) . '</td></tr>';
        }
        if ((float)$bill['sscl_amount'] > 0) {
            $html .= '<tr><td>SSCL (' . (float)Settings::get('sscl_rate', 0) . '%)</td><td style="text-align:right">Rs.' . number_format((float)$bill['sscl_amount'], 2) . '</td></tr>';
        }
        $html .= '<tr class="total"><td>Total</td><td style="text-align:right">Rs.' . number_format((float)$bill['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><td>Paid</td><td style="text-align:right">Rs.' . number_format((float)$bill['paid_amount'], 2) . '</td></tr>';
        if ((float)$bill['balance'] > 0) {
            $html .= '<tr><td>Balance</td><td style="text-align:right">Rs.' . number_format((float)$bill['balance'], 2) . '</td></tr>';
        }
        $html .= '</table></div>';

        // Loyalty box
        if (!empty($loyalty['earned']) || !empty($loyalty['redeemed'])) {
            $html .= '<div style="clear:both;"></div>';
            $html .= '<div class="loyalty-box">';
            if (!empty($loyalty['earned'])) {
                $html .= '<strong>Points earned:</strong> ' . (int)$loyalty['earned'] . ' &nbsp; ';
            }
            if (!empty($loyalty['redeemed'])) {
                $html .= '<strong>Points redeemed:</strong> ' . (int)$loyalty['redeemed'] . ' (value Rs.' . number_format((float)($bill['points_value'] ?? 0), 2) . ') &nbsp; ';
            }
            if (!empty($bill['points_authorized_by'])) {
                $html .= '<strong>Authorized by:</strong> ' . $esc($bill['points_authorized_by']);
            }
            $html .= '</div>';
        }

        // Warranty summary
        if ($content['show_warranty'] && !empty($warranties)) {
            $html .= '<div class="warranty-box">';
            $html .= '<h4>Warranty Summary</h4><ul>';
            foreach ($warranties as $w) {
                $html .= '<li><strong>' . $esc($w['warranty_number']) . '</strong> — ' . $esc($w['item_name']);
                if (!empty($w['variant_sku'])) $html .= ' [' . $esc($w['variant_sku']) . ']';
                $html .= ' &middot; Expires: ' . date('Y-m-d', strtotime($w['expires_at']));
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }

        // Remarks
        if (!empty($bill['remarks'])) {
            $html .= '<div style="margin-top:14pt;font-size:9pt;"><strong>Remarks:</strong> ' . $esc($bill['remarks']) . '</div>';
        }

        // Terms page
        if ($content['show_terms'] && $terms) {
            $termsData = is_string($terms) ? Util::jsonDecode($terms, []) : $terms;
            $termsContent = $termsData['content'] ?? '';
            $termsFormat = $termsData['format'] ?? 'html';
            $html .= '<div class="terms-page">';
            $html .= '<h2>Terms &amp; Conditions</h2>';
            if ($termsFormat === 'markdown') {
                $html .= '<pre style="font-family: sans-serif; white-space: pre-wrap; font-size: 10pt;">' . $esc($termsContent) . '</pre>';
            } else {
                $html .= $termsContent; // already-sanitized HTML
            }
            $html .= '</div>';
        }

        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S'); // return as string
    }

    // ---- 4. A4 HTML preview ----
    public static function generateA4PreviewHtml(array $data): string
    {
        $shop = $data['shop'];
        $bill = $data['bill'];
        $items = $data['bill_items'];
        $content = $data['content_settings'];
        $warranties = $data['warranties'];
        $loyalty = $data['loyalty'] ?? [];
        $terms = $bill['terms_snapshot'] ?? null;
        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        $html .= '<title>Invoice ' . $esc($bill['bill_number']) . '</title>';
        $html .= '<style>
            @page { size: A4; margin: 14mm; }
            body { font-family: "Geist Sans", sans-serif; color: #18181b; margin: 0; padding: 20px; }
            .header-band { background: #6366f1; color: #fff; padding: 18pt 24pt; display: flex; justify-content: space-between; align-items: center; }
            .header-band h1 { margin: 0; font-size: 22pt; }
            .invoice-box { border: 1px solid #e4e4e7; padding: 14pt 18pt; margin: 14pt 0; background: #fafafa; display: flex; justify-content: space-between; gap: 14pt; }
            .label { color: #71717a; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em; }
            .value { font-size: 13pt; font-weight: bold; }
            table.items { width: 100%; border-collapse: collapse; margin: 14pt 0; }
            table.items th { background: #18181b; color: #fff; padding: 6pt 8pt; font-size: 9pt; text-align: left; }
            table.items td { padding: 6pt 8pt; border-bottom: 1px solid #e4e4e7; font-size: 10pt; vertical-align: top; }
            .totals-box { float: right; width: 320pt; margin-top: 14pt; }
            .totals-box table { width: 100%; border-collapse: collapse; }
            .totals-box td { padding: 4pt 8pt; font-size: 10pt; }
            .totals-box tr.total td { font-size: 14pt; font-weight: bold; border-top: 2px solid #18181b; padding-top: 8pt; }
            .warranty-box { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 10pt 14pt; margin: 14pt 0; }
            .loyalty-box { background: #f0f9ff; border-left: 3px solid #0284c7; padding: 8pt 14pt; margin: 10pt 0; font-size: 10pt; }
        </style></head><body>';

        $html .= '<div class="header-band">';
        $html .= '<h1>' . $esc(strtoupper($shop['name'])) . '</h1>';
        $html .= '<div style="text-align:right;font-size:10pt;">';
        $html .= '<div>' . $esc($shop['address']) . '</div>';
        $html .= '<div>Tel: ' . $esc($shop['phone']) . '</div>';
        $html .= '<div>' . $esc($shop['email']) . '</div>';
        $html .= '<div>' . $esc($shop['website']) . '</div>';
        $html .= '</div></div>';

        $html .= '<div class="invoice-box">';
        $html .= '<div><div class="label">Invoice No.</div><div class="value">' . $esc($bill['bill_number']) . '</div></div>';
        $html .= '<div><div class="label">Date</div><div class="value">' . date('Y-m-d H:i', strtotime($bill['created_at'])) . '</div></div>';
        $html .= '<div><div class="label">Status</div><div class="value" style="text-transform:uppercase">' . $esc($bill['status']) . '</div></div>';
        $html .= '<div><div class="label">Cashier</div><div class="value">' . $esc($bill['staff_name']) . '</div></div>';
        $html .= '</div>';

        $html .= '<div style="margin-bottom:10pt;"><strong>Bill To:</strong> ';
        $html .= $esc($bill['customer_name']);
        if (!empty($bill['customer_phone'])) $html .= ' &middot; ' . $esc($bill['customer_phone']);
        if (!empty($bill['customer_email'])) $html .= ' &middot; ' . $esc($bill['customer_email']);
        $html .= '</div>';

        $html .= '<table class="items"><thead><tr>';
        $html .= '<th style="width:50%">Item</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($items as $item) {
            $name = $item['item_name'];
            if (!empty($item['variant_name'])) $name .= ' — ' . $item['variant_name'];
            $html .= '<tr>';
            $html .= '<td><strong>' . $esc($name) . '</strong></td>';
            $html .= '<td>' . $esc($item['quantity']) . ' ' . $esc($item['unit']) . '</td>';
            $html .= '<td>Rs.' . number_format((float)$item['actual_price'], 2) . '</td>';
            $html .= '<td>Rs.' . number_format((float)$item['subtotal'], 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<div class="totals-box"><table>';
        $html .= '<tr><td>Subtotal</td><td style="text-align:right">Rs.' . number_format((float)$bill['subtotal'], 2) . '</td></tr>';
        if ((float)$bill['total_discount'] > 0) {
            $html .= '<tr><td>Discount</td><td style="text-align:right">-Rs.' . number_format((float)$bill['total_discount'], 2) . '</td></tr>';
        }
        if ((float)$bill['vat_amount'] > 0) {
            $html .= '<tr><td>VAT</td><td style="text-align:right">Rs.' . number_format((float)$bill['vat_amount'], 2) . '</td></tr>';
        }
        if ((float)$bill['sscl_amount'] > 0) {
            $html .= '<tr><td>SSCL</td><td style="text-align:right">Rs.' . number_format((float)$bill['sscl_amount'], 2) . '</td></tr>';
        }
        $html .= '<tr class="total"><td>Total</td><td style="text-align:right">Rs.' . number_format((float)$bill['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><td>Paid</td><td style="text-align:right">Rs.' . number_format((float)$bill['paid_amount'], 2) . '</td></tr>';
        if ((float)$bill['balance'] > 0) {
            $html .= '<tr><td>Balance</td><td style="text-align:right">Rs.' . number_format((float)$bill['balance'], 2) . '</td></tr>';
        }
        $html .= '</table></div>';

        if (!empty($loyalty['earned']) || !empty($loyalty['redeemed'])) {
            $html .= '<div style="clear:both;"></div><div class="loyalty-box">';
            if (!empty($loyalty['earned'])) $html .= '<strong>Points earned:</strong> ' . (int)$loyalty['earned'] . ' &nbsp; ';
            if (!empty($loyalty['redeemed'])) $html .= '<strong>Points redeemed:</strong> ' . (int)$loyalty['redeemed'] . ' &nbsp; ';
            $html .= '</div>';
        }

        if ($content['show_warranty'] && !empty($warranties)) {
            $html .= '<div class="warranty-box"><strong>Warranty:</strong> ';
            $html .= count($warranties) . ' item(s) covered — see PDF for details.</div>';
        }

        if ($content['show_terms'] && $terms) {
            $termsData = is_string($terms) ? Util::jsonDecode($terms, []) : $terms;
            $html .= '<div style="clear:both;margin-top:14pt;font-size:9pt;color:#71717a;">Terms &amp; Conditions attached in PDF.</div>';
        }

        $html .= '</body></html>';
        return $html;
    }
}
