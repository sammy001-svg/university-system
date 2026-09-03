<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Mark Attendance</h1>
        <p class="lede">Date: <?= date_fmt($session['session_date']) ?> &middot; Topic: <?= e($session['topic'] ?? 'General Session') ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/attendance') ?>">&larr; Back to Sessions</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/attendance/sessions/' . $session['id']) ?>">
        <?= csrf_field() ?>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= icon('attendance') ?> Student Register</span>
            <span class="text-muted-sm"><?= count($students) ?> Students Enrolled</span>
        </div>
        <div class="table-wrap">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="3"><div class="empty-state"><?= icon('student') ?><p>No registered students found.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><code><?= e($s['admission_number']) ?></code></td>
                        <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td class="text-center">
                            <select name="attendance[<?= $s['student_id'] ?>]" class="form-select form-select-sm d-inline-block w-auto">
                                <?= enum_options(['present','absent','late','excused'], $s['att_status'] ?? 'present') ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save Attendance</button>
        </div>
    </form>
</div>
<?php endsection(); ?>
