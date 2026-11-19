<?php layout('layouts.app'); $pageTitle = 'Payroll'; section('content'); ?>
<div class="page-head">
    <div>
        <h1>Payroll</h1>
        <p class="lede">One run a month: open the period, run it, check the register, approve it, then pay.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light btn-icon" href="<?= url('/finance/payroll/salaries') ?>"><?= icon('users', 'ico-sm') ?> Salaries</a>
        <a class="btn btn-sm btn-light btn-icon" href="<?= url('/finance/payroll/loans') ?>"><?= icon('money', 'ico-sm') ?> Loans &amp; advances</a>
        <?php if (can('payroll.edit')): ?>
            <a class="btn btn-sm btn-light btn-icon" href="<?= url('/finance/salary-components') ?>"><?= icon('clipboard', 'ico-sm') ?> Allowances</a>
            <a class="btn btn-sm btn-light btn-icon" href="<?= url('/finance/tax-bands') ?>"><?= icon('chart', 'ico-sm') ?> Tax bands</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($noSalary > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <?= icon('bell') ?>
        <div><strong><?= (int) $noSalary ?></strong> of the <?= (int) $onPayroll ?> active staff have no basic salary on record, so they
            would be paid nothing. <a href="<?= url('/finance/payroll/salaries') ?>">Set their salaries</a> before running the payroll.</div>
    </div>
<?php endif; ?>

<?php if (can('payroll.create')): ?>
<div class="card mb-4">
    <div class="card-header"><?= icon('plus') ?> Open a payroll period</div>
    <form method="post" action="<?= url('/finance/payroll') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Month <span class="text-danger">*</span></label>
                    <select name="month" class="form-select" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (int) date('n') === $m ? 'selected' : '' ?>><?= date('F', (int) mktime(0, 0, 0, $m, 10)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pay date</label>
                    <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-t') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Note</label>
                    <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Optional">
                </div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Open period</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2"><?= icon('money') ?> Payroll periods
        <span class="badge text-bg-light"><?= count($periods) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Period</th><th>Pay date</th><th class="text-center">Staff</th>
                <th class="text-end">Gross</th><th class="text-end">Net</th><th>Status</th><th class="text-end">&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($periods === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('money') ?><p>No payroll period yet. Open one above.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td class="fw-semibold"><a href="<?= url('/finance/payroll/' . $p['id']) ?>"><?= e($p['period_name']) ?></a>
                        <?php if (!empty($p['notes'])): ?><div class="text-muted-sm"><?= e($p['notes']) ?></div><?php endif; ?></td>
                    <td><?= date_fmt($p['pay_date'] ?? '') ?></td>
                    <td class="text-center"><?= (int) $p['payslips'] ?></td>
                    <td class="text-end"><?= money($p['gross']) ?></td>
                    <td class="text-end fw-semibold"><?= money($p['net']) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= url('/finance/payroll/' . $p['id']) ?>"><?= icon('eye', 'ico-sm') ?> Register</a>
                        <?php if (can('payroll.delete') && !in_array($p['status'], ['paid', 'closed'], true)): ?>
                            <form method="post" action="<?= url('/finance/payroll/' . $p['id']) ?>" class="d-inline"
                                  data-confirm="Delete <?= e($p['period_name']) ?>? Its payslips go too, and any loan repayments they took are given back.">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button>
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
