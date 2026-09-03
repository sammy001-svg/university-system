<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

/** Usage: 'permission:students.view' or 'permission:students.view,students.edit' (any-of). */
final class PermissionMiddleware
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if ($argument === null || $argument === '') {
            return;
        }
        $permissions = array_map('trim', explode(',', $argument));
        if (!Auth::can($permissions)) {
            throw new HttpException(403, 'You do not have permission to access this area.');
        }
    }
}
