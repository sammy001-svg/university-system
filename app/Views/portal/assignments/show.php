<?php
use App\Services\Elearning\AssignmentService;

layout('layouts.app');
section('content');
$a   = $assignment;
$s   = $submission;
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$canSubmit = ($s === null || $s['graded_at'] === null) && in_array($phase, ['open', 'late'], true);
?>
<div class="page-head">
    <div>
        <h1><?= e($a['title']) ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; marked out of <?= $fmt($a['max_score']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/portal/assignments') ?>">&larr; All assignments</a>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <?php if ($s !== null && $s['graded_at'] !== null): ?>
            <div class="card mb-3" style="border-left:4px solid var(--ok)">
                <div class="card-body d-flex gap-4 align-items-center flex-wrap">
                    <div class="figure-lg"><?= $fmt($s['score']) ?> <small class="fs-5 text-muted">/ <?= $fmt($a['max_score']) ?></small></div>
                    <div>
                        <?php if ((float) $s['raw_score'] !== (float) $s['score']): ?><div class="text-muted-sm"><?= $fmt($s['raw_score']) ?> before the <?= (int) $a['late_penalty'] ?>% late penalty</div><?php endif; ?>
                        <?php if ($s['feedback']): ?><div><b>Feedback:</b> <?= e($s['feedback']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><?= icon('file') ?> Instructions</div>
            <div class="card-body">
                <?= $a['instructions'] ? '<div style="white-space:pre-wrap">' . e($a['instructions']) . '</div>' : '<p class="text-muted mb-0">No written instructions.</p>' ?>
                <?php if ($a['attachment_name']): ?>
                    <a class="btn btn-sm btn-light mt-3" target="_blank" href="<?= url('/files/assignments/' . $a['id']) ?>"><?= icon('download', 'ico-sm') ?> <?= e($a['attachment_name']) ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canSubmit): ?>
            <div class="card">
                <div class="card-header"><?= icon('upload') ?> <?= $s ? 'Replace your submission' : 'Hand in your work' ?></div>
                <div class="card-body">
                    <?php if ($phase === 'late'): ?>
                        <div class="alert alert-warning py-2">The deadline has passed. Late work is accepted<?= (int) $a['late_penalty'] > 0 ? ' but loses ' . (int) $a['late_penalty'] . '% of the mark' : '' ?>.</div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" action="<?= url('/portal/assignments/' . $a['id']) ?>">
                        <?= csrf_field() ?>
                        <?php if ($a['submission_type'] !== 'text'): ?>
                            <div class="mb-3">
                                <label class="form-label">File</label>
                                <input class="form-control" type="file" name="file" <?= $a['submission_type'] === 'file' && $s === null ? 'required' : '' ?>>
                                <div class="form-text"><?= e(strtoupper(implode(', ', AssignmentService::FILE_TYPES))) ?>, up to <?= AssignmentService::MAX_MB ?> MB.</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($a['submission_type'] !== 'file'): ?>
                            <div class="mb-3">
                                <label class="form-label">Your answer</label>
                                <textarea class="form-control" name="text_answer" rows="10"><?= e((string) ($s['text_answer'] ?? '')) ?></textarea>
                            </div>
                        <?php endif; ?>
                        <button class="btn btn-primary"><?= icon('upload', 'ico-sm') ?> <?= $s ? 'Hand in again' : 'Hand in' ?></button>
                        <?php if ($s): ?><span class="text-muted-sm ms-2">Your new work replaces what you handed in before.</span><?php endif; ?>
                    </form>
                </div>
            </div>
        <?php elseif ($s === null && $phase === 'closed'): ?>
            <div class="alert alert-secondary">The deadline has passed and late work is not accepted.</div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><?= icon('clipboard') ?> Status</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Deadline</dt><dd class="col-7"><?= fdatetime($a['due_at']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Time left</dt><dd class="col-7"><?= $phase === 'open' ? e(time_until($a['due_at'])) : 'Deadline passed' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Late work</dt><dd class="col-7"><?= (int) $a['allow_late'] === 1 ? 'Accepted' . ((int) $a['late_penalty'] > 0 ? ', -' . (int) $a['late_penalty'] . '%' : '') : 'Not accepted' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Your work</dt>
                    <dd class="col-7 mb-0">
                        <?php if ($s === null): ?>Not handed in
                        <?php else: ?>Handed in <?= fdatetime($s['submitted_at']) ?><?= (int) $s['is_late'] === 1 ? ' <span class="badge text-bg-warning">Late</span>' : '' ?>
                            <?php if ($s['file_name']): ?><br><a target="_blank" href="<?= url('/files/submissions/' . $s['id']) ?>"><?= e($s['file_name']) ?></a><?php endif; ?>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
