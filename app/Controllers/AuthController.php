<?php
/**
 * AuthController — login, logout, me
 */

declare(strict_types=1);

namespace Insophinia\POS\Controllers;

use Insophinia\POS\Core\Controller;
use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\HttpException;
use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Util;
use Insophinia\POS\Lib\Validation;
use Insophinia\POS\Models\User;
use Insophinia\POS\Models\LoginSession;

class AuthController extends Controller
{
    /** POST /api/auth/login */
    public function login(Request $request, array $params = []): Response
    {
        $clientIp = Security::getClientIp($request);
        $ua = Security::getUserAgent($request);

        $body = $request->json();
        $result = Validation::validate(Validation::SCHEMA_LOGIN, $body);
        if (!$result['success']) {
            throw HttpException::badRequest('Validation failed', Validation::formatErrors($result['errors']));
        }
        $data = $result['data'];

        $userModel = new User();
        $user = $userModel->findByUsername($data['username']);

        // Always run bcrypt to prevent timing attacks
        $dummyHash = Auth::hashPassword('dummy');
        $passwordHash = $user['password'] ?? $dummyHash;
        $passwordValid = Auth::verifyPassword($data['password'], $passwordHash);

        if (!$user || !$passwordValid) {
            Security::logSecurityEvent([
                'event_type'  => Security::EVENT_LOGIN_FAILED,
                'ip_address'  => $clientIp,
                'user_agent'  => $ua,
                'details'     => 'username=' . $data['username'],
                'severity'    => 'medium',
                'user_id'     => $user['id'] ?? null,
            ]);
            if ($user) {
                $newAttempts = (int)($user['failed_login_attempts'] ?? 0) + 1;
                $lockUntil = $newAttempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $userModel->update($user['id'], [
                    'failed_login_attempts' => $newAttempts,
                    'locked_until'          => $lockUntil,
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]);
            }
            throw HttpException::unauthorized('Invalid username or password');
        }

        if (!$user['is_active']) {
            throw HttpException::forbidden('Account is inactive');
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new HttpException(
                'Account locked. Try again after ' . date('H:i:s', strtotime($user['locked_until'])) . '.',
                423
            );
        }

        $userModel->update($user['id'], [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        // Create session
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $sessionModel = new LoginSession();
        $sessionId = $sessionModel->createSession([
            'id'                => Util::uuid(),
            'user_id'           => $user['id'],
            'session_token'     => 'placeholder',
            'ip_address'        => $clientIp,
            'user_agent'        => $ua,
            'expires_at'        => $expiresAt,
            'is_active'         => 1,
            'last_activity_at'  => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $token = Auth::generateToken([
            'user_id'    => $user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'session_id' => $sessionId,
        ]);

        $tokenHash = Util::sha256($token);
        $sessionModel->update($sessionId, [
            'session_token' => $tokenHash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        Security::logSecurityEvent([
            'event_type'  => Security::EVENT_LOGIN_SUCCESS,
            'ip_address'  => $clientIp,
            'user_agent'  => $ua,
            'user_id'     => $user['id'],
            'severity'    => 'low',
        ]);

        $response = Response::success([
            'token' => $token,
            'user'  => [
                'id'                  => $user['id'],
                'username'            => $user['username'],
                'email'               => $user['email'],
                'role'                => $user['role'],
                'first_name'          => $user['first_name'],
                'last_name'           => $user['last_name'],
                'must_change_password' => (bool)$user['must_change_password'],
            ],
        ], 'Login successful');

        $response->withCookie(Auth::setAuthCookie($token));
        return $response;
    }

    /** POST /api/auth/logout */
    public function logout(Request $request, array $params = []): Response
    {
        $token = $this->token;
        if ($token) {
            Auth::blacklistToken($token, $this->userId(), 'logout');
            // Revoke session
            $sessionId = $this->user['session_id'] ?? null;
            if ($sessionId) {
                (new LoginSession())->revoke($sessionId, 'user_logout');
            }
        }
        Security::logSecurityEvent([
            'event_type'  => Security::EVENT_LOGOUT,
            'ip_address'  => Security::getClientIp($request),
            'user_agent'  => Security::getUserAgent($request),
            'user_id'     => $this->userId(),
            'severity'    => 'low',
        ]);
        $response = Response::success(null, 'Logged out');
        $response->withCookie(Auth::clearAuthCookie());
        return $response;
    }

    /** GET /api/auth/me */
    public function me(Request $request, array $params = []): Response
    {
        $user = (new User())->findActiveById($this->userId());
        if (!$user) {
            throw HttpException::unauthorized('User not found');
        }
        return Response::success([
            'id'                  => $user['id'],
            'username'            => $user['username'],
            'email'               => $user['email'],
            'role'                => $user['role'],
            'first_name'          => $user['first_name'],
            'last_name'           => $user['last_name'],
            'must_change_password' => (bool)$user['must_change_password'],
            'two_factor_enabled'  => (bool)$user['two_factor_enabled'],
        ]);
    }
}
