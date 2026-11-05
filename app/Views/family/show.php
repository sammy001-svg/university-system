<?php
layout('layouts.app');
section('content');
$s = $student;
$enrolled = ($enrollment['status'] ?? null) === 'enrolled';
?>
<section class="student-hero">
    <div class="student-avatar"><?php if (!empty($s['avatar'])): ?><img src="<?= e(uploaded($s['avatar'])) ?>" alt=""><?php else: ?><?= e(initials($s['first_name'], $s['last_name'])) ?><?php endif; ?></div>
    <div>
        <div class="dash-eyebrow">Family portal</div>
        <h1><?= e(trim($s['first_name'] . ' ' . ($s['other_name'] ?? '') . ' ' . $s['last_name'])) ?></h1>
        <div class="meta"><span>ID <b><?= e($s['admission_number']) ?></b></span><span><?= e($s['program_name']) ?></span><span><b><?= e($standing) ?></b></span></div>
    </div>
    <div class="hero-status">
        <?php if ($semester): ?><div class="mb-2" style="color:var(--sidebar-text); font-size:12.5px"><?= e((string) $semester['academic_year']) ?> &middot; <?= e($semester['name']) ?></div><?php endif; ?>
        <?php if ($enrolled): ?><span class="hero-pill ok"><?= icon('check', 'ico-sm') ?> Registration complete</span>
        <?php elseif ($enrollment !== null): ?><span class="hero-pill warn"><?= icon('clock', 'ico-sm') ?> 1st installment due</span>
        <?php else: ?><span class="hero-pill info">Not yet billed</span><?php endif; ?>
    </div>
</section>

<?php if (count($others) > 1): ?>
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach ($others as $o): ?>
            <a class="btn btn-sm <?= (int) $o['id'] === (int) $s['id'] ? 'btn-primary' : 'btn-light' ?>" href="<?= url('/family/' . $o['id']) ?>"><?= e($o['first_name']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($enrollment !== null && !$enrolled): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2"><?= icon('clock') ?>
        <div><strong><?= e($s['first_name']) ?>'s registration is not complete.</strong> The 1st installment of <?= e(money($enrollment['first_outstanding'])) ?> is outstanding.
            <a class="fw-semibold" href="<?= url('/pay/' . $s['id'] . '/' . $enrollment['invoice_id']) ?>">Pay online now</a></div></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi"><div class="kpi-head"><span>Cumulative GPA</span><?= icon('chart') ?></div>
        <div class="kpi-value"><?= $cgpa !== null ? number_format($cgpa, 2) : '&ndash;' ?></div><div class="kpi-sub"><?= $cgpa !== null ? e(classify_gpa($cgpa)) : 'No results published yet' ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="kpi-head"><span>This semester</span><?= icon('book') ?></div>
        <div class="kpi-value"><?= count($registrations) ?> <small>courses</small></div><div class="kpi-sub"><?= array_sum(array_map(static fn ($r) => (int) $r['credit_hours'], $registrations)) ?> credit hours</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="kpi-head"><span>Attendance</span><?= icon('check') ?></div>
        <div class="kpi-value"><?= e((string) $attendance) ?><small>%</small></div>
        <div class="meter <?= $attendance >= 75 ? 'ok' : ($attendance >= 50 ? 'warn' : 'bad') ?> mt-2"><span style="width:<?= min(100, (float) $attendance) ?>%"></span></div></div></div>
    <div class="col-6 col-xl-3"><a class="kpi" href="<?= url('/family/' . $s['id'] . '/fees') ?>"><div class="kpi-head"><span>Fee balance</span><?= icon('money') ?></div>
        <div class="kpi-value" style="color:<?= $balance > 0 ? 'var(--danger)' : 'var(--ok)' ?>"><?= e(money($balance)) ?></div><div class="kpi-sub">View fees and pay online &rarr;</div></a></div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header"><?= icon('book') ?> Courses this semester</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Course</th><th>Lecturer</th><th class="text-center">Credits</th></tr></thead>
                <tbody>
                <?php if ($registrations === []): ?><tr><td colspan="3"><div class="empty-state py-3"><p>No courses registered this semester.</p></div></td></tr><?php endif; ?>
                <?php foreach ($registrations as $r): ?>
                    <tr><td><b><?= e($r['code']) ?></b><div class="text-muted-sm"><?= e($r['title']) ?></div></td>
                        <td class="text-muted-sm"><?= e($r['lecturer_name'] ?? '') ?></td><td class="text-center"><?= (int) $r['credit_hours'] ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header"><?= icon('chart') ?> Published results</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Course</th><th>Term</th><th class="text-center">Grade</th></tr></thead>
                <tbody>
                <?php if ($results === []): ?><tr><td colspan="3"><div class="empty-state py-3"><p>No results published yet.</p></div></td></tr><?php endif; ?>
                <?php foreach (array_slice($results, 0, 20) as $r): ?>
                    <tr><td><b><?= e($r['code']) ?></b><div class="text-muted-sm"><?= e($r['title']) ?></div></td>
                        <td class="text-muted-sm"><?= e($r['academic_year'] . ' ' . $r['semester_name']) ?></td>
                        <td class="text-center"><span class="badge text-bg-<?= $r['outcome'] === 'pass' ? 'success' : 'danger' ?>"><?= e((string) $r['grade']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
</div>
<?php endsection(); ?>
