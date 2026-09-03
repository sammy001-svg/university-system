<?php
/**
 * Navigation is permission-driven: a link only appears when the signed-in
 * user holds at least one of the permissions listed for it. Items that also
 * declare 'user_type' are personal workspaces and additionally require the
 * signed-in user to be of that type.
 */
$nav = require config('paths.app') . '/Support/navigation.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= url('/dashboard') ?>">
        <?php if (!empty($appLogo)): ?>
            <img src="<?= uploaded($appLogo) ?>" alt="Logo" class="sidebar-logo me-2" style="height:32px;width:32px;object-fit:contain;border-radius:4px">
        <?php else: ?>
            <span class="crest"><?= e(substr((string) $appShortName, 0, 2)) ?></span>
        <?php endif; ?>
        <span>
            <span class="name d-block"><?= e(str_limit($appName, 22, '')) ?></span>
            <span class="sub">Management System</span>
        </span>
    </a>

    <nav class="pb-4">
        <?php foreach ($nav as $group): ?>
            <?php
            $userType = $currentUser['user_type'] ?? null;
            $visible = array_filter($group['items'], static function (array $item) use ($userType): bool {
                if (isset($item['user_type']) && !in_array($userType, (array) $item['user_type'], true)) {
                    return false;
                }
                if (($item['permission'] ?? null) === null) {
                    return true;
                }
                return can($item['permission']);
            });
            if ($visible === []) { continue; }
            ?>
            <div class="nav-group-title"><?= e($group['title']) ?></div>
            <?php foreach ($visible as $item): ?>
                <?php
                $href   = $item['url'];
                $active = $href === '/dashboard'
                    ? (str_ends_with($currentPath, '/dashboard') ? 'active' : '')
                    : (str_contains($currentPath, rtrim($href, '/')) ? 'active' : '');
                ?>
                <a class="nav-link <?= $active ?>" href="<?= url($href) ?>">
                    <?= icon($item['icon'] ?? 'dashboard') ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
