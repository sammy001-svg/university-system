<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Payroll</h1>
        <p class="lede">Staff monthly payroll processing and payslips.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><?= icon('plus') ?> Create New Payroll Period</div>
    <form method="post" action="<?= url('/payroll') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Month <span class="text-danger">*</span></label>
                    <select name="month" class="form-select" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (int)date('n') === $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pay Date</label>
                    <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-t') ?>">
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Create Payroll Period</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><?= icon('finance') ?> Payroll Periods (<?= count($periods) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Period Name</th>
                    <th>Month / Year</th>
                    <th>Pay Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($periods)): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('finance') ?><p>No payroll periods generated.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['period_name'] ?? ('Period #' . $p['id'])) ?></td>
                    <td><?= e($p['month']) ?> / <?= e($p['year']) ?></td>
                    <td><?= date_fmt($p['pay_date'] ?? '') ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/payroll/' . $p['id']) ?>"><?= icon('eye', 'ico-sm') ?> View Payroll</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
