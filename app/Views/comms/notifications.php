<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Notifications</h1>
        <p class="lede">System notifications &amp; alerts.</p>
    </div>
    <form method="post" action="<?= url('/notifications/read-all') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-secondary">Mark All as Read</button>
    </form>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-muted-sm"><p class="mb-0">No notifications found.</p></div>
        <?php endif; ?>
        <?php foreach ($notifications as $n): ?>
            <div class="list-group-item <?= !$n['is_read'] ? 'bg-light fw-bold' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-primary"><?= e($n['title'] ?? 'Notification') ?></span>
                    <small class="text-muted fw-normal"><?= date_fmt($n['created_at']) ?></small>
                </div>
                <div class="text-muted-sm fw-normal"><?= e($n['body'] ?? '') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endsection(); ?>
