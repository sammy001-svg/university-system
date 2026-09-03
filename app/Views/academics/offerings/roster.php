<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Class Roster: <?= e($offering['code'] ?? '') ?></h1>
        <p class="lede">Section <?= e($offering['section'] ?? '1') ?> &middot; Enrolled Students</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/offerings') ?>">&larr; Back to Offerings</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('student') ?> Enrolled Students (<?= count($students) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Reg Type</th>
                    <th>Approval Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('student') ?><p>No students enrolled in this offering.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= e($s['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td class="text-muted-sm"><?= e($s['email']) ?></td>
                    <td><?= e(humanize($s['registration_type'] ?? 'normal')) ?></td>
                    <td><?= status_badge($s['approval_status'] ?? 'pending') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
