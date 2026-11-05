<?php
use App\Services\Payments\OnlinePaymentService;

layout('layouts.app');
$pageTitle = 'Online Payments';
section('content');
$tabs = ['awaiting_verification' => 'To verify', 'pending' => 'Waiting on phone', 'successful' => 'Confirmed',
         'rejected' => 'Rejected', 'failed' => 'Failed', 'all' => 'All'];
?>
<div class="page-head">
    <div>
        <h1>Online Payments</h1>
        <p class="lede">Fees paid online by students and sponsors. MTN MoMo payments post themselves once MTN confirms them;
            mobile money and bank references need you to match them with the money received, then confirm.</p>
    </div>
</div>

<?php if ($providers === []): ?>
    <div class="alert alert-info">No online payment method is set up. Add the merchant numbers or bank details under
        <a href="<?= url('/admin/settings?group=payments') ?>">Settings &rsaquo; Payments</a><?= $momo ? '' : ', or the MTN MoMo API keys in the .env file' ?>.</div>
<?php endif; ?>

<nav class="el-tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $status === $key ? 'active' : '' ?>" href="<?= url('/finance/online-payments?status=' . $key) ?>"><?= e($label) ?>
            <?php if ($key !== 'all' && !empty($counts[$key])): ?><span class="badge text-bg-<?= $key === 'awaiting_verification' ? 'warning' : 'light' ?>"><?= (int) $counts[$key] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Started</th><th>Student</th><th>Method</th><th>Transaction ID</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('money') ?><p>Nothing here.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted-sm text-nowrap"><?= fdatetime($r['created_at']) ?></td>
                    <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div>
                        <div class="text-muted-sm"><?= e($r['admission_number']) ?> &middot; <?= e((string) $r['invoice_number']) ?>
                            <?= $r['initiated_type'] === 'parent' ? '&middot; paid by ' . e($r['initiated_name']) : '' ?></div></td>
                    <td class="small"><?= e(explode(' (', OnlinePaymentService::PROVIDERS[$r['provider']])[0]) ?>
                        <?php if ($r['payer_phone']): ?><div class="text-muted-sm"><?= e($r['payer_phone']) ?></div><?php endif; ?></td>
                    <td><code><?= e((string) $r['provider_reference']) ?></code>
                        <?php if ($r['proof_path']): ?><a class="small d-block" target="_blank" href="<?= url('/finance/online-payments/' . $r['reference'] . '/proof') ?>">View proof</a><?php endif; ?></td>
                    <td class="text-end fw-semibold"><?= e(money($r['amount'])) ?></td>
                    <td><?= status_badge(['awaiting_verification' => 'pending', 'pending' => 'processing', 'successful' => 'paid'][$r['status']] ?? $r['status']) ?>
                        <?php if ($r['receipt_number']): ?><div class="text-muted-sm"><?= e($r['receipt_number']) ?></div><?php endif; ?>
                        <?php if ($r['message']): ?><div class="text-muted-sm"><?= e($r['message']) ?></div><?php endif; ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['status'] === 'awaiting_verification' && can('finance.create')): ?>
                            <form class="d-inline" method="post" action="<?= url('/finance/online-payments/' . $r['reference'] . '/approve') ?>"
                                  data-confirm="Confirm that <?= e(money($r['amount'])) ?> with transaction ID <?= e((string) $r['provider_reference']) ?> has been received?">
                                <?= csrf_field() ?><button class="btn btn-sm btn-success">Confirm</button>
                            </form>
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#rej<?= $r['id'] ?>">Reject</button>
                            <form class="collapse mt-2" id="rej<?= $r['id'] ?>" method="post" action="<?= url('/finance/online-payments/' . $r['reference'] . '/reject') ?>">
                                <?= csrf_field() ?>
                                <div class="input-group input-group-sm"><input class="form-control" name="reason" placeholder="Reason, e.g. no such transaction" required>
                                    <button class="btn btn-outline-danger">Reject</button></div>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
