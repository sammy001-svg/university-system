<?php
use App\Services\Elearning\AssignmentService;

layout('layouts.app');
section('content');
$isNew = !isset($assignment['id']);
$a     = $assignment;
$val   = static fn (string $key) => old_raw($key, $a[$key] ?? '');
?>
<div class="page-head">
    <div>
        <h1><?= $isNew ? 'New assignment' : 'Edit assignment' ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; Section <?= e($offering['section']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($isNew ? '/teaching/' . $offering['id'] . '/classroom#assignments' : '/teaching/assignments/' . $a['id']) ?>">&larr; Back</a>
</div>

<form method="post" enctype="multipart/form-data" action="<?= url($isNew ? '/teaching/' . $offering['id'] . '/assignments' : '/teaching/assignments/' . $a['id']) ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?= icon('file') ?> The assignment</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="req">*</span></label>
                        <input class="form-control" name="title" required maxlength="150" value="<?= e((string) $val('title')) ?>" placeholder="e.g. Essay 1: The causes of inflation">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="instructions" rows="8" placeholder="What to do, length, referencing, how it will be marked..."><?= e((string) $val('instructions')) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Brief or resource file <span class="text-muted-sm">(optional)</span></label>
                        <input class="form-control" type="file" name="attachment">
                        <?php if (!$isNew && $a['attachment_name']): ?>
                            <div class="form-text">Current file: <a href="<?= url('/files/assignments/' . $a['id']) ?>" target="_blank"><?= e($a['attachment_name']) ?></a>. Choose a new file to replace it.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('settings') ?> Deadline and marking</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Deadline <span class="req">*</span></label>
                        <input class="form-control" type="datetime-local" name="due_at" required value="<?= e(date('Y-m-d\TH:i', strtotime((string) $val('due_at')) ?: time())) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Marked out of</label>
                        <input class="form-control" type="number" name="max_score" min="1" max="1000" step="0.5" value="<?= e((string) $val('max_score')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Students hand in</label>
                        <select class="form-select" name="submission_type"><?= options(AssignmentService::SUBMISSION_TYPES, $val('submission_type')) ?></select>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="allow_late" value="1" id="late" <?= (string) $val('allow_late') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="late">Accept late work</label>
                    </div>
                    <div>
                        <label class="form-label">Late penalty (% of the mark)</label>
                        <input class="form-control" type="number" name="late_penalty" min="0" max="100" value="<?= (int) $val('late_penalty') ?>">
                    </div>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3"><?= $isNew ? 'Set assignment and notify the class' : 'Save changes' ?></button>
        </div>
    </div>
</form>
<?php endsection(); ?>
