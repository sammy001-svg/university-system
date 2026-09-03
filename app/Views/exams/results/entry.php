<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Batch Marks Entry</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/exams/results') ?>">&larr; Back to Results</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('edit') ?> Select Course Offering for Marks Entry</div>
    <div class="list-group list-group-flush">
        <?php if (empty($offerings)): ?>
            <div class="p-3 text-muted-sm">No active course offerings available for marks entry.</div>
        <?php endif; ?>
        <?php foreach ($offerings as $o): ?>
            <a href="<?= url('/teaching/' . $o['id'] . '/marks') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <strong class="text-primary"><?= e($o['code']) ?></strong> &ndash; <?= e($o['title']) ?> (Section <?= e($o['section']) ?>)
                    <div class="text-muted-sm"><?= e($o['semester_name']) ?></div>
                </div>
                <span class="btn btn-sm btn-outline-primary">Enter Marks &rarr;</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endsection(); ?>
