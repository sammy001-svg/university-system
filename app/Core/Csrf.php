<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::has(self::KEY)) {
            Session::put(self::KEY, bin2hex(random_bytes(32)));
        }
        return (string) Session::get(self::KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get(self::KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public static function rotate(): void
    {
        Session::put(self::KEY, bin2hex(random_bytes(32)));
    }
}
