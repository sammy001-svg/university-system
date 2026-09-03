<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Leave Requests</h1>
        <p class="lede">Staff leave applications &amp; HR approvals.</p>
    </div>
    <?php if (can('hr.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/hr/leave/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Apply for Leave
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('hr') ?> Leave Applications (<?= count($leaves) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Staff No.</th>
                    <th>Staff Name</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($leaves)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('hr') ?><p>No leave requests found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><code><?= e($l['staff_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></td>
                    <td><?= e($l['leave_type_name']) ?></td>
                    <td><?= date_fmt($l['start_date']) ?></td>
                    <td><?= date_fmt($l['end_date']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($l['status'] === 'pending' && can('hr.manage')): ?>
                            <form method="post" action="<?= url('/hr/leave/' . $l['id'] . '/decide') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="approved">
                                <button class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" action="<?= url('/hr/leave/' . $l['id'] . '/decide') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="rejected">
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
