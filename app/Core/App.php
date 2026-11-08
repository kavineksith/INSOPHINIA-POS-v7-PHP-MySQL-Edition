<?php
/**
 * App — Bootstrap the application, load routes, dispatch.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

use Insophinia\POS\Core\Database;
use Insophinia\POS\Core\Request;
use Insophinia\POS\Core\Response;
use Insophinia\POS\Core\Router;
use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\Security;
use Insophinia\POS\Lib\Settings;
use Insophinia\POS\Lib\Util;

class App
{
    private array $config;
    private Router $router;
    private Request $request;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->bootstrap();
        $this->router = new Router();
        $this->request = new Request();
    }

    private function bootstrap(): void
    {
        // Timezone
        date_default_timezone_set($this->config['app_timezone'] ?? 'Asia/Colombo');

        // Errors
        if (($this->config['debug'] ?? false) || ($this->config['app_env'] ?? '') === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', $this->config['storage']['logs'] . '/php-error.log');
        }

        // UTF-8
        mb_internal_encoding('UTF-8');

        // Session configuration (PHP sessions are NOT used for auth — JWT only —
        // but we still start a session for CSRF token storage and flash messages).
        $secure = !empty($this->config['cookie_secure']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $this->config['cookie_path'] ?? '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $this->config['cookie_samesite'] ?? 'Strict',
        ]);
        session_name('insophinia_pos');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Init DB
        Database::getInstance($this->config);

        // Init Settings cache
        Settings::warm();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /** Dispatch the request. */
    public function run(): void
    {
        $match = $this->router->match($this->request->method, $this->request->path);
        if ($match === null) {
            // If path looks like an API route, return JSON 404
            if (str_starts_with($this->request->path, '/api/')) {
                Response::error('Not found', 404)->send();
            }
            // Otherwise, attempt to render a dashboard page
            $this->renderPage($this->request->path);
            return;
        }

        try {
            // Run middleware chain
            foreach ($match['middleware'] as $mw) {
                $result = $mw($this->request, $this);
                if ($result instanceof Response) {
                    $result->send();
                }
                if ($result === false) {
                    // Middleware explicitly rejected
                    Response::error('Forbidden', 403)->send();
                }
            }

            // Build handler args (Request + path params)
            $args = [$this->request, $match['params'], $this];

            $handler = $match['handler'];
            if (is_array($handler) && is_string($handler[0]) && is_string($handler[1])) {
                // [ControllerClass::class, 'method'] — instantiate and inject
                $controllerClass = $handler[0];
                $method = $handler[1];
                if (!class_exists($controllerClass)) {
                    throw new \RuntimeException("Controller class not found: {$controllerClass}");
                }
                $controller = new $controllerClass();
                if ($controller instanceof Controller) {
                    $controller->setRequest($this->request);
                    // If auth middleware populated the request, propagate to controller
                    $controller->setPropertyIfAuth($this->request);
                }
                $result = $controller->$method(...$args);
            } elseif (is_callable($handler)) {
                $result = $handler(...$args);
            } else {
                throw new \RuntimeException('Invalid handler');
            }

            if ($result instanceof Response) {
                $result->send();
            }
            if (is_array($result)) {
                Response::success($result)->send();
            }
            // If handler returned void, do nothing (page was already rendered)
        } catch (\Insophinia\POS\Core\HttpException $e) {
            Response::error($e->getMessage(), $e->getStatusCode(), $e->getErrors())->send();
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        } catch (\Throwable $e) {
            if (($this->config['debug'] ?? false)) {
                Response::error('Server error: ' . $e->getMessage(), 500, [
                    'exception' => get_class($e),
                    'trace'     => $e->getTraceAsString(),
                ])->send();
            }
            error_log('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            Response::error('Internal server error', 500)->send();
        }
    }

    private function handlePdoException(\PDOException $e): never
    {
        // MySQL duplicate entry (SQLSTATE 23000, errno 1062) → 409
        if ($e->getCode() === '23000' && ($e->errorInfo[1] ?? 0) === 1062) {
            Response::error('Duplicate entry — record already exists', 409)->send();
        }
        // Not found
        if ($e->getCode() === '42S02') {
            Response::error('Table not found — database may need migration', 500)->send();
        }
        if (($this->config['debug'] ?? false)) {
            Response::error('Database error: ' . $e->getMessage(), 500)->send();
        }
        error_log('PDO error: ' . $e->getMessage());
        Response::error('Database error', 500)->send();
    }

    /** Render a dashboard HTML page by mapping URL path to a View file. */
    private function renderPage(string $path): void
    {
        $pageController = new \Insophinia\POS\Controllers\PageController();
        $pageController->setRequest($this->request);
        $pageController->setPropertyIfAuth($this->request);
        $pageController->render($path);
    }
}
