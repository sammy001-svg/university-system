<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Announcements</h1>
        <p class="lede">Broad institutional communications and news.</p>
    </div>
    <?php if (can('announcements.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/announcements/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New Announcement
        </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php if (empty($announcements)): ?>
        <div class="col-12"><div class="card p-4 text-center text-muted"><p class="mb-0">No announcements published.</p></div></div>
    <?php endif; ?>
    <?php foreach ($announcements as $a): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge text-bg-info"><?= e(humanize($a['audience'])) ?></span>
                        <span class="text-muted-sm"><?= date_fmt($a['published_at']) ?></span>
                    </div>
                    <h5 class="card-title mb-2"><?= e($a['title']) ?></h5>
                    <p class="card-text text-muted mb-3"><?= nl2br(e(str_limit($a['body'], 200))) ?></p>
                    <div class="text-muted-sm">Published by <?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                </div>
                <?php if (can('announcements.edit')): ?>
                <div class="card-footer text-end">
                    <a href="<?= url('/announcements/' . $a['id'] . '/edit') ?>" class="btn btn-sm btn-light"><?= icon('edit','ico-sm') ?> Edit</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
