<?php
use App\Core\Auth;
use App\Core\Notify;
use App\Models\Message;

$user           = $currentUser ?? Auth::user();
$notifications  = $user ? Notify::latest((int) $user['id'], 6) : [];
$unreadNotifs   = $user ? Notify::unreadCount((int) $user['id']) : 0;
$unreadMessages = $user ? (new Message())->unreadCount((int) $user['id']) : 0;
$displayName    = $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'Guest';
?>
<header class="topbar no-print">
    <button class="btn btn-sm btn-outline-secondary sidebar-toggle" data-sidebar-toggle aria-label="Toggle navigation">
        <?= icon('dashboard', 'ico-sm') ?>
    </button>

    <h1 class="page-title d-none d-sm-block"><?= e($pageTitle ?? 'Dashboard') ?></h1>

    <form class="search-box ms-auto d-none d-md-block" action="<?= url('/search') ?>" method="get">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><?= icon('search', 'ico-sm') ?></span>
            <input type="search" name="q" class="form-control border-start-0" placeholder="Search students, staff, courses..."
                   value="<?= e($_GET['q'] ?? '') ?>">
        </div>
    </form>

    <div class="ms-auto ms-md-0 d-flex align-items-center gap-2">
        <a href="<?= url('/messages') ?>" class="btn btn-sm btn-light position-relative" title="Messages">
            <?= icon('mail', 'ico-sm') ?>
            <?php if ($unreadMessages > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= $unreadMessages ?></span>
            <?php endif; ?>
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-light position-relative" data-bs-toggle="dropdown" title="Notifications">
                <?= icon('bell', 'ico-sm') ?>
                <?php if ($unreadNotifs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= $unreadNotifs ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="width:330px">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <strong class="small">Notifications</strong>
                    <a class="small" href="<?= url('/notifications') ?>">View all</a>
                </div>
                <?php if ($notifications === []): ?>
                    <div class="p-3 text-muted-sm">Nothing new right now.</div>
                <?php else: ?>
                    <div style="max-height:340px;overflow:auto">
                        <?php foreach ($notifications as $note): ?>
                            <a class="dropdown-item py-2 border-bottom <?= $note['read_at'] === null ? 'bg-light' : '' ?>"
                               href="<?= url('/notifications/' . $note['id'] . '/read') ?>">
                                <div class="d-flex gap-2">
                                    <span class="text-<?= status_class($note['type']) ?>"><?= icon($note['icon'] ?: 'bell', 'ico-sm') ?></span>
                                    <div class="flex-grow-1" style="white-space:normal">
                                        <div class="small fw-semibold"><?= e($note['title']) ?></div>
                                        <div class="text-muted-sm"><?= e(str_limit($note['message'], 70)) ?></div>
                                        <div class="text-muted-sm"><?= ago($note['created_at']) ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <span class="avatar avatar-sm" style="background:<?= e(avatar_color($displayName)) ?>">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= uploaded($user['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= e(initials($user['first_name'] ?? '', $user['last_name'] ?? '')) ?>
                    <?php endif; ?>
                </span>
                <span class="d-none d-lg-inline"><?= e($displayName) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-semibold small"><?= e($displayName) ?></div>
                    <div class="text-muted-sm"><?= e(humanize($user['user_type'] ?? '')) ?></div>
                </li>
                <li><a class="dropdown-item" href="<?= url('/profile') ?>"><?= icon('users', 'ico-sm') ?> My profile</a></li>
                <li><a class="dropdown-item" href="<?= url('/password/change') ?>"><?= icon('shield', 'ico-sm') ?> Change password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="<?= url('/logout') ?>" method="post" class="m-0">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><?= icon('logout', 'ico-sm') ?> Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
