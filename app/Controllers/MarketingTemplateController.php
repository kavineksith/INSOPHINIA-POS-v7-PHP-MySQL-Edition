<?php
/**
 * MarketingTemplateController — email templates CRUD.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Models\EmailTemplate;

class MarketingTemplateController extends Controller
{
    /** GET /api/marketing/templates */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $type = $request->query('type');
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));
        $result = (new EmailTemplate())->listWithStats($p['page'], $p['limit'], $p['search'], $type, $trashed);
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/marketing/templates */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_EMAIL_TEMPLATE, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $id = Util::uuid();
        (new EmailTemplate())->insert([
            'id'                => $id,
            'name'              => $data['name'],
            'type'              => $data['type'] ?? 'marketing',
            'subject'           => $data['subject'],
            'html_body'         => $data['html_body'],
            'text_body'         => $data['text_body'] ?? null,
            'default_variables' => $data['default_variables'] ?? null,
            'is_active'         => !empty($data['is_active']) ? 1 : 0,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'TEMPLATE_CREATE',
            'entity'     => 'email_template',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new EmailTemplate())->find($id), 'Template created', 201);
    }

    /** GET /api/marketing/templates/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $t = (new EmailTemplate())->find($params['id']);
        if (!$t || $t['deleted_at']) throw HttpException::notFound('Template not found');
        return Response::success($t);
    }

    /** PUT /api/marketing/templates/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $t = (new EmailTemplate())->find($params['id']);
        if (!$t || $t['deleted_at']) throw HttpException::notFound('Template not found');
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_EMAIL_TEMPLATE, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        (new EmailTemplate())->update($t['id'], $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'TEMPLATE_UPDATE',
            'entity'     => 'email_template',
            'entity_id'  => $t['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new EmailTemplate())->find($t['id']), 'Template updated');
    }

    /** DELETE /api/marketing/templates/{id} */
    public function destroy(Request $request, array $params = []): Response
    {
        $t = (new EmailTemplate())->find($params['id']);
        if (!$t) throw HttpException::notFound('Template not found');

        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            (new EmailTemplate())->forceDelete($t['id']);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'TEMPLATE_FORCE_DELETE',
                'entity' => 'email_template', 'entity_id' => $t['id'],
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
            ]);
            return Response::success(null, 'Template permanently deleted');
        }

        (new EmailTemplate())->softDelete($t['id']);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'TEMPLATE_DELETE',
            'entity' => 'email_template', 'entity_id' => $t['id'],
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Template deleted');
    }

    /** PATCH /api/marketing/templates/{id}/restore */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $t = (new EmailTemplate())->find($id);
        if (!$t) throw HttpException::notFound('Template not found');
        if (empty($t['deleted_at'])) throw HttpException::badRequest('Template is not deleted');
        (new EmailTemplate())->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'TEMPLATE_RESTORE',
            'entity' => 'email_template', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new EmailTemplate())->find($id), 'Template restored');
    }
}
