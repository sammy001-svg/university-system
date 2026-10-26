<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Invoice: <?= e($invoice['invoice_number']) ?></h1>
        <p class="lede">Student Fee Invoice</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/portal/finance/invoice/' . $invoice['id'] . '/billing-form') ?>"><?= icon('print', 'ico-sm') ?> Billing Form</a>
        <a class="btn btn-sm btn-light" href="<?= url('/portal/finance') ?>">&larr; Back to Fees &amp; Payments</a>
    </div>
</div>

<?php partial('partials.installments', ['installments' => $installments, 'enrollment' => $enrollment]); ?>

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
                    <td><?= e($item['description'] ?: $item['fee_type_name']) ?><?php if (!empty($item['course_id'])): ?> <span class="text-muted small">(<?= (int) $item['quantity'] ?> cr × <?= e(money($item['unit_amount'])) ?>)</span><?php endif; ?></td>
                    <td class="text-end"><?= e(money($item['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td>Total Amount</td>
                    <td class="text-end"><?= e(money($invoice['total_amount'])) ?></td>
                </tr>
                <?php if ((float) $invoice['discount_amount'] > 0): ?>
                <tr>
                    <td>Scholarship / Discount</td>
                    <td class="text-end">-<?= e(money($invoice['discount_amount'])) ?></td>
                </tr>
                <?php endif; ?>
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
