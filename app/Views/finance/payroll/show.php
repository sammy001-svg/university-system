<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Payroll Period: <?= e($period['period_name'] ?? ($period['month'] . '/' . $period['year'])) ?></h1>
        <p class="lede">Pay Date: <?= date_fmt($period['pay_date'] ?? '') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= url('/payroll') ?>">&larr; Back to Payroll</a>
        <?php if ($period['status'] === 'draft' && can('payroll.create')): ?>
            <form method="post" action="<?= url('/payroll/' . $period['id'] . '/process') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary">Process Payroll &amp; Generate Payslips</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('staff') ?> Staff Payslips (<?= count($payslips) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Staff No.</th>
                    <th>Staff Name</th>
                    <th class="text-end">Basic Salary</th>
                    <th class="text-end">Gross Pay</th>
                    <th class="text-end">Net Pay</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payslips)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('staff') ?><p>No payslips generated for this period.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($payslips as $ps): ?>
                <tr>
                    <td><code><?= e($ps['staff_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($ps['first_name'] . ' ' . $ps['last_name']) ?></td>
                    <td class="text-end"><?= number_format((float)$ps['basic_salary'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)($ps['gross_pay'] ?? $ps['basic_salary']), 2) ?></td>
                    <td class="text-end fw-bold text-success"><?= number_format((float)($ps['net_pay'] ?? $ps['basic_salary']), 2) ?></td>
                    <td><?= status_badge($ps['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/payroll/payslip/' . $ps['id']) ?>"><?= icon('file', 'ico-sm') ?> Payslip</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
