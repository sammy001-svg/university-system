<?php
layout('layouts.app');
section('content');
$s      = $staff;
$name   = trim($s['first_name'] . ' ' . ($s['other_name'] ?? '') . ' ' . $s['last_name']);
$locked = !can('payroll.edit');
$unit   = static fn (string $basis): string => match ($basis) {
    'percent_basic' => '% of basic',
    'percent_gross' => '% of gross',
    default         => 'amount',
};
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/finance/payroll/salaries') ?>">&larr; Staff salaries</a></nav>
        <h1><?= e($name) ?></h1>
        <p class="lede"><?= e($s['staff_number']) ?> &middot; <?= e((string) ($s['designation'] ?? 'No position on record')) ?>
            <?= $s['department_name'] ? ' &middot; ' . e($s['department_name']) : '' ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/staff/' . $s['id']) ?>">Staff record</a>
</div>

<form method="post" action="<?= url('/finance/payroll/salaries/' . $s['id']) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><?= icon('money') ?> Pay and bank details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Basic salary per month <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="basic_salary" class="form-control <?= has_error('basic_salary') ? 'is-invalid' : '' ?>"
                                   value="<?= e(old('basic_salary', $s['basic_salary'])) ?>" <?= $locked ? 'disabled' : 'required' ?>>
                            <div class="invalid-feedback"><?= e(error_for('basic_salary')) ?></div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Grade</label>
                            <input type="text" name="salary_grade" class="form-control" maxlength="20"
                                   value="<?= e(old('salary_grade', $s['salary_grade'] ?? '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-7">
                            <label class="form-label">Bank</label>
                            <input type="text" name="bank_name" class="form-control" maxlength="120"
                                   value="<?= e(old('bank_name', $s['bank_name'] ?? '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Account number</label>
                            <input type="text" name="bank_account" class="form-control" maxlength="60"
                                   value="<?= e(old('bank_account', $s['bank_account'] ?? '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Taxpayer number</label>
                            <input type="text" name="tax_pin" class="form-control" maxlength="40"
                                   value="<?= e(old('tax_pin', $s['tax_pin'] ?? '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex align-items-center gap-2"><?= icon('clipboard') ?> Loans and advances
                    <a class="ms-auto btn btn-sm btn-light" href="<?= url('/finance/payroll/loans') ?>">All loans</a></div>
                <div class="table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Reference</th><th>Kind</th><th class="text-end">Monthly</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($loans === []): ?>
                            <tr><td colspan="5" class="text-muted-sm p-3">Nothing owed.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($loans as $l): ?>
                            <tr>
                                <td><code><?= e($l['reference']) ?></code></td>
                                <td><?= e(humanize((string) $l['kind'])) ?></td>
                                <td class="text-end"><?= money($l['monthly_deduction']) ?></td>
                                <td class="text-end fw-semibold"><?= money($l['balance']) ?></td>
                                <td><?= status_badge($l['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><?= icon('chart') ?> Allowances and deductions</div>
                <div class="card-body">
                    <p class="text-muted-sm">Fill in an amount to put a component on this person's payslip; leave it empty to take it off.
                        A component marked "everyone" applies even when it is empty here, at its own default.</p>
                    <?php foreach (['allowance' => 'Allowances', 'deduction' => 'Deductions'] as $kind => $heading): ?>
                        <h6 class="mt-3"><?= e($heading) ?></h6>
                        <div class="row g-2">
                            <?php $any = false; foreach ($components as $c): if ($c['kind'] !== $kind) { continue; } $any = true; ?>
                                <div class="col-md-6">
                                    <label class="form-label mb-1"><?= e($c['name']) ?>
                                        <span class="text-muted-sm">(<?= e($unit((string) $c['basis'])) ?><?php
                                            if ((int) $c['applies_to_all'] === 1) { echo ', everyone'; }
                                            if ((int) $c['taxable'] === 0 && $kind === 'allowance') { echo ', not taxed'; }
                                        ?>)</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="component[<?= (int) $c['id'] ?>]"
                                           value="<?= e((string) ($assigned[$c['id']] ?? '')) ?>"
                                           placeholder="<?= e(number_format((float) $c['amount'], 2)) ?>" <?= $locked ? 'disabled' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$any): ?><div class="col-12 text-muted-sm">None set up yet.</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$locked): ?>
                    <div class="card-footer d-flex gap-2">
                        <button class="btn btn-primary">Save salary</button>
                        <a class="btn btn-light" href="<?= url('/finance/payroll/salaries') ?>">Cancel</a>
                        <a class="btn btn-light ms-auto" href="<?= url('/finance/salary-components') ?>">Manage components</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
<?php endsection(); ?>
