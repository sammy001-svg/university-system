<?php
layout('layouts.app');
section('content');
$low = $s['cgpa'] !== null && (float) $s['cgpa'] < $minCgpa;
?>
<div class="page-head">
    <div><h1><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h1>
        <p class="lede"><?= e($s['admission_number']) ?> &middot; <?= e($s['program_name']) ?> &middot; Year <?= (int) $s['year_of_study'] ?> &middot; advisor <?= e((string) ($s['advisor_name'] ?? 'not assigned')) ?></p></div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/students/' . $s['id'] . '/degree-audit') ?>">Degree audit</a>
        <a class="btn btn-sm btn-light" href="<?= url('/advising/mine') ?>">&larr; My advisees</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b class="<?= $low ? 'text-danger' : '' ?>"><?= $s['cgpa'] !== null ? number_format((float) $s['cgpa'], 2) : '&ndash;' ?></b><span>CGPA<?= $low ? ' - on probation' : '' ?></span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $s['last_gpa'] !== null ? number_format((float) $s['last_gpa'], 2) : '&ndash;' ?></b><span>Last semester GPA</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= e((string) ($s['enrolment'] ? humanize($s['enrolment']) : 'Not registered')) ?></b><span>This semester</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= e((string) $s['phone']) ?: '&ndash;' ?></b><span><?= e((string) $s['email']) ?></span></div></div>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('clipboard') ?> Advising notes</div>
            <div class="card-body py-1">
                <?php if ($notes === []): ?><p class="text-muted-sm py-3 mb-0">No advising meetings recorded yet.</p><?php endif; ?>
                <?php foreach ($notes as $n): ?>
                    <div class="py-2 border-bottom">
                        <div class="d-flex justify-content-between"><b><?= fdate($n['meeting_date']) ?></b><span class="text-muted-sm"><?= e((string) $n['by_name']) ?> &middot; <?= (int) $n['visible_to_student'] ? 'shared with student' : 'staff only' ?></span></div>
                        <div style="white-space:pre-wrap"><?= e($n['note']) ?></div>
                        <?php if ($n['follow_up_on']): ?><div class="text-muted-sm">Follow up on <?= fdate($n['follow_up_on']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><?= icon('plus') ?> Record a meeting</div>
            <div class="card-body">
                <form method="post" action="<?= url('/advising/students/' . $s['id'] . '/notes') ?>"><?= csrf_field() ?>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Meeting date</label><input class="form-control" type="date" name="meeting_date" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-6"><label class="form-label">Follow up on</label><input class="form-control" type="date" name="follow_up_on"></div>
                    </div>
                    <textarea class="form-control mb-2" name="note" rows="4" required placeholder="What was discussed and agreed: course choices, study plan, concerns, referrals..."></textarea>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="visible_to_student" value="1" id="vis" checked><label class="form-check-label" for="vis">Share this note with the student</label></div>
                    <button class="btn btn-primary">Save note</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><?= icon('chart') ?> Semester record</div>
            <div class="table-wrap"><table class="table table-sm mb-0">
                <thead><tr><th>Semester</th><th class="text-center">Credits</th><th class="text-end">GPA</th></tr></thead>
                <tbody>
                <?php if ($terms === []): ?><tr><td colspan="3" class="text-muted-sm p-3">No published results yet.</td></tr><?php endif; ?>
                <?php foreach ($terms as $t): ?>
                    <tr><td><?= e($t['term']) ?><?= (int) $t['failed'] ? ' <span class="badge text-bg-danger">' . (int) $t['failed'] . ' failed</span>' : '' ?></td>
                        <td class="text-center"><?= (int) $t['credits'] ?></td><td class="text-end fw-semibold <?= (float) $t['gpa'] < $minCgpa ? 'text-danger' : '' ?>"><?= number_format((float) $t['gpa'], 2) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php if ($canManage): ?>
            <div class="card"><div class="card-header">Advisor</div><div class="card-body">
                <form method="post" action="<?= url('/advising/students/' . $s['id'] . '/advisor') ?>" class="d-flex gap-2"><?= csrf_field() ?>
                    <select class="form-select" name="advisor_id"><?= options($staff, $s['advisor_id'], 'None') ?></select><button class="btn btn-primary">Save</button></form>
            </div></div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
