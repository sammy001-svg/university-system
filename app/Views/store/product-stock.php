<?php
layout('layouts.app');
section('content');
$p = $product;
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$low = (float) $p['quantity'] <= (float) $p['reorder_level'];
$badges = [
    'in'     => '<span class="badge text-bg-success">Received</span>',
    'sale'   => '<span class="badge text-bg-primary">Sold</span>',
    'void'   => '<span class="badge text-bg-info">Sale voided</span>',
    'adjust' => '<span class="badge text-bg-secondary">Stock count</span>',
];
?>
<div class="page-head">
    <div><h1><?= e($p['name']) ?></h1>
        <p class="lede"><code><?= e($p['sku']) ?></code> &middot; <?= e($p['category']) ?> &middot; <?= e(money($p['price'])) ?> per <?= e($p['unit']) ?>
            <?= (int) $p['is_active'] === 1 ? '' : ' &middot; <span class="badge text-bg-secondary">Not for sale</span>' ?></p></div>
    <div class="d-flex gap-2">
        <?php if ($canManage): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('/store/products/' . $p['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a><?php endif; ?>
        <a class="btn btn-sm btn-light" href="<?= url('/store/products') ?>">&larr; Store Products</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b class="<?= $low ? 'text-danger' : '' ?>"><?= $fmt($p['quantity']) ?> <?= e($p['unit']) ?></b><span>In stock<?= $low ? ' - reorder now' : '' ?></span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $fmt($p['reorder_level']) ?></b><span>Reorder level</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= e(money((float) $p['price'] * (float) $p['quantity'])) ?></b><span>Stock value (at selling price)</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $fmt($soldQty) ?></b><span>Sold to date</span></div></div>
</div>
<div class="row g-3">
    <div class="<?= $canManage ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header"><?= icon('clock') ?> Stock history</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>When</th><th>Movement</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Reference</th><th>By</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state py-4"><p>No stock movements yet. Receive stock to start selling.</p></div></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="text-muted-sm text-nowrap"><?= fdatetime($r['created_at']) ?></td>
                        <td><?= $badges[$r['direction']] ?? e($r['direction']) ?></td>
                        <td class="text-end"><?= $fmt($r['quantity']) ?></td><td class="text-end fw-semibold"><?= $fmt($r['balance_after']) ?></td>
                        <td class="small">
                            <?php if ($r['sale_id']): ?><a href="<?= url('/store/sales/' . $r['sale_id']) ?>"><code><?= e((string) $r['receipt_number']) ?></code></a>
                            <?php elseif ($r['reference']): ?><code><?= e($r['reference']) ?></code><?php endif; ?>
                            <?= $r['note'] ? '<div class="text-muted-sm">' . e($r['note']) . '</div>' : '' ?></td>
                        <td class="text-muted-sm"><?= e((string) $r['by_name']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('download') ?> Receive or count stock</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/store/products/' . $p['id'] . '/stock') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Movement</label>
                            <select class="form-select" name="direction"><option value="in">Receive new stock</option><option value="adjust">Stock count (set the balance)</option></select></div>
                        <div class="mb-2"><label class="form-label">Quantity (<?= e($p['unit']) ?>)</label><input class="form-control" type="number" step="0.01" min="0" name="quantity" required></div>
                        <div class="mb-2"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Delivery note or supplier invoice no."></div>
                        <div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note"></div>
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                    <p class="text-muted-sm mt-2 mb-0">Sales reduce stock automatically at the till.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>

