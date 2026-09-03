<?php
/** Renders one table cell according to its column definition. */
$key   = $column['key'];
$value = $row[$key] ?? null;
$type  = $column['type'] ?? 'text';

switch ($type) {
    case 'badge':
        echo status_badge((string) $value);
        break;

    case 'money':
        echo '<span class="fw-semibold">' . e(money($value)) . '</span>';
        break;

    case 'date':
        echo fdate($value);
        break;

    case 'datetime':
        echo fdatetime($value);
        break;

    case 'ago':
        echo ago($value);
        break;

    case 'bool':
        echo $value
            ? '<span class="badge text-bg-success">Yes</span>'
            : '<span class="badge text-bg-secondary">No</span>';
        break;

    case 'number':
        echo number_format((float) $value, (int) ($column['decimals'] ?? 0));
        break;

    case 'code':
        echo '<code class="text-nowrap">' . e((string) $value) . '</code>';
        break;

    case 'strong':
        echo '<span class="fw-semibold">' . e((string) $value) . '</span>';
        break;

    case 'link':
        $href = str_replace('{id}', (string) ($row['id'] ?? ''), (string) ($column['url'] ?? $routeBase . '/{id}'));
        echo '<a class="fw-semibold" href="' . url($href) . '">' . e((string) $value) . '</a>';
        break;

    case 'person':
        $name = trim(($row[$column['first'] ?? 'first_name'] ?? '') . ' ' . ($row[$column['last'] ?? 'last_name'] ?? ''));
        $sub  = $row[$column['sub'] ?? ''] ?? '';
        echo '<div class="d-flex align-items-center gap-2">'
           . '<span class="avatar avatar-sm" style="background:' . e(avatar_color($name)) . '">'
           . e(initials($row[$column['first'] ?? 'first_name'] ?? '', $row[$column['last'] ?? 'last_name'] ?? ''))
           . '</span><div><div class="fw-semibold">' . e($name) . '</div>'
           . ($sub !== '' ? '<div class="text-muted-sm">' . e((string) $sub) . '</div>' : '')
           . '</div></div>';
        break;

    case 'meter':
        $pct = (float) $value;
        $tone = $pct >= 75 ? 'ok' : ($pct >= 50 ? '' : ($pct >= 30 ? 'warn' : 'bad'));
        echo '<div class="d-flex align-items-center gap-2"><div class="meter flex-grow-1 ' . $tone . '" style="min-width:60px">'
           . '<span style="width:' . min(100, max(0, $pct)) . '%"></span></div>'
           . '<span class="text-muted-sm">' . number_format($pct, 1) . '%</span></div>';
        break;

    case 'humanize':
        echo e(humanize((string) $value));
        break;

    default:
        $text = (string) ($value ?? '');
        if ($text === '') {
            echo '<span class="text-muted">&mdash;</span>';
        } else {
            echo e(isset($column['limit']) ? str_limit($text, (int) $column['limit']) : $text);
        }
}
