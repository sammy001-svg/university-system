<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Payment Receipt: <?= e($payment['receipt_number']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('download', 'ico-sm') ?> Print Receipt</button>
        <a class="btn btn-sm btn-light" href="<?= url('/finance/payments') ?>">&larr; Back to Payments</a>
    </div>
</div>

<div class="card p-4">
    <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold"><?= e($appName) ?></h4>
            <div class="text-muted-sm">Official Fee Receipt</div>
        </div>
        <div class="text-end">
            <div class="fw-bold text-primary fs-5"><?= e($payment['receipt_number']) ?></div>
            <div class="text-muted-sm"><?= date_fmt($payment['paid_at'] ?? '') ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="text-muted">Received From:</h6>
            <div class="fw-bold fs-6"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
            <div>Admission No: <code><?= e($payment['admission_number']) ?></code></div>
            <div>Email: <?= e($payment['email']) ?></div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h6 class="text-muted">Payment Info:</h6>
            <div>Payment Method: <strong><?= e(humanize($payment['method'])) ?></strong></div>
            <div>Reference: <code><?= e($payment['reference'] ?? 'N/A') ?></code></div>
            <div>Status: <?= status_badge($payment['status']) ?></div>
        </div>
    </div>

    <div class="table-wrap mb-4">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Student Fee Payment (Receipt Ref: <?= e($payment['receipt_number']) ?>)</td>
                    <td class="text-end fw-bold"><?= e(money($payment['amount'])) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="text-center text-muted-sm mt-3">
        Thank you for your payment. This is an officially generated system receipt.
    </div>
</div>
<?php endsection(); ?>
