<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Portal</h1>
        <p class="lede">Welcome, <?= e($student['first_name']) ?>. Here's your academic summary.</p>
    </div>
</div>

<?php if ($semester): ?>
    <?php if (($enrollment['status'] ?? null) === 'enrolled'): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
            <?= icon('check') ?>
            <div><strong>Registration Complete</strong> &middot; Enrolled for <?= e(($semester['academic_year'] ?? '') . ' ' . $semester['name']) ?>.</div>
        </div>
    <?php elseif ($enrollment !== null): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <?= icon('clock') ?>
            <div><strong>Not yet enrolled for <?= e($semester['name']) ?>.</strong>
                Pay your compulsory 1st installment (<?= e(money($enrollment['first_outstanding'])) ?> outstanding) to enrol and register for courses.
                <a href="<?= url('/portal/finance/invoice/' . $enrollment['invoice_id']) ?>">View invoice</a></div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No fee invoice has been issued for <?= e($semester['name']) ?> yet.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php $tiles = [
        ['Balance Due', money($balance), 'finance', $balance > 0 ? 'tone-red' : 'tone-green'],
        ['Registered Units', count($registrations), 'course', ''],
        ['Attendance', $attendance . '%', 'attendance', $attendance < 75 ? 'tone-amber' : 'tone-green'],
        ['Year of Study', 'Year ' . $student['year_of_study'], 'student', ''],
    ];
    foreach ($tiles as [$label, $value, $iconName, $tone]): ?>
        <div class="col-6 col-xl-3">
            <div class="stat <?= $tone ?>">
                <span class="stat-icon"><?= icon($iconName, 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= e($value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('course') ?> Current Registrations</div>
            <?php if (empty($registrations)): ?>
                <div class="card-body"><div class="empty-state"><?= icon('course') ?><p>No courses registered this semester.</p></div></div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th>Lecturer</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($registrations as $r): ?>
                        <tr>
                            <td><code><?= e($r['code']) ?></code></td>
                            <td><?= e($r['title']) ?></td>
                            <td class="text-center"><?= (int)$r['credit_hours'] ?></td>
                            <td class="text-muted-sm"><?= e($r['lecturer_name'] ?? '—') ?></td>
                            <td><?= status_badge($r['approval_status'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="card-footer">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('/portal/registration') ?>">Manage Registration</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= icon('announcement') ?> Announcements</div>
            <ul class="list-group list-group-flush">
                <?php if (empty($announcements)): ?>
                    <li class="list-group-item text-muted-sm">No announcements.</li>
                <?php endif; ?>
                <?php foreach ($announcements as $a): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold text-sm"><?= e($a['title']) ?></div>
                        <div class="text-muted-sm"><?= date_fmt($a['published_at'] ?? '') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card mt-3">
            <div class="card-header"><?= icon('link') ?> Quick Links</div>
            <div class="list-group list-group-flush">
                <a href="<?= url('/portal/results') ?>" class="list-group-item list-group-item-action"><?= icon('file','ico-sm') ?> My Results</a>
                <a href="<?= url('/portal/finance') ?>" class="list-group-item list-group-item-action"><?= icon('finance','ico-sm') ?> Fee Statement</a>
                <a href="<?= url('/timetable') ?>" class="list-group-item list-group-item-action"><?= icon('clock','ico-sm') ?> Timetable</a>
                <a href="<?= url('/portal/transcript') ?>" class="list-group-item list-group-item-action"><?= icon('file','ico-sm') ?> Transcript</a>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
