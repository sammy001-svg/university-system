<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Invoice: <?= e($invoice['invoice_number']) ?></h1>
        <p class="lede">Student Fee Invoice</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/portal/finance') ?>">&larr; Back to Fees &amp; Payments</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= icon('finance') ?> Fee Item Breakdown</span>
        <?= status_badge($invoice['status']) ?>
    </div>
    <div class="table-wrap">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount (<?= e(currency_code()) ?>)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['fee_type_name']) ?></td>
                    <td class="text-end"><?= e(money($item['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td>Total Amount</td>
                    <td class="text-end"><?= e(money($invoice['total_amount'])) ?></td>
                </tr>
                <tr class="text-success">
                    <td>Amount Paid</td>
                    <td class="text-end"><?= e(money($invoice['amount_paid'])) ?></td>
                </tr>
                <tr class="fw-bold <?= (float)$invoice['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <td>Balance Due</td>
                    <td class="text-end"><?= e(money($invoice['balance'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endsection(); ?>
