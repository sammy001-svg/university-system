<?php
declare(strict_types=1);

use App\Core\Acl;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Setting;
use App\Core\View;

/** Escape a value for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return Response::url($path);
}

function asset(string $path): string
{
    return Response::url('assets/' . ltrim($path, '/'));
}

function uploaded(?string $path, string $fallback = 'assets/img/avatar.svg'): string
{
    if ($path === null || $path === '') {
        return Response::url($fallback);
    }
    return Response::url(ltrim($path, '/'));
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, mixed $default = null): mixed
{
    return Setting::get($key, $default);
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function old(string $key, mixed $default = ''): string
{
    return e(Session::old($key, $default));
}

function old_raw(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

function has_error(string $field): bool
{
    $errors = Session::get('_flash')['errors'] ?? [];
    return isset($errors[$field]);
}

function error_for(string $field): string
{
    $errors = Session::get('_flash')['errors'] ?? [];
    return e($errors[$field] ?? '');
}

function auth(): ?array
{
    return Auth::user();
}

function auth_id(): ?int
{
    return Auth::id();
}

function can(string|array $permission, bool $requireAll = false): bool
{
    return Auth::can($permission, $requireAll);
}

function has_role(string|array $roles): bool
{
    return Auth::hasRole($roles);
}

/** Format money in the configured currency. */
function money(float|int|string|null $amount, bool $withSymbol = true): string
{
    $value  = number_format((float) ($amount ?? 0), 2);
    $symbol = (string) Setting::get('currency_symbol', Config::get('locale.currency_symbol', '$'));
    return $withSymbol ? $symbol . ' ' . $value : $value;
}

function currency_code(): string
{
    return (string) Setting::get('currency_code', Config::get('locale.currency', 'USD'));
}

function currency_symbol(): string
{
    return (string) Setting::get('currency_symbol', Config::get('locale.currency_symbol', '$'));
}

function fdate(?string $date, string $format = null): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '&mdash;';
    }
    $timestamp = strtotime($date);
    return $timestamp === false ? '&mdash;' : date($format ?? (string) Config::get('locale.date_format', 'd M Y'), $timestamp);
}

function date_fmt(?string $date, string $format = null): string
{
    return fdate($date, $format);
}

function fdatetime(?string $date): string
{
    return fdate($date, (string) Config::get('locale.datetime_format', 'd M Y H:i'));
}

/** "3 hours ago" style relative time. */
function ago(?string $date): string
{
    if ($date === null || $date === '') {
        return '&mdash;';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '&mdash;';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    foreach ([31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $seconds => $unit) {
        if ($diff >= $seconds) {
            $count = (int) floor($diff / $seconds);
            return $count . ' ' . $unit . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** Turn snake_case / kebab-case into Title Case. */
function humanize(?string $value): string
{
    return ucwords(str_replace(['_', '-'], ' ', (string) $value));
}

function initials(?string $first, ?string $last = null): string
{
    $a = mb_substr(trim((string) $first), 0, 1);
    $b = mb_substr(trim((string) $last), 0, 1);
    return strtoupper($a . $b) ?: 'U';
}

function str_limit(?string $value, int $limit = 80, string $end = '...'): string
{
    $value = trim((string) $value);
    return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . $end;
}

/** Bootstrap contextual class for a status value. */
function status_class(?string $status): string
{
    return match (strtolower((string) $status)) {
        'active', 'approved', 'paid', 'confirmed', 'cleared', 'present', 'published', 'pass', 'graduated', 'available', 'completed', 'returned', 'open' => 'success',
        'pending', 'partial', 'draft', 'planned', 'under_review', 'submitted', 'requested', 'processing', 'in_progress', 'late', 'borrowed', 'on_leave' => 'warning',
        'inactive', 'closed', 'archived', 'dropped', 'excused', 'deferred', 'reserved', 'maintenance' => 'secondary',
        'suspended', 'rejected', 'failed', 'fail', 'overdue', 'absent', 'cancelled', 'expelled', 'withdrawn', 'blocked', 'lost', 'reversed', 'terminated', 'urgent' => 'danger',
        default => 'info',
    };
}

function status_badge(?string $status): string
{
    return '<span class="badge text-bg-' . status_class($status) . '">' . e(humanize($status)) . '</span>';
}

/** Build a query string preserving the current filters. */
function query_with(array $params): string
{
    $merged = array_merge($_GET, $params);
    $merged = array_filter($merged, static fn ($v) => $v !== null && $v !== '');
    return $merged === [] ? '' : '?' . http_build_query($merged);
}

function active_if(string ...$prefixes): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    foreach ($prefixes as $prefix) {
        if (str_contains($path, $prefix)) {
            return 'active';
        }
    }
    return '';
}

function percent(float|int|null $value, float|int|null $total, int $decimals = 1): string
{
    $total = (float) $total;
    if ($total <= 0.0) {
        return '0%';
    }
    return number_format(((float) $value / $total) * 100, $decimals) . '%';
}

/* ------------------------------------------------------------------ */
/*  View helpers                                                       */
/* ------------------------------------------------------------------ */

function layout(string $name): void
{
    View::extend($name);
}

function section(string $name): void
{
    View::startSection($name);
}

function endsection(): void
{
    View::endSection();
}

function yield_section(string $name, string $default = ''): string
{
    return View::section($name, $default);
}

function partial(string $view, array $data = []): void
{
    View::partial($view, $data);
}

/** Render a <select> option list. */
function options(array $items, mixed $selected = null, ?string $placeholder = null): string
{
    $html = '';
    if ($placeholder !== null) {
        $html .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($items as $value => $label) {
        $isSelected = (string) $value === (string) $selected ? ' selected' : '';
        $html .= '<option value="' . e((string) $value) . '"' . $isSelected . '>' . e((string) $label) . '</option>';
    }
    return $html;
}

/** Render options from a list of enum-style strings. */
function enum_options(array $values, mixed $selected = null, ?string $placeholder = null): string
{
    $items = [];
    foreach ($values as $value) {
        $items[$value] = humanize($value);
    }
    return options($items, $selected, $placeholder);
}

/** Pagination links for a paginate() result. */
function paginate_links(array $result, int $window = 2): string
{
    $last = (int) ($result['last_page'] ?? 1);
    if ($last <= 1) {
        return '';
    }
    $page  = (int) $result['page'];
    $html  = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . e(query_with(['page' => $page - 1])) . '">&laquo;</a></li>';

    $start = max(1, $page - $window);
    $end   = min($last, $page + $window);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(query_with(['page' => 1])) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '">'
               . '<a class="page-link" href="' . e(query_with(['page' => $i])) . '">' . $i . '</a></li>';
    }
    if ($end < $last) {
        if ($end < $last - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e(query_with(['page' => $last])) . '">' . $last . '</a></li>';
    }
    $html .= '<li class="page-item' . ($page >= $last ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . e(query_with(['page' => $page + 1])) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

/** Inline SVG icon from the shared icon set. */
function icon(string $name, string $classes = ''): string
{
    static $paths = null;
    if ($paths === null) {
        $paths = require __DIR__ . '/icons.php';
    }
    $path = $paths[$name] ?? $paths['dashboard'];
    return '<svg class="ico ' . e($classes) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . $path . '"/></svg>';
}

/** Sortable table header link. */
function sort_link(string $label, string $column, string $currentSort = '', string $currentDir = 'asc'): string
{
    $direction = ($currentSort === $column && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';
    $arrow     = $currentSort === $column ? (strtolower($currentDir) === 'asc' ? ' &uarr;' : ' &darr;') : '';
    return '<a class="table-sort" href="' . e(query_with(['sort' => $column, 'dir' => $direction])) . '">' . e($label) . $arrow . '</a>';
}

/** Deterministic colour for an avatar bubble. */
function avatar_color(string $seed): string
{
    $palette = ['#0f2c52', '#1d4ed8', '#0f766e', '#7c3aed', '#b45309', '#be123c', '#0369a1', '#4d7c0f'];
    return $palette[abs(crc32($seed)) % count($palette)];
}

/** GPA to degree classification. */
function classify_gpa(float $cgpa): string
{
    return match (true) {
        $cgpa >= 3.60 => 'First Class Honours',
        $cgpa >= 3.00 => 'Second Class Honours (Upper Division)',
        $cgpa >= 2.40 => 'Second Class Honours (Lower Division)',
        $cgpa >= 2.00 => 'Pass',
        default       => 'Fail',
    };
}
