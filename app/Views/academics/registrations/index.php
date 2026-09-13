<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Course Registrations</h1>
        <p class="lede">Student course registrations &amp; approval workflow.</p>
    </div>
</div>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Approval Status</label>
            <select name="approval_status" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['pending', 'approved', 'rejected'], $_GET['approval_status'] ?? '', 'All Statuses') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header"><?= icon('registrations') ?> Course Registrations (<?= count($registrations) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($registrations)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('registrations') ?><p>No registrations found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($registrations as $r): ?>
                <tr>
                    <td><code><?= e($r['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="text-muted-sm"><?= e($r['semester_name']) ?></td>
                    <td><?= status_badge($r['approval_status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['approval_status'] === 'pending' && can('registrations.approve')): ?>
                            <form method="post" action="<?= url('/academics/registrations/' . $r['id'] . '/approve') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" action="<?= url('/academics/registrations/' . $r['id'] . '/reject') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
