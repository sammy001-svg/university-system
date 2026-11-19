<?php
/** The payroll register, in the columns of the university's own payroll sheet. */
layout('layouts.app');
section('content');
$editable = !in_array($period['status'], ['paid', 'closed'], true);
$n        = static fn (mixed $v): string => number_format((float) $v, 2);
?>
<style>
    .register { font-size: .82rem; }
    .register th, .register td { white-space: nowrap; padding: .35rem .5rem; }
    .register thead th { vertical-align: bottom; }
    .register thead tr.group th { text-align: center; background: #f3eef4; }
    .register tfoot td { font-weight: 700; border-top: 2px solid #999; }
    .register .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/finance/payroll') ?>">&larr; Payroll</a></nav>
        <h1><?= e($period['period_name']) ?></h1>
        <p class="lede">Pay date <?= date_fmt($period['pay_date'] ?? '') ?> &middot; <?= status_badge($period['status']) ?>
            &middot; <?= count($rows) ?> staff on this run<?= $period['notes'] ? ' &middot; ' . e($period['notes']) : '' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('payroll.export') && $rows !== []): ?>
            <a class="btn btn-sm btn-light btn-icon" href="<?= url('/finance/payroll/' . $period['id'] . '/export') ?>">
                <?= icon('download', 'ico-sm') ?> Export</a>
        <?php endif; ?>
        <?php if ($editable && can('payroll.create')): ?>
            <form method="post" action="<?= url('/finance/payroll/' . $period['id'] . '/process') ?>"
                  data-confirm="<?= $rows === [] ? 'Run the payroll for every active staff member?' : 'Rebuild this run from the current salaries, allowances and loans?' ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary btn-icon"><?= icon('play', 'ico-sm') ?>
                    <?= $rows === [] ? 'Run payroll' : 'Re-run' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($rows !== [] && $period['status'] !== 'approved' && $editable && can('payroll.approve')): ?>
            <form method="post" action="<?= url('/finance/payroll/' . $period['id'] . '/approve') ?>"
                  data-confirm="Approve this payroll? The figures are then fixed unless it is re-run.">
                <?= csrf_field() ?><button class="btn btn-sm btn-success btn-icon"><?= icon('check', 'ico-sm') ?> Approve</button>
            </form>
        <?php endif; ?>
        <?php if ($period['status'] === 'approved' && can('payroll.pay')): ?>
            <form method="post" action="<?= url('/finance/payroll/' . $period['id'] . '/pay') ?>"
                  data-confirm="Mark this payroll as paid? Payslips can no longer be changed.">
                <?= csrf_field() ?><button class="btn btn-sm btn-success btn-icon"><?= icon('money', 'ico-sm') ?> Mark paid</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($rows === []): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('money') ?>
        <p>This period has not been run yet. Running it builds a payslip for every active staff member
           from their salary, allowances and any loan being repaid.</p></div></div></div>
<?php else: ?>

<div class="row g-3 mb-3">
    <?php foreach ([['Gross income', $totals['gross_pay'], 'money'], ['Total deductions', $totals['total_deductions'], 'chart'],
                    ['Net salary', $totals['net_pay'], 'check'], ['Outstanding loans', $totals['outstanding_balance'], 'clipboard']] as [$label, $value, $ico]): ?>
        <div class="col-md-3">
            <div class="stat"><div><?= icon($ico) ?></div>
                <div><div class="text-muted-sm"><?= e($label) ?></div><div class="h5 mb-0"><?= money($value) ?></div></div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2"><?= icon('users') ?> Payroll register</div>
    <div class="table-wrap">
        <table class="table table-sm table-hover table-bordered align-middle mb-0 register">
            <thead>
            <tr class="group">
                <th colspan="10">&nbsp;</th>
                <th colspan="3">Earnings</th>
                <th colspan="5">Deductions</th>
                <th colspan="3">&nbsp;</th>
            </tr>
            <tr>
                <th>Sr No</th><th>FirstName</th><th>Middle Name</th><th>LastName</th><th>Employee ID</th>
                <th>Gender</th><th>Phone Number</th><th>DoB</th><th>Position</th><th>Department</th>
                <th class="num">Basic Salary</th><th class="num">Allowances</th><th class="num">Gross Income</th>
                <th class="num">Loan/ Penalty</th><th class="num">Taxable amount</th><th class="num">Tax Deduction</th>
                <th class="num">Others</th><th class="num">Total Deductions</th>
                <th class="num">Outstanding Balance</th><th class="num">Net Salary</th><th>Payroll Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $r): $loan = (float) $r['loan_deduction'] + (float) $r['penalty']; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['first_name']) ?></td>
                    <td><?= e((string) ($r['other_name'] ?? '')) ?></td>
                    <td class="fw-semibold"><a href="<?= url('/finance/payroll/payslip/' . $r['id']) ?>"><?= e($r['last_name']) ?></a></td>
                    <td><code><?= e($r['staff_number']) ?></code></td>
                    <td><?= e($r['gender'] ? strtoupper(substr((string) $r['gender'], 0, 1)) : '') ?></td>
                    <td><?= e((string) ($r['phone'] ?? '')) ?></td>
                    <td><?= $r['date_of_birth'] ? date_fmt($r['date_of_birth']) : '' ?></td>
                    <td><?= e(str_limit((string) ($r['designation'] ?? ''), 34)) ?></td>
                    <td><?= e(str_limit((string) ($r['department_name'] ?? ''), 30)) ?></td>
                    <td class="num"><?= $n($r['basic_salary']) ?></td>
                    <td class="num"><?= $n($r['allowances']) ?></td>
                    <td class="num fw-semibold"><?= $n($r['gross_pay']) ?></td>
                    <td class="num"><?= $loan > 0 ? $n($loan) : '' ?></td>
                    <td class="num"><?= $n($r['taxable_amount']) ?></td>
                    <td class="num"><?= $n($r['tax_deduction']) ?></td>
                    <td class="num"><?= $n($r['other_deductions']) ?></td>
                    <td class="num"><?= $n($r['total_deductions']) ?></td>
                    <td class="num"><?= (float) $r['outstanding_balance'] > 0 ? $n($r['outstanding_balance']) : '' ?></td>
                    <td class="num fw-bold"><?= $n($r['net_pay']) ?></td>
                    <td><?= date_fmt($period['pay_date'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="10" class="text-end">TOTAL</td>
                <td class="num"><?= $n($totals['basic_salary']) ?></td>
                <td class="num"><?= $n($totals['allowances']) ?></td>
                <td class="num"><?= $n($totals['gross_pay']) ?></td>
                <td class="num"><?= $n($totals['loan_deduction'] + $totals['penalty']) ?></td>
                <td class="num"><?= $n($totals['taxable_amount']) ?></td>
                <td class="num"><?= $n($totals['tax_deduction']) ?></td>
                <td class="num"><?= $n($totals['other_deductions']) ?></td>
                <td class="num"><?= $n($totals['total_deductions']) ?></td>
                <td class="num"><?= $n($totals['outstanding_balance']) ?></td>
                <td class="num"><?= $n($totals['net_pay']) ?></td>
                <td>&nbsp;</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endsection(); ?>
