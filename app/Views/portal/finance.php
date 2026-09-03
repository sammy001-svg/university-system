<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>My Fees &amp; Payments</h1></div>
</div>

<div class="row g-3 mb-4">
    <?php $tiles = [
        ['Total Billed', 'KES ' . number_format($totalBilled, 2), 'finance', ''],
        ['Total Paid', 'KES ' . number_format($totalPaid, 2), 'check', 'tone-green'],
        ['Outstanding', 'KES ' . number_format($balance, 2), 'warning', $balance > 0 ? 'tone-red' : 'tone-green'],
    ];
    foreach ($tiles as [$label, $value, $iconName, $tone]): ?>
        <div class="col-md-4">
            <div class="stat <?= $tone ?>">
                <span class="stat-icon"><?= icon($iconName,'ico-lg') ?></span>
                <div><div class="stat-value"><?= e($value) ?></div><div class="stat-label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><?= icon('finance') ?> Invoices</div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Invoice No.</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="7"><div class="empty-state"><?= icon('finance') ?><p>No invoices.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><code><?= e($inv['invoice_number']) ?></code></td>
                            <td><?= number_format((float)$inv['total_amount'], 2) ?></td>
                            <td><?= number_format((float)$inv['amount_paid'], 2) ?></td>
                            <td class="<?= $inv['balance'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= number_format((float)$inv['balance'], 2) ?></td>
                            <td class="text-muted-sm"><?= date_fmt($inv['due_date'] ?? '') ?></td>
                            <td><?= status_badge($inv['status']) ?></td>
                            <td><a class="btn btn-sm btn-light" href="<?= url('/portal/invoices/' . $inv['id']) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Payments</div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="4"><div class="empty-state text-sm"><?= icon('check') ?><p>No payments yet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><code><?= e($p['receipt_number']) ?></code></td>
                            <td><?= number_format((float)$p['amount'], 2) ?></td>
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
