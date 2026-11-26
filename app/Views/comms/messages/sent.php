<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Sent</h1>
        <p class="lede">Messages you have sent, newest first.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-light btn-sm btn-icon" href="<?= url('/messages') ?>"><?= icon('mail', 'ico-sm') ?> Inbox</a>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/messages/compose') ?>"><?= icon('plus', 'ico-sm') ?> New Message</a>
    </div>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if ($messages === []): ?>
            <div class="p-4 text-center text-muted-sm"><p class="mb-0">You have not sent any messages yet.</p></div>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <a href="<?= url('/messages/' . $m['id']) ?>" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>To: <?= e($m['first_name'] . ' ' . $m['last_name']) ?></span>
                    <small class="text-muted"><?= date_fmt($m['created_at']) ?>
                        <?= $m['read_at'] ? '&middot; read' : '&middot; unread' ?></small>
                </div>
                <div class="mb-1 text-primary"><?= e($m['subject']) ?></div>
                <div class="text-muted-sm"><?= e(str_limit($m['body'], 100)) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endsection(); ?>
