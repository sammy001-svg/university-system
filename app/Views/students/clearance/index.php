<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Student Clearance</h1>
        <p class="lede">Institutional clearance &amp; department sign-offs for graduating/leaving students.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('clearance') ?> Student Clearance Register (<?= count($students) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Programme</th>
                    <th>Cleared Progress</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('clearance') ?><p>No students listed for clearance.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= e($s['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= e($s['program_name']) ?></td>
                    <td>
                        <span class="badge text-bg-info"><?= (int)$s['cleared_count'] ?> / <?= (int)($s['total_items'] ?? 5) ?> Cleared</span>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/services/clearance/' . $s['id']) ?>"><?= icon('eye', 'ico-sm') ?> Manage Clearance</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
