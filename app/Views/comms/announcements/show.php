<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div>
        <h1><?= e($a['title']) ?></h1>
        <p class="lede"><?= date_fmt($a['published_at'] ?? $a['created_at']) ?> &middot; <?= e($a['author_name'] ?: 'The university') ?>
            &middot; for <?= e(humanize($a['audience'])) ?> &middot; <?= status_badge($a['priority']) ?>
            <?php if ($a['status'] !== 'published'): ?><span class="badge text-bg-secondary"><?= e(ucfirst($a['status'])) ?></span><?php endif; ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('announcements.edit')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/announcements/' . $a['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-light" href="<?= url('/announcements') ?>">&larr; All announcements</a>
    </div>
</div>
<div class="card" style="max-width:860px">
    <div class="card-body">
        <div style="white-space:pre-wrap; line-height:1.7"><?= e($a['body']) ?></div>
        <?php if (!empty($a['attachment'])): ?>
            <a class="btn btn-sm btn-light mt-3" target="_blank" href="<?= e(uploaded($a['attachment'])) ?>"><?= icon('download', 'ico-sm') ?> Attachment</a>
        <?php endif; ?>
        <?php if (!empty($a['expires_at'])): ?><p class="text-muted-sm mt-3 mb-0">Valid until <?= date_fmt($a['expires_at']) ?>.</p><?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
