<?php
/**
 * TermsController — terms templates CRUD.
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
use Insophinia\POS\Models\TermsTemplate;

class TermsController extends Controller
{
    /** GET /api/terms */
    public function index(Request $request, array $params = []): Response
    {
        $p = $this->pagination();
        $trashed = \Insophinia\POS\Models\BaseModel::parseTrashedParam($request->query('trashed'));
        $result = (new TermsTemplate())->listActive($p['page'], $p['limit'], $p['search'], $trashed);
        foreach ($result['items'] as &$row) {
            $row['applies_to'] = Util::jsonDecode($row['applies_to'], ['a4']);
        }
        return Response::paginated($result['items'], $p['page'], $p['limit'], $result['total']);
    }

    /** POST /api/terms */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_CREATE_TERMS_TEMPLATE, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $appliesTo = $data['applies_to'] ?? ['a4'];
        if (is_array($appliesTo)) $appliesTo = Util::jsonEncode($appliesTo);
        $id = Util::uuid();
        (new TermsTemplate())->insert([
            'id'         => $id,
            'name'       => $data['name'],
            'format'     => $data['format'] ?? 'html',
            'content'    => $data['content'],
            'applies_to' => $appliesTo,
            'is_active'  => !empty($data['is_active']) ? 1 : 0,
            'version'    => 1,
        ]);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'TERMS_CREATE',
            'entity'     => 'terms_template',
            'entity_id'  => $id,
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new TermsTemplate())->find($id), 'Terms template created', 201);
    }

    /** GET /api/terms/{id} */
    public function show(Request $request, array $params = []): Response
    {
        $t = (new TermsTemplate())->find($params['id']);
        if (!$t || $t['deleted_at']) throw HttpException::notFound('Terms template not found');
        $t['applies_to'] = Util::jsonDecode($t['applies_to'], ['a4']);
        return Response::success($t);
    }

    /** PUT /api/terms/{id} */
    public function update(Request $request, array $params = []): Response
    {
        $t = (new TermsTemplate())->find($params['id']);
        if (!$t || $t['deleted_at']) throw HttpException::notFound('Terms template not found');
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_TERMS_TEMPLATE, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        // Increment version on content change
        if (!empty($data['content']) && $data['content'] !== $t['content']) {
            $data['version'] = (int)$t['version'] + 1;
        }
        if (!empty($data['applies_to']) && is_array($data['applies_to'])) {
            $data['applies_to'] = Util::jsonEncode($data['applies_to']);
        }
        (new TermsTemplate())->update($t['id'], $data);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'TERMS_UPDATE',
            'entity'     => 'terms_template',
            'entity_id'  => $t['id'],
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new TermsTemplate())->find($t['id']), 'Terms template updated');
    }

    /** DELETE /api/terms/{id} */
    public function destroy(Request $request, array $params = []): Response
    {
        $t = (new TermsTemplate())->find($params['id']);
        if (!$t) throw HttpException::notFound('Terms template not found');

        $force = $request->query('force') === 'true' || $request->query('force') === '1';
        if ($force) {
            if (!\Insophinia\POS\Lib\Permissions::canForceDelete($this->userRole())) {
                throw HttpException::forbidden('Only master_admin can permanently delete records');
            }
            (new TermsTemplate())->forceDelete($t['id']);
            Security::auditLog([
                'user_id' => $this->userId(), 'action' => 'TERMS_FORCE_DELETE',
                'entity' => 'terms_template', 'entity_id' => $t['id'],
                'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
            ]);
            return Response::success(null, 'Terms template permanently deleted');
        }

        (new TermsTemplate())->softDelete($t['id']);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'TERMS_DELETE',
            'entity' => 'terms_template', 'entity_id' => $t['id'],
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success(null, 'Terms template deleted');
    }

    /** PATCH /api/terms/{id}/restore */
    public function restore(Request $request, array $params = []): Response
    {
        $id = $params['id'];
        $t = (new TermsTemplate())->find($id);
        if (!$t) throw HttpException::notFound('Terms template not found');
        if (empty($t['deleted_at'])) throw HttpException::badRequest('Terms template is not deleted');
        (new TermsTemplate())->restore($id);
        Security::auditLog([
            'user_id' => $this->userId(), 'action' => 'TERMS_RESTORE',
            'entity' => 'terms_template', 'entity_id' => $id,
            'ip_address' => Security::getClientIp($request), 'user_agent' => Security::getUserAgent($request),
        ]);
        return Response::success((new TermsTemplate())->find($id), 'Terms template restored');
    }
}
