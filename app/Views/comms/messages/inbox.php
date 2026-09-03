<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Inbox</h1>
        <p class="lede">Internal system messages.</p>
    </div>
    <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/messages/compose') ?>">
        <?= icon('plus', 'ico-sm') ?> New Message
    </a>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if (empty($messages)): ?>
            <div class="p-4 text-center text-muted-sm"><p class="mb-0">Your inbox is empty.</p></div>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <a href="<?= url('/messages/' . $m['id']) ?>" class="list-group-item list-group-item-action <?= !$m['read_at'] ? 'fw-bold bg-light' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>From: <?= e($m['first_name'] . ' ' . $m['last_name']) ?></span>
                    <small class="text-muted fw-normal"><?= date_fmt($m['created_at']) ?></small>
                </div>
                <div class="mb-1 text-primary"><?= e($m['subject']) ?></div>
                <div class="text-muted-sm fw-normal"><?= e(str_limit($m['body'], 100)) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endsection(); ?>
