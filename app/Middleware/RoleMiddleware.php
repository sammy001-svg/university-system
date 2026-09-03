<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

/** Usage: 'role:student' or 'role:lecturer,hod'. */
final class RoleMiddleware
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if ($argument === null || $argument === '') {
            return;
        }
        $roles = array_map('trim', explode(',', $argument));
        if (!Auth::hasRole($roles) && !Auth::isSuperAdmin()) {
            throw new HttpException(403, 'This area is restricted.');
        }
    }
}
