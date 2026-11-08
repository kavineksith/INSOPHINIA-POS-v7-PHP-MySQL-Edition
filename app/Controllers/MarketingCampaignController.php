<?php
/**
 * MarketingCampaignController — campaigns CRUD + preview + send.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Email;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Settings;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Models\EmailTemplate;
use Insophinia\POS\Models\MarketingCampaign;
use Insophinia\POS\Models\MarketingRecipient;
use Insophinia\POS\Models\Customer;

class MarketingCampaignController extends Controller
{
    /** GET /api/marketing/campaigns */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $result = (new MarketingCampaign())->listWithRelations($p['page'], $p['limit'], $p['search']);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/marketing/campaigns */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_CAMPAIGN, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];

        $template = (new EmailTemplate())->find($data['template_id']);
        if (!$template || !$template['is_active'] || $template['deleted_at']) {
            throw HttpException::badRequest('Template is not active or does not exist');
        }

        $totalRecipients = 0;
        if (($data['audience_type'] ?? 'single') === 'single') {
            $totalRecipients = 1;
        } else {
            // Count matching customers (opted in)
            $filter = Util::jsonDecode($data['audience_filter'] ?? '{}', []);
            $totalRecipients = $this->countBulkRecipients($filter);
        }

        $id = Util::uuid();
        (new MarketingCampaign())->insert([
            'id'                => $id,
            'name'              => $data['name'],
            'template_id'       => $data['template_id'],
            'audience_type'     => $data['audience_type'] ?? 'single',
            'audience_filter'   => $data['audience_filter'] ?? null,
            'recipient_email'   => $data['recipient_email'] ?? null,
            'recipient_name'    => $data['recipient_name'] ?? null,
            'subject_override'  => $data['subject_override'] ?? null,
            'custom_variables'  => $data['custom_variables'] ?? null,
            'scheduled_at'      => $data['scheduled_at'] ?? null,
            'status'            => 'draft',
            'total_recipients'  => $totalRecipients,
            'created_by'        => $this->userId(),
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CAMPAIGN_CREATE',
            'entity'     => 'marketing_campaign',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new MarketingCampaign())->findFull($id), 'Campaign created', 201);
    }

    /** GET /api/marketing/campaigns/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $c = (new MarketingCampaign())->findFull($params['id']);
        if (!$c) throw HttpException::notFound('Campaign not found');
        return Response::success($c);
    }

    /** DELETE /api/marketing/campaigns/{id} */
    public function destroy(Request $request, array $params = []): Response
    {
        $c = (new MarketingCampaign())->find($params['id']);
        if (!$c) throw HttpException::notFound('Campaign not found');
        if ($c['status'] === 'sending') {
            throw HttpException::conflict('Cannot delete a campaign that is currently sending');
        }
        Database::getInstance()->delete('marketing_campaigns', 'id = ?', [$c['id']]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CAMPAIGN_DELETE',
            'entity'     => 'marketing_campaign',
            'entity_id'  => $c['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Campaign deleted');
    }

    /** GET /api/marketing/campaigns/{id}/preview */
    public function preview(Request $request, array $params = []): Response
    {
        $c = (new MarketingCampaign())->findFull($params['id']);
        if (!$c) throw HttpException::notFound('Campaign not found');
        $subject = $c['subject_override'] ?: $c['template_subject'];
        $vars = array_merge(
            Util::jsonDecode($c['template_default_variables'] ?? '{}', []),
            Util::jsonDecode($c['custom_variables'] ?? '{}', []),
            [
                'customer_name' => $c['recipient_name'] ?: 'Valued Customer',
                'shop_name'     => Settings::get('shop_name', 'INSOPHINIA'),
            ]
        );
        $html = Email::renderTemplate($c['template_html'], $vars);
        return Response::success(['subject' => $subject, 'html' => $html]);
    }

    /** POST /api/marketing/campaigns/{id}/send */
    public function send(Request $request, array $params = []): Response
    {
        // Rate limit (5/hr)
        Security::enforceRateLimit(Security::getClientIp($request), 'marketing_send');

        $c = (new MarketingCampaign())->findFull($params['id']);
        if (!$c) throw HttpException::notFound('Campaign not found');
        if (!in_array($c['status'], ['draft', 'scheduled'], true)) {
            throw HttpException::badRequest('Campaign cannot be sent (status: ' . $c['status'] . ')');
        }

        // Mark as sending
        (new MarketingCampaign())->update($c['id'], [
            'status'      => 'sending',
            'started_at'  => date('Y-m-d H:i:s'),
        ]);

        $subject = $c['subject_override'] ?: $c['template_subject'];
        $baseVars = array_merge(
            Util::jsonDecode($c['template_default_variables'] ?? '{}', []),
            Util::jsonDecode($c['custom_variables'] ?? '{}', []),
            ['shop_name' => Settings::get('shop_name', 'INSOPHINIA')]
        );

        $recipients = [];
        if ($c['audience_type'] === 'single') {
            $recipients[] = ['email' => $c['recipient_email'], 'name' => $c['recipient_name'] ?? '', 'customer_id' => null];
        } else {
            $filter = Util::jsonDecode($c['audience_filter'] ?? '{}', []);
            $recipients = $this->fetchBulkRecipients($filter, 500); // 500-recipient cap
        }

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $r) {
            $recipientId = Util::uuid();
            try {
                $vars = array_merge($baseVars, ['customer_name' => $r['name'] ?: 'Valued Customer']);
                $html = Email::renderTemplate($c['template_html'], $vars);
                Email::send($r['email'], $subject, $html);
                (new MarketingRecipient())->insert([
                    'id'           => $recipientId,
                    'campaign_id'  => $c['id'],
                    'customer_id'  => $r['customer_id'] ?? null,
                    'email'        => $r['email'],
                    'name'         => $r['name'],
                    'status'       => 'sent',
                    'sent_at'      => date('Y-m-d H:i:s'),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                (new MarketingRecipient())->insert([
                    'id'            => $recipientId,
                    'campaign_id'   => $c['id'],
                    'customer_id'   => $r['customer_id'] ?? null,
                    'email'         => $r['email'],
                    'name'          => $r['name'],
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $finalStatus = $sent === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'sent');
        (new MarketingCampaign())->update($c['id'], [
            'status'        => $finalStatus,
            'sent_count'    => $sent,
            'failed_count'  => $failed,
            'completed_at'  => date('Y-m-d H:i:s'),
        ]);

        Security::logSecurityEvent([
            'event_type'  => Security::EVENT_SUSPICIOUS_ACTIVITY,
            'ip_address'  => Security::getClientIp($request),
            'user_agent'  => Security::getUserAgent($request),
            'user_id'     => $this->userId(),
            'details'     => "Campaign sent: id={$c['id']}; sent={$sent}; failed={$failed}",
            'severity'    => 'medium',
        ]);
        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'CAMPAIGN_SEND',
            'entity'     => 'marketing_campaign',
            'entity_id'  => $c['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);

        return Response::success([
            'sent'   => $sent,
            'failed' => $failed,
            'status' => $finalStatus,
        ], 'Campaign send completed');
    }

    private function countBulkRecipients(array $filter): int
    {
        [$sql, $params] = $this->bulkQuery($filter, 'COUNT(*)');
        return (int)Database::getInstance()->fetchColumn($sql, $params);
    }

    private function fetchBulkRecipients(array $filter, int $limit): array
    {
        [$sql, $params] = $this->bulkQuery($filter, 'id, name, email');
        $sql .= " LIMIT {$limit}";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        return array_map(fn($r) => ['customer_id' => $r['id'], 'email' => $r['email'], 'name' => $r['name']], $rows);
    }

    private function bulkQuery(array $filter, string $select): array
    {
        $where = "deleted_at IS NULL AND marketing_opt_in = 1 AND email IS NOT NULL AND email != ''";
        $params = [];
        if (!empty($filter['has_email'])) $where .= " AND email IS NOT NULL";
        if (!empty($filter['opted_in'])) $where .= " AND marketing_opt_in = 1";
        if (isset($filter['min_loyalty_points'])) {
            $where .= " AND loyalty_points >= ?";
            $params[] = (int)$filter['min_loyalty_points'];
        }
        if (isset($filter['max_loyalty_points'])) {
            $where .= " AND loyalty_points <= ?";
            $params[] = (int)$filter['max_loyalty_points'];
        }
        return ["SELECT {$select} FROM customers WHERE {$where}", $params];
    }
}
