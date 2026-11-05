<?php
use App\Controllers\Quality\EvaluationController as EC;

layout('layouts.app');
section('content');
$o = $offering;
$overall = $questions === [] ? null : array_sum(array_map(static fn ($q) => (float) $q['average'], $questions)) / count($questions);
$section = null;
?>
<div class="page-head">
    <div><h1><?= e($o['code']) ?> &middot; <?= e($o['title']) ?></h1>
        <p class="lede"><?= e($period['title']) ?> &middot; lecturer <?= e((string) ($o['lecturer_name'] ?? 'not assigned')) ?> &middot;
            <?= $responses ?> of <?= $enrolled ?> students responded (<?= $enrolled ? round($responses / $enrolled * 100) : 0 ?>%)</p></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url(can('evaluations.view') ? '/evaluations?period=' . $period['id'] : '/teaching/evaluations') ?>">&larr; Back</a></div>
</div>
<?php if ($hidden): ?>
    <div class="alert alert-info">Results are shown once at least <?= (int) $period['min_responses'] ?> students have responded, so that no individual can be identified.</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><?= icon('chart') ?> Ratings <span class="ms-auto">Overall <b><?= number_format((float) $overall, 2) ?></b> / 5</span></div>
                <div class="card-body">
                    <?php foreach ($questions as $q): ?>
                        <?php if ($q['section'] !== $section): $section = $q['section']; ?><div class="dash-subhead"><?= e($section) ?></div><?php endif; ?>
                        <div class="hbar hbar-wide">
                            <div class="hbar-label" title="<?= e($q['body']) ?>" style="white-space:normal"><?= e($q['body']) ?></div>
                            <div class="hbar-track"><span style="width:<?= round(((float) $q['average'] - 1) / 4 * 100) ?>%"></span></div>
                            <div class="hbar-value"><?= number_format((float) $q['average'], 2) ?>
                                <span title="Strongly disagree ... strongly agree"><?= (int) $q['r1'] ?>/<?= (int) $q['r2'] ?>/<?= (int) $q['r3'] ?>/<?= (int) $q['r4'] ?>/<?= (int) $q['r5'] ?></span></div>
                        </div>
                    <?php endforeach; ?>
                    <p class="text-muted-sm mt-3 mb-0">Scale: <?= e(implode(', ', array_map(static fn ($k, $v) => $k . ' = ' . $v, array_keys(EC::SCALE), EC::SCALE))) ?>. The small figures count answers from 1 to 5.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><?= icon('mail') ?> Comments <span class="badge text-bg-light"><?= count($comments) ?></span></div>
                <div class="card-body py-1" style="max-height:640px;overflow:auto">
                    <?php if ($comments === []): ?><p class="text-muted-sm py-3 mb-0">No written comments.</p><?php endif; ?>
                    <?php foreach ($comments as $c): ?><div class="py-2 border-bottom small" style="white-space:pre-wrap">&ldquo;<?= e($c) ?>&rdquo;</div><?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php endsection(); ?>
