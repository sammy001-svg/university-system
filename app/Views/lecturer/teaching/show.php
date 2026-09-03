<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1><?= e($offering['code']) ?> – <?= e($offering['title']) ?></h1>
        <p class="lede">Section <?= e($offering['section']) ?> &middot; <?= e($offering['semester_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching') ?>">&larr; My Teaching</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/teaching/' . $offering['id'] . '/marks') ?>"><?= icon('edit','ico-sm') ?> Marks</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/' . $offering['id'] . '/assessments') ?>"><?= icon('assessment','ico-sm') ?> Assessments</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('student') ?> Class Roster <span class="badge text-bg-light"><?= count($students) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Admission No.</th><th>Name</th><th>Email</th><th class="text-end">Score</th><th>Grade</th><th>Outcome</th></tr></thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="6"><div class="empty-state"><?= icon('student') ?><p>No students registered.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= e($s['admission_number']) ?></code></td>
                    <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td class="text-muted-sm"><?= e($s['email']) ?></td>
                    <td class="text-end"><?= $s['total_score'] !== null ? number_format((float)$s['total_score'],1) : '—' ?></td>
                    <td><?= e($s['grade'] ?? '—') ?></td>
                    <td><?= $s['outcome'] ? status_badge($s['outcome']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
