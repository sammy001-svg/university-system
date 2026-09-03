<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Clearance: <?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h1>
        <p class="lede"><?= e($profile['admission_number']) ?> &middot; <?= e($profile['program_name']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/services/clearance') ?>">&larr; Back to Clearance</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('clearance') ?> Departmental Sign-offs</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Signed Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('clearance') ?><p>No departmental records.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(humanize($item['department'])) ?></td>
                            <td><?= status_badge($item['status']) ?></td>
                            <td class="text-muted-sm"><?= date_fmt($item['signed_at'] ?? '') ?></td>
                            <td class="text-muted-sm"><?= e($item['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (can('clearance.edit')): ?>
        <div class="card">
            <div class="card-header"><?= icon('edit') ?> Update Department Clearance</div>
            <form method="post" action="<?= url('/services/clearance/' . $profile['id']) ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department" class="form-select" required>
                            <?= enum_options(['library','finance','hostel','sports','academic','security','dean_of_students'], old('department', 'finance')) ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <?= enum_options(['pending','cleared','flagged'], old('status', 'cleared')) ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Notes or reasons if flagged"></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Sign Off Clearance</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
