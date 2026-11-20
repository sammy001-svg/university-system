<?php layout('layouts.app'); section('content'); $s = $sale; $voided = $s['status'] === 'voided'; ?>
<div class="page-head">
    <div><h1>Store Receipt: <?= e((string) $s['receipt_number']) ?></h1></div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <?php if (can('store.sell')): ?><a class="btn btn-sm btn-primary" href="<?= url('/store/sales/create') ?>"><?= icon('plus', 'ico-sm') ?> New Sale</a><?php endif; ?>
        <a class="btn btn-sm btn-light" href="<?= url('/store/sales') ?>">&larr; Store Sales</a>
    </div>
</div>

<?php if ($voided): ?>
    <div class="alert alert-danger">This sale was <strong>voided</strong><?= $s['voided_by_name'] ? ' by ' . e($s['voided_by_name']) : '' ?> on <?= fdatetime($s['voided_at']) ?>. Reason: <?= e((string) $s['void_reason']) ?></div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">
<div class="card p-4">
    <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold"><?= e($appName) ?></h4>
            <div class="text-muted-sm">University Store &middot; Sales Receipt</div>
        </div>
        <div class="text-end">
            <div class="fw-bold text-primary fs-5"><?= e((string) $s['receipt_number']) ?></div>
            <div class="text-muted-sm"><?= fdatetime($s['sold_at']) ?></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-6">
            <h6 class="text-muted">Sold to:</h6>
            <div class="fw-bold"><?= e((string) $s['customer_name']) ?></div>
            <div class="text-muted-sm"><?= e(humanize($s['customer_type'])) ?>
                <?= $s['admission_number'] ? ' &middot; Adm. No. <code>' . e($s['admission_number']) . '</code>' : '' ?>
                <?= $s['staff_number'] ? ' &middot; Staff No. <code>' . e($s['staff_number']) . '</code>' : '' ?></div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h6 class="text-muted">Payment:</h6>
            <div><strong><?= e(humanize($s['payment_method'])) ?></strong><?= $s['reference'] ? ' &middot; <code>' . e($s['reference']) . '</code>' : '' ?></div>
            <div>Status: <?= status_badge($s['status']) ?></div>
        </div>
    </div>

    <div class="table-wrap mb-3">
        <table class="table table-bordered mb-0">
            <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr><td><?= e($i['product_name']) ?> <span class="text-muted-sm"><code><?= e($i['sku']) ?></code></span></td>
                    <td class="text-end"><?= rtrim(rtrim(number_format((float) $i['quantity'], 2), '0'), '.') ?></td>
                    <td class="text-end"><?= e(money($i['unit_price'])) ?></td>
                    <td class="text-end"><?= e(money($i['line_total'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3" class="text-end">Subtotal</th><td class="text-end"><?= e(money($s['subtotal'])) ?></td></tr>
                <?php if ((float) $s['discount'] > 0): ?><tr><th colspan="3" class="text-end">Discount</th><td class="text-end">&minus;<?= e(money($s['discount'])) ?></td></tr><?php endif; ?>
                <tr><th colspan="3" class="text-end fs-6">Total</th><td class="text-end fw-bold fs-6"><?= e(money($s['total'])) ?></td></tr>
                <?php if ($s['amount_tendered'] !== null): ?>
                    <tr><th colspan="3" class="text-end">Cash received</th><td class="text-end"><?= e(money($s['amount_tendered'])) ?></td></tr>
                    <tr><th colspan="3" class="text-end">Change</th><td class="text-end"><?= e(money($s['change_due'])) ?></td></tr>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>
    <?php if ($s['note']): ?><p class="text-muted-sm mb-2">Note: <?= e($s['note']) ?></p><?php endif; ?>
    <div class="text-center text-muted-sm mt-2">Served by <?= e((string) $s['sold_by_name']) ?>. Thank you for shopping with us.</div>
</div>
</div>

<?php if (!$voided && can('store.void')): ?>
<div class="col-lg-4 d-print-none">
    <div class="card">
        <div class="card-header"><?= icon('trash') ?> Void this sale</div>
        <div class="card-body">
            <form method="post" action="<?= url('/store/sales/' . $s['id'] . '/void') ?>" onsubmit="return confirm('Void this sale and return the items to stock?')">
                <?= csrf_field() ?>
                <div class="mb-2"><label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input class="form-control" name="reason" required maxlength="255" placeholder="e.g. Wrong item, customer returned goods"></div>
                <button class="btn btn-outline-danger w-100">Void sale</button>
            </form>
            <p class="text-muted-sm mt-2 mb-0">The items go back into stock and the sale is removed from the takings.</p>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
<?php endsection(); ?>

