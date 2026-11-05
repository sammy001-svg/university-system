<?php
layout('layouts.app');
section('content');
$atRisk = count(array_filter($rows, static fn ($r) => $r['cgpa'] !== null && (float) $r['cgpa'] < $minCgpa));
?>
<div class="page-head"><div><h1>My Advisees</h1><p class="lede"><?= count($rows) ?> student(s)<?= $atRisk ? ', <strong class="text-danger">' . $atRisk . ' on probation</strong>' : '' ?>.
    Open a student to see their record and add advising notes.</p></div></div>
<div class="card">
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Student</th><th>Programme</th><th class="text-end">CGPA</th><th class="text-end">Last semester</th><th>This semester</th><th>Last met</th><th></th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="7"><div class="empty-state"><?= icon('users') ?><p>You have no advisees yet.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): $low = $r['cgpa'] !== null && (float) $r['cgpa'] < $minCgpa; ?>
            <tr>
                <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div><div class="text-muted-sm"><?= e($r['admission_number']) ?> &middot; Year <?= (int) $r['year_of_study'] ?></div></td>
                <td class="small"><?= e($r['program_name']) ?></td>
                <td class="text-end fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= $r['cgpa'] !== null ? number_format((float) $r['cgpa'], 2) : '&ndash;' ?><?= $low ? '<div class="small">probation</div>' : '' ?></td>
                <td class="text-end"><?= $r['last_gpa'] !== null ? number_format((float) $r['last_gpa'], 2) : '&ndash;' ?></td>
                <td><?= $r['enrolment'] === 'enrolled' ? '<span class="badge text-bg-success">Enrolled</span>' : ($r['enrolment'] ? '<span class="badge text-bg-warning">Payment due</span>' : '<span class="badge text-bg-light">Not registered</span>') ?></td>
                <td class="text-muted-sm"><?= $r['last_meeting'] ? fdate($r['last_meeting']) : 'Never' ?></td>
                <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/advising/students/' . $r['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
