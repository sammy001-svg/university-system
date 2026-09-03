<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Payments</h1>
        <p class="lede">Student fee payment transactions and receipts.</p>
    </div>
    <?php if (can('finance.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/payments/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Record Payment
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('check') ?> Payment Register (<?= count($payments) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Receipt No.</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th class="text-end">Amount</th>
                    <th>Payment Method</th>
                    <th>Reference</th>
                    <th>Paid At</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('check') ?><p>No payments recorded.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><code><?= e($p['receipt_number']) ?></code></td>
                    <td><code><?= e($p['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                    <td class="text-end fw-semibold text-success">KES <?= number_format((float)$p['amount'], 2) ?></td>
                    <td><?= e(humanize($p['method'])) ?></td>
                    <td><code><?= e($p['reference'] ?? '—') ?></code></td>
                    <td class="text-muted-sm"><?= date_fmt($p['paid_at'] ?? '') ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= url('/payments/' . $p['id'] . '/receipt') ?>"><?= icon('file', 'ico-sm') ?> Receipt</a>
                        <?php if ($p['status'] === 'confirmed' && can('finance.reverse')): ?>
                            <form method="post" action="<?= url('/payments/' . $p['id'] . '/reverse') ?>" class="d-inline" onsubmit="return confirm('Reverse this payment?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Reverse</button>
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
