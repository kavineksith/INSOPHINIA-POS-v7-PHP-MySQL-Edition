<?php
/**
 * SettingsController — settings + test email.
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Email;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Settings;
use Insophinia\POS\Lib\Validation;

class SettingsController extends Controller
{
    /** GET /api/settings */
    public function index(Request $request, array $params = []): Response
    {
        return Response::success(Settings::all());
    }

    /** PUT /api/settings */
    public function update(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $v = Validation::validate(Validation::SCHEMA_UPDATE_SETTINGS, $body);
        if (!$v['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($v['errors']));
        }
        $data = $v['data'];
        $updated = [];
        foreach ($data as $key => $value) {
            if (!Settings::isAllowedKey((string)$key)) continue;
            $type = is_bool($value) ? 'boolean' : (is_int($value) || is_float($value) ? 'integer' : 'string');
            Settings::set((string)$key, $value, $type);
            $updated[] = $key;
        }
        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'SETTINGS_UPDATE',
            'entity'     => 'settings',
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
            'details'    => 'keys=' . implode(',', $updated),
        ]);
        return Response::success(['updated' => $updated], 'Settings updated');
    }

    /** POST /api/settings/test-email */
    public function testEmail(Request $request, array $params = []): Response
    {
        $body = $request->json();
        $recipient = $body['recipient'] ?? '';
        if (!Validation::isValidEmail($recipient)) {
            throw HttpException::badRequest('Invalid recipient email');
        }
        $shop = Settings::shopData();
        $html = '<div style="font-family:sans-serif;font-size:14px;">'
              . '<h2>Test Email from ' . htmlspecialchars($shop['name']) . '</h2>'
              . '<p>This is a test email from INSOPHINIA POS v7. SMTP is configured correctly.</p>'
              . '<p>Sent at: ' . date('Y-m-d H:i:s') . '</p>'
              . '</div>';
        Email::send($recipient, 'INSOPHINIA POS — Test Email', $html);

        Security::auditLog([
            'user_id'    => $this->userId(),
            'action'     => 'TEST_EMAIL',
            'entity'     => 'settings',
            'ip_address' => Security::getClientIp($request),
            'user_agent' => Security::getUserAgent($request),
            'details'    => "recipient={$recipient}",
        ]);
        return Response::success(null, 'Test email sent');
    }
}
