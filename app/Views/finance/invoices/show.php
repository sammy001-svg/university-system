<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Invoice: <?= e($invoice['invoice_number']) ?></h1>
        <p class="lede">Student: <?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?> (<?= e($invoice['admission_number']) ?>)</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= url('/invoices') ?>">&larr; Back to Invoices</a>
        <?php if ($invoice['status'] !== 'cancelled' && can('finance.edit')): ?>
            <form method="post" action="<?= url('/invoices/' . $invoice['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this invoice?')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">Cancel Invoice</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= icon('finance') ?> Invoice Breakdown</span>
        <?= status_badge($invoice['status']) ?>
    </div>
    <div class="table-wrap">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th class="text-end">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['fee_type_name']) ?></td>
                    <td class="text-end"><?= number_format((float)$item['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td>Total Amount</td>
                    <td class="text-end">KES <?= number_format((float)$invoice['total_amount'], 2) ?></td>
                </tr>
                <tr class="text-success">
                    <td>Amount Paid</td>
                    <td class="text-end">KES <?= number_format((float)$invoice['amount_paid'], 2) ?></td>
                </tr>
                <tr class="fw-bold <?= (float)$invoice['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <td>Balance Due</td>
                    <td class="text-end">KES <?= number_format((float)$invoice['balance'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endsection(); ?>
