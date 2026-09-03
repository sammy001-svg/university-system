<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use Throwable;

final class App
{
    private Router $router;
    private Request $request;

    public function __construct(private string $basePath)
    {
        $this->registerAutoloader();
        Config::load(require $this->basePath . '/config/config.php');
        require_once $this->basePath . '/app/Support/helpers.php';

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
        $this->configureErrorReporting();

        Session::start();
        $this->request = new Request();
        $this->router  = new Router();
    }

    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'App' . chr(92);
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path     = $this->basePath . '/app/' . str_replace(chr(92), '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    private function configureErrorReporting(): void
    {
        $debug = (bool) Config::get('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_exception_handler([$this, 'handleException']);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error($error['message'], ['file' => $error['file'], 'line' => $error['line']]);
            }
        });
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function run(): void
    {
        try {
            $this->shareViewGlobals();
            $output = $this->router->dispatch($this->request);
            if (is_string($output)) {
                echo $output;
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function shareViewGlobals(): void
    {
        View::share('appName', Setting::get('institution_name', Config::get('app.name')));
        View::share('appShortName', Setting::get('institution_short_name', Config::get('app.short_name')));
        View::share('currentUser', Auth::user());
        View::share('flash', [
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
            'warning' => Session::getFlash('warning'),
            'info'    => Session::getFlash('info'),
        ]);
    }

    public function handleException(Throwable $e): void
    {
        Logger::exception($e);

        $status  = $e instanceof HttpException ? $e->statusCode() : 500;
        $message = $e instanceof HttpException
            ? $e->getMessage()
            : ((bool) Config::get('app.debug') ? $e->getMessage() : HttpException::defaultMessage(500));

        if (!headers_sent()) {
            http_response_code($status);
        }

        if ($this->request->wantsJson()) {
            Response::json(['ok' => false, 'error' => $message, 'status' => $status], $status);
        }

        // 401 sends the visitor to the sign-in page instead of an error card.
        if ($status === 401 && !Auth::check()) {
            Session::flash('warning', 'Please sign in to continue.');
            Response::redirect('/login');
        }

        View::reset();
        $view = View::exists('errors.' . $status) ? 'errors.' . $status : 'errors.error';
        echo View::render($view, [
            'status'    => $status,
            'message'   => $message,
            'exception' => (bool) Config::get('app.debug') ? $e : null,
        ]);
    }
}
