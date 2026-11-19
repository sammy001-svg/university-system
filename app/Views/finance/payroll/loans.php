<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/finance/payroll') ?>">&larr; Payroll</a></nav>
        <h1>Staff loans, advances and penalties</h1>
        <p class="lede">Each one is taken off the payslip a month at a time until the balance clears. What is left shows
            on the register as the outstanding balance.</p>
    </div>
</div>

<?php if (can('payroll.edit')): ?>
<div class="card mb-4">
    <div class="card-header"><?= icon('plus') ?> Record a loan, advance or penalty</div>
    <form method="post" action="<?= url('/finance/payroll/loans') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Staff member <span class="text-danger">*</span></label>
                    <select name="staff_id" class="form-select <?= has_error('staff_id') ? 'is-invalid' : '' ?>" required>
                        <?= options(array_column($staff, 'label', 'id'), old('staff_id'), 'Choose a staff member') ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error_for('staff_id')) ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kind <span class="text-danger">*</span></label>
                    <select name="kind" class="form-select" required>
                        <?= options(['loan' => 'Loan', 'salary_advance' => 'Salary advance', 'penalty' => 'Penalty'], old('kind', 'loan')) ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Amount owed <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="principal" class="form-control <?= has_error('principal') ? 'is-invalid' : '' ?>"
                           value="<?= e(old('principal')) ?>" required>
                    <div class="invalid-feedback"><?= e(error_for('principal')) ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Monthly deduction <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="monthly_deduction" class="form-control <?= has_error('monthly_deduction') ? 'is-invalid' : '' ?>"
                           value="<?= e(old('monthly_deduction')) ?>" required>
                    <div class="invalid-feedback"><?= e(error_for('monthly_deduction')) ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">First deducted</label>
                    <input type="date" name="first_deduction_on" class="form-control" value="<?= e(old('first_deduction_on')) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" class="form-control" maxlength="255" value="<?= e(old('reason')) ?>">
                </div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Record it</button></div>
    </form>
</div>
<?php endif; ?>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Show</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <?= options(['active' => 'Being repaid', 'settled' => 'Settled', 'cancelled' => 'Cancelled', 'all' => 'Everything'], $status) ?>
            </select>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2"><?= icon('money') ?> Loans
        <span class="badge text-bg-light"><?= count($loans) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Reference</th><th>Staff member</th><th>Kind</th><th>Reason</th>
                <th class="text-end">Owed</th><th class="text-end">Monthly</th><th class="text-end">Repaid</th>
                <th class="text-end">Balance</th><th>Status</th><th class="text-end">&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($loans === []): ?>
                <tr><td colspan="10"><div class="empty-state"><?= icon('check') ?><p>Nothing to show.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td><code><?= e($l['reference']) ?></code>
                        <div class="text-muted-sm"><?= $l['issued_on'] ? date_fmt($l['issued_on']) : '' ?></div></td>
                    <td class="fw-semibold"><?= e($l['last_name'] . ', ' . $l['first_name']) ?>
                        <div class="text-muted-sm"><?= e($l['staff_number']) ?></div></td>
                    <td><?= e(humanize((string) $l['kind'])) ?></td>
                    <td class="text-muted-sm"><?= e(str_limit((string) ($l['reason'] ?? ''), 48)) ?></td>
                    <td class="text-end"><?= money($l['principal']) ?></td>
                    <td class="text-end"><?= money($l['monthly_deduction']) ?></td>
                    <td class="text-end"><?= money($l['repaid']) ?></td>
                    <td class="text-end fw-semibold"><?= money($l['balance']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="text-end">
                        <?php if ($l['status'] === 'active' && can('payroll.edit')): ?>
                            <form method="post" action="<?= url('/finance/payroll/loans/' . $l['id'] . '/cancel') ?>" class="d-inline"
                                  data-confirm="Cancel <?= e($l['reference']) ?>? Nothing further is deducted and the remaining <?= e(money($l['balance'])) ?> is written off.">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-light text-danger">Cancel</button>
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
