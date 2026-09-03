<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

abstract class Controller
{
    /** Render a view inside the application layout. */
    protected function view(string $view, array $data = [], int $status = 200): string
    {
        http_response_code($status);
        View::reset();
        return View::render($view, $data);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    protected function back(string $fallback = '/dashboard'): never
    {
        Response::back($fallback);
    }

    /** Redirect with a success flash. */
    protected function success(string $message, string $path): never
    {
        Session::flash('success', $message);
        Response::redirect($path);
    }

    protected function error(string $message, ?string $path = null): never
    {
        Session::flash('error', $message);
        if ($path === null) {
            Response::back();
        }
        Response::redirect($path);
    }

    protected function warning(string $message, string $path): never
    {
        Session::flash('warning', $message);
        Response::redirect($path);
    }

    /**
     * Validate the request, or bounce back with errors and the old input.
     * @return array<string,mixed> the validated input
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        $data      = $request->all();
        $validator = Validator::make($data, $rules, $labels);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                Response::json(['ok' => false, 'errors' => $validator->flatErrors()], 422);
            }
            Session::flash('errors', $validator->flatErrors());
            Session::flash('error', $validator->firstError() ?? 'Please correct the highlighted fields.');
            Session::keepOldInput($data);
            Response::back();
        }
        return $validator->validated();
    }

    /** Reject the request unless the CSRF token matches. */
    protected function verifyCsrf(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!Csrf::verify((string) $request->input('_token', ''))) {
            throw new HttpException(419, 'Your session expired. Please refresh the page and try again.');
        }
    }

    protected function authorize(string|array $permission, bool $requireAll = false): void
    {
        if (!Auth::can($permission, $requireAll)) {
            throw new HttpException(403);
        }
    }

    protected function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }

    /** Fetch a record or 404. */
    protected function findOrFail(Model $model, int|string $id, string $label = 'Record'): array
    {
        $row = $model->find($id);
        if ($row === null) {
            throw new HttpException(404, "{$label} not found.");
        }
        return $row;
    }

    protected function page(Request $request): int
    {
        return max(1, $request->int('page', 1));
    }

    protected function perPage(Request $request, int $default = 20): int
    {
        return max(5, min(200, $request->int('per_page', $default)));
    }
}
