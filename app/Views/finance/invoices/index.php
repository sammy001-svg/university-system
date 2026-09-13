<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Invoices</h1>
        <p class="lede">Student fee billing invoices.</p>
    </div>
    <?php if (can('finance.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/finance/invoices/generate') ?>">
            <?= icon('plus', 'ico-sm') ?> Generate Invoices
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('finance') ?> Invoices Register (<?= count($invoices) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($invoices)): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('finance') ?><p>No invoices generated.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><code><?= e($inv['invoice_number']) ?></code></td>
                    <td><code><?= e($inv['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($inv['first_name'] . ' ' . $inv['last_name']) ?></td>
                    <td class="text-end"><?= number_format((float)$inv['total_amount'], 2) ?></td>
                    <td class="text-end text-success"><?= number_format((float)$inv['amount_paid'], 2) ?></td>
                    <td class="text-end <?= (float)$inv['balance'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= number_format((float)$inv['balance'], 2) ?></td>
                    <td class="text-muted-sm"><?= date_fmt($inv['due_date'] ?? '') ?></td>
                    <td><?= status_badge($inv['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/finance/invoices/' . $inv['id']) ?>"><?= icon('eye', 'ico-sm') ?> View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
