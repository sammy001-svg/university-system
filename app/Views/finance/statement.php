<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Fee Statement</h1>
        <p class="lede"><?= e($student['first_name'] . ' ' . $student['last_name']) ?> (<?= e($student['admission_number']) ?>) &middot; <?= e($student['program_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('download', 'ico-sm') ?> Print Statement</button>
        <a class="btn btn-sm btn-light" href="<?= url('/students/' . $student['id']) ?>">&larr; Back to Student Profile</a>
    </div>
</div>

<div class="card p-3 mb-4 bg-light">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <div class="text-muted-sm">Current Balance Due</div>
            <div class="fs-3 fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($balance)) ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('finance') ?> Invoices Billed</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('finance') ?><p>No invoices billed.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><code><?= e($inv['invoice_number']) ?></code></td>
                            <td class="text-end"><?= number_format((float)$inv['total_amount'], 2) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)$inv['balance'], 2) ?></td>
                            <td><?= status_badge($inv['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Payments Received</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Receipt No.</th>
                            <th class="text-end">Amount</th>
                            <th>Method</th>
                            <th>Paid Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('check') ?><p>No payments recorded.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><code><?= e($p['receipt_number']) ?></code></td>
                            <td class="text-end text-success fw-semibold"><?= number_format((float)$p['amount'], 2) ?></td>
                            <td><?= e(humanize($p['method'])) ?></td>
                            <td class="text-muted-sm"><?= date_fmt($p['paid_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
