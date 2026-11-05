<?php
use App\Services\Payments\OnlinePaymentService;

layout('layouts.app');
section('content');
$s = $student;
?>
<div class="page-head">
    <div><h1>Fees &middot; <?= e($s['first_name'] . ' ' . $s['last_name']) ?></h1>
        <p class="lede"><?= e($s['admission_number']) ?> &middot; outstanding <strong><?= e(money($balance)) ?></strong></p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/family/' . $s['id']) ?>">&larr; Overview</a>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('file') ?> Invoices</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Invoice</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th></th></tr></thead>
                <tbody>
                <?php if ($invoices === []): ?><tr><td colspan="5"><div class="empty-state py-3"><p>No invoices.</p></div></td></tr><?php endif; ?>
                <?php foreach ($invoices as $i): ?>
                    <tr><td><code><?= e($i['invoice_number']) ?></code><div class="text-muted-sm"><?= e((string) $i['title']) ?></div></td>
                        <td class="text-end"><?= e(money((float) $i['total_amount'] - (float) $i['discount_amount'])) ?></td>
                        <td class="text-end"><?= e(money($i['amount_paid'])) ?></td>
                        <td class="text-end fw-semibold <?= (float) $i['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($i['balance'])) ?></td>
                        <td class="text-end"><?php if ((float) $i['balance'] > 0.005 && $i['status'] !== 'cancelled'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= url('/pay/' . $s['id'] . '/' . $i['id']) ?>">Pay online</a><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php if ($online !== []): ?>
            <div class="card">
                <div class="card-header"><?= icon('clock') ?> Online payments</div>
                <div class="card-body py-1">
                    <?php foreach ($online as $o): ?>
                        <a class="el-item text-decoration-none" href="<?= url('/pay/status/' . $o['reference']) ?>">
                            <div class="el-item-main"><div class="el-item-title"><?= e(money($o['amount'])) ?> &middot; <?= e(explode(' (', OnlinePaymentService::PROVIDERS[$o['provider']])[0]) ?></div>
                                <div class="el-item-meta"><?= fdatetime($o['created_at']) ?></div></div>
                            <?= status_badge(['awaiting_verification' => 'pending', 'pending' => 'processing', 'successful' => 'paid'][$o['status']] ?? $o['status']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Payments received</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Receipt</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                <?php if ($payments === []): ?><tr><td colspan="3"><div class="empty-state py-3"><p>No payments yet.</p></div></td></tr><?php endif; ?>
                <?php foreach ($payments as $p): ?>
                    <tr><td><code><?= e($p['receipt_number']) ?></code><div class="text-muted-sm"><?= e(humanize($p['method'])) ?></div></td>
                        <td class="text-muted-sm"><?= fdate($p['paid_at']) ?></td><td class="text-end fw-semibold"><?= e(money($p['amount'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
</div>
<?php endsection(); ?>
