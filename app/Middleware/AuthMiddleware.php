<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Blocks guests and forces a password change when one is pending. */
final class AuthMiddleware
{
    public function handle(Request $request, ?string $argument = null): void
    {
        if (!Auth::check()) {
            Session::flash('warning', 'Please sign in to continue.');
            Session::put('intended_url', $request->uri());
            throw new HttpException(401);
        }

        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401);
        }

        if ($user['status'] !== 'active') {
            Auth::logout();
            Session::flash('error', 'Your account is no longer active.');
            Response::redirect('/login');
        }

        if ((int) $user['must_change_password'] === 1 && !str_contains($request->uri(), 'password/change')) {
            Session::flash('warning', 'Please set a new password before continuing.');
            Response::redirect('/password/change');
        }
    }
}
