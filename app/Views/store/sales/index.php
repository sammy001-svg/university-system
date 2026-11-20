<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Store Sales</h1>
        <p class="lede">Sales to students, staff and walk-in customers.</p>
    </div>
    <?php if (can('store.sell')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/store/sales/create') ?>"><?= icon('plus', 'ico-sm') ?> New Sale</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="mini-stat"><b><?= number_format((int) $totals['sales']) ?></b><span>Completed sales</span></div></div>
    <div class="col-6 col-md-4"><div class="mini-stat"><b><?= e(money($totals['revenue'])) ?></b><span>Takings</span></div></div>
    <div class="col-6 col-md-4"><div class="mini-stat"><b><?= e(money($totals['cash'])) ?></b><span>Of which cash</span></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Customer</label>
                <select name="customer_type" class="form-select"><?= options(['student' => 'Students', 'staff' => 'Staff', 'walk_in' => 'Walk-in'], $filters['customer_type'], 'All') ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Status</label>
                <select name="status" class="form-select"><?= options(['completed' => 'Completed', 'voided' => 'Voided'], $filters['status'], 'All') ?></select></div>
            <div class="col-md-3"><label class="form-label">Search</label><input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Receipt, name, admission or staff no."></div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100"><?= icon('search', 'ico-sm') ?></button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('file') ?> Sales (<?= count($sales) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th class="text-end">Items</th><th class="text-end">Total</th><th>Payment</th><th>Sold by</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if ($sales === []): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('file') ?><p>No sales in this period.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><a href="<?= url('/store/sales/' . $s['id']) ?>"><code><?= e((string) $s['receipt_number']) ?></code></a></td>
                    <td class="text-muted-sm text-nowrap"><?= fdatetime($s['sold_at']) ?></td>
                    <td><div class="fw-semibold"><?= e((string) $s['customer_name']) ?></div>
                        <div class="text-muted-sm"><?= e(humanize($s['customer_type'])) ?><?= $s['admission_number'] ? ' &middot; ' . e($s['admission_number']) : '' ?><?= $s['staff_number'] ? ' &middot; ' . e($s['staff_number']) : '' ?></div></td>
                    <td class="text-end"><?= rtrim(rtrim(number_format((float) $s['item_count'], 2), '0'), '.') ?></td>
                    <td class="text-end fw-semibold <?= $s['status'] === 'voided' ? 'text-decoration-line-through text-muted' : '' ?>"><?= e(money($s['total'])) ?></td>
                    <td><?= e(humanize($s['payment_method'])) ?></td>
                    <td class="text-muted-sm"><?= e((string) $s['sold_by_name']) ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/store/sales/' . $s['id']) ?>"><?= icon('file', 'ico-sm') ?> Receipt</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>

