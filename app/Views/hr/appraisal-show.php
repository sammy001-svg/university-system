<?php
use App\Controllers\HR\AppraisalController as AC;

layout('layouts.app');
section('content');
$editableSelf = $isSelf && in_array($a['status'], ['pending', 'self_submitted'], true) && $a['cycle_status'] === 'open';
$canReview = ($isReviewer || can('appraisals.manage')) && in_array($a['status'], ['self_submitted', 'reviewed'], true) && $a['cycle_status'] === 'open';
$ratingOptions = [];
foreach (AC::RATINGS as $n => $t) { $ratingOptions[$n] = $n . ' - ' . $t; }
$text = static fn (?string $v): string => $v ? '<div style="white-space:pre-wrap">' . e($v) . '</div>' : '<span class="text-muted">&ndash;</span>';
?>
<div class="page-head">
    <div><h1><?= e($a['staff_name']) ?></h1>
        <p class="lede"><?= e($a['cycle_title']) ?> &middot; <?= e((string) $a['designation']) ?> &middot; appraiser <?= e((string) ($a['reviewer_name'] ?? 'not assigned')) ?></p></div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url(can('appraisals.view') ? '/hr/appraisals?cycle=' . $a['cycle_id'] : '/appraisals/mine') ?>">&larr; Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('user') ?> Self-assessment
                <?php if ($a['self_submitted_at']): ?><span class="ms-auto small fw-normal">submitted <?= fdate($a['self_submitted_at']) ?></span><?php endif; ?></div>
            <div class="card-body">
                <?php if ($editableSelf): ?>
                    <form method="post" action="<?= url('/appraisals/' . $a['id'] . '/self') ?>"><?= csrf_field() ?>
                        <?php foreach (['achievements' => 'Main achievements this period', 'challenges' => 'Challenges', 'goals_next' => 'Goals for the next period', 'training_needs' => 'Training and support needed'] as $k => $l): ?>
                            <div class="mb-3"><label class="form-label"><?= e($l) ?></label><textarea class="form-control" name="<?= $k ?>" rows="4"><?= e((string) $a[$k]) ?></textarea></div>
                        <?php endforeach; ?>
                        <div class="mb-3"><label class="form-label">How would you rate your overall performance?</label>
                            <select class="form-select" name="self_rating" style="max-width:320px"><?= options(AC::RATINGS, $a['self_rating'], 'Choose...') ?></select></div>
                        <div class="d-flex gap-2"><button class="btn btn-outline-primary" name="action" value="save">Save draft</button>
                            <button class="btn btn-primary" name="action" value="submit">Submit to appraiser</button></div>
                    </form>
                <?php else: ?>
                    <dl class="mb-0">
                        <dt>Main achievements</dt><dd><?= $text($a['achievements']) ?></dd>
                        <dt>Challenges</dt><dd><?= $text($a['challenges']) ?></dd>
                        <dt>Goals for the next period</dt><dd><?= $text($a['goals_next']) ?></dd>
                        <dt>Training and support needed</dt><dd><?= $text($a['training_needs']) ?></dd>
                        <dt>Self-rating</dt><dd class="mb-0"><?= $a['self_rating'] ? e(AC::RATINGS[(int) $a['self_rating']]) : '&ndash;' ?></dd>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($research !== []): ?>
            <div class="card"><div class="card-header"><?= icon('book') ?> Research output in this period</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($research as $r): ?><li class="list-group-item"><?= e($r['title']) ?> <span class="text-muted">(<?= e(humanize($r['output_type'])) ?>, <?= (int) $r['year'] ?>)</span></li><?php endforeach; ?>
                </ul></div>
        <?php endif; ?>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><?= icon('check') ?> Appraiser's review
                <?php if ($a['overall_rating'] !== null): ?><span class="ms-auto fw-bold"><?= number_format((float) $a['overall_rating'], 2) ?> / 5</span><?php endif; ?></div>
            <div class="card-body">
                <?php if ($canReview): ?>
                    <form method="post" action="<?= url('/appraisals/' . $a['id'] . '/review') ?>"><?= csrf_field() ?>
                        <?php foreach (AC::AREAS as $k => $l): ?>
                            <div class="mb-2"><label class="form-label small mb-1"><?= e($l) ?></label>
                                <select class="form-select form-select-sm" name="<?= $k ?>"><?= options($ratingOptions, $a[$k], 'Not rated') ?></select></div>
                        <?php endforeach; ?>
                        <div class="mb-3"><label class="form-label small mb-1">Comments</label><textarea class="form-control" name="reviewer_comments" rows="5"><?= e((string) $a['reviewer_comments']) ?></textarea></div>
                        <button class="btn btn-primary w-100"><?= $a['status'] === 'reviewed' ? 'Update review' : 'Complete review' ?></button>
                    </form>
                <?php elseif ($a['status'] === 'reviewed' || $a['status'] === 'acknowledged'): ?>
                    <?php foreach (AC::AREAS as $k => $l): ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom"><span><?= e($l) ?></span><b><?= $a[$k] ? (int) $a[$k] . ' - ' . e(AC::RATINGS[(int) $a[$k]]) : '&ndash;' ?></b></div>
                    <?php endforeach; ?>
                    <div class="mt-3"><?= $text($a['reviewer_comments']) ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">The appraiser reviews once the self-assessment has been submitted.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isSelf && $a['status'] === 'reviewed'): ?>
            <div class="card"><div class="card-header">Acknowledge</div><div class="card-body">
                <form method="post" action="<?= url('/appraisals/' . $a['id'] . '/self') ?>"><?= csrf_field() ?>
                    <label class="form-label small">Your comments on the review (optional)</label>
                    <textarea class="form-control mb-2" name="staff_comments" rows="3"></textarea>
                    <button class="btn btn-primary w-100" name="action" value="acknowledge">I have read my appraisal</button>
                </form></div></div>
        <?php elseif ($a['staff_comments']): ?>
            <div class="card"><div class="card-header">Staff member's response</div><div class="card-body"><?= $text($a['staff_comments']) ?></div></div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
