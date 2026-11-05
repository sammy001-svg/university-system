<?php
layout('layouts.app');
section('content');
$low = $s['cgpa'] !== null && (float) $s['cgpa'] < $minCgpa;
?>
<div class="page-head"><div><h1>My Advisor</h1><p class="lede">Your academic advisor helps you plan your courses and stay on track to graduate.</p></div></div>
<?php if ($low): ?>
    <div class="alert alert-warning"><strong>You are on academic probation</strong> (CGPA <?= number_format((float) $s['cgpa'], 2) ?>, minimum <?= number_format($minCgpa, 2) ?>). Please book a meeting with your advisor.</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card"><div class="card-header"><?= icon('user') ?> Advisor</div><div class="card-body">
            <?php if ($advisor): ?>
                <div class="fw-semibold fs-5"><?= e($advisor['name']) ?></div>
                <div class="text-muted"><?= e((string) $advisor['designation']) ?></div>
                <?php if ($advisor['email']): ?><div class="mt-2"><a href="mailto:<?= e($advisor['email']) ?>"><?= e($advisor['email']) ?></a></div><?php endif; ?>
                <?php if ($advisor['phone']): ?><div><?= e($advisor['phone']) ?></div><?php endif; ?>
            <?php else: ?><p class="text-muted mb-0">No advisor has been assigned to you yet. Contact the Office of Admission and Records.</p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header"><?= icon('clipboard') ?> Notes from advising meetings</div><div class="card-body py-1">
            <?php if ($notes === []): ?><p class="text-muted-sm py-3 mb-0">No notes yet.</p><?php endif; ?>
            <?php foreach ($notes as $n): ?>
                <div class="py-2 border-bottom"><b><?= fdate($n['meeting_date']) ?></b> <span class="text-muted-sm">&middot; <?= e((string) $n['by_name']) ?></span>
                    <div style="white-space:pre-wrap"><?= e($n['note']) ?></div>
                    <?php if ($n['follow_up_on']): ?><div class="text-muted-sm">Next meeting: <?= fdate($n['follow_up_on']) ?></div><?php endif; ?></div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>
<?php endsection(); ?>
