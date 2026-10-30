<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div>
        <h1><?= e($import['file_name']) ?></h1>
        <p class="lede">
            <?= status_badge($import['status'] === 'ok' ? 'completed' : $import['status']) ?>
            &middot; <?= (int) $import['created_count'] ?> created, <?= (int) $import['updated_count'] ?> updated,
            <?= (int) $import['failed_count'] ?> failed
            &middot; <?= fdatetime($import['imported_at']) ?> (<?= number_format($import['duration_ms'] / 1000, 1) ?> s)
            <?php if (!empty($import['source'])): ?>&middot; source: <?= e($import['source']) ?><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports') ?>">&larr; Back</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('clipboard') ?> Import report</div>
    <div class="card-body">
        <pre class="mb-0 small" style="white-space: pre-wrap"><?= e((string) $import['report']) ?></pre>
    </div>
</div>
<?php endsection(); ?>
