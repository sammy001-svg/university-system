<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Logger;
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
        if (Auth::can($permissions)) {
            return;
        }

        // Say which permission was wanted and for which page. Without this the
        // refusal looks the same wherever it comes from, and tracing a report of
        // it means guessing at the route.
        $needed = implode(' or ', $permissions);
        $who    = Auth::check()
            ? 'signed in as ' . (Auth::user()['username'] ?? ('user #' . Auth::id()))
            : 'not signed in';

        Logger::warning('Access refused: ' . $request->uri() . ' needs ' . $needed . ' (' . $who . ')');

        throw new HttpException(403, 'You do not have permission to access this area. '
            . 'It needs "' . $needed . '", which this account does not have.');
    }
}
