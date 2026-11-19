<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/finance/payroll') ?>">&larr; Payroll</a></nav>
        <h1>Staff salaries</h1>
        <p class="lede">What each staff member is paid, and the allowances and deductions on their payslip.
            A payroll run reads these figures, so set them before running the month.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>"
                   placeholder="Name, employee ID or position">
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Search</button></div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2"><?= icon('users') ?> Staff on the payroll
        <span class="badge text-bg-light"><?= count($staff) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Employee ID</th><th>Name</th><th>Position</th><th>Department</th>
                <th class="text-end">Basic salary</th><th class="text-end">Fixed allowances</th>
                <th class="text-end">Loan balance</th><th class="text-end">&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($staff === []): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('users') ?><p>No staff match.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($staff as $s): ?>
                <tr>
                    <td><code><?= e($s['staff_number']) ?></code></td>
                    <td class="fw-semibold"><a href="<?= url('/finance/payroll/salaries/' . $s['id']) ?>">
                        <?= e(trim($s['last_name'] . ', ' . $s['first_name'] . ' ' . ($s['other_name'] ?? ''))) ?></a>
                        <?php if ((int) $s['components'] > 0): ?>
                            <span class="text-muted-sm"><?= (int) $s['components'] ?> component<?= (int) $s['components'] === 1 ? '' : 's' ?></span>
                        <?php endif; ?></td>
                    <td class="text-muted-sm"><?= e((string) ($s['designation'] ?? '')) ?></td>
                    <td class="text-muted-sm"><?= e((string) ($s['department_name'] ?? '')) ?></td>
                    <td class="text-end <?= (float) $s['basic_salary'] <= 0 ? 'text-danger fw-semibold' : '' ?>">
                        <?= (float) $s['basic_salary'] > 0 ? money($s['basic_salary']) : 'Not set' ?></td>
                    <td class="text-end"><?= (float) $s['fixed_allowances'] > 0 ? money($s['fixed_allowances']) : '&mdash;' ?></td>
                    <td class="text-end"><?= (float) $s['loan_balance'] > 0 ? money($s['loan_balance']) : '&mdash;' ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/finance/payroll/salaries/' . $s['id']) ?>">
                            <?= icon('edit', 'ico-sm') ?> <?= can('payroll.edit') ? 'Edit' : 'View' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
