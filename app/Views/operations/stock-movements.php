<?php
layout('layouts.app');
section('content');
$i = $item;
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$low = (float) $i['quantity'] <= (float) $i['reorder_level'];
?>
<div class="page-head">
    <div><h1><?= e($i['name']) ?></h1>
        <p class="lede"><code><?= e($i['code']) ?></code> &middot; <?= e($i['category']) ?><?= $i['location'] ? ' &middot; ' . e($i['location']) : '' ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/assets/stock') ?>">&larr; Supplies &amp; Stock</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b class="<?= $low ? 'text-danger' : '' ?>"><?= $fmt($i['quantity']) ?> <?= e($i['unit']) ?></b><span>In stock<?= $low ? ' - reorder now' : '' ?></span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $fmt($i['reorder_level']) ?></b><span>Reorder level</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $i['unit_cost'] !== null ? e(money((float) $i['unit_cost'] * (float) $i['quantity'])) : '&ndash;' ?></b><span>Stock value</span></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('clock') ?> Movements</div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>When</th><th>Movement</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Issued to / reference</th><th>By</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state py-4"><p>No movements yet.</p></div></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="text-muted-sm text-nowrap"><?= fdatetime($r['created_at']) ?></td>
                        <td><?= ['in' => '<span class="badge text-bg-success">Received</span>', 'out' => '<span class="badge text-bg-warning">Issued</span>', 'adjust' => '<span class="badge text-bg-secondary">Stock count</span>'][$r['direction']] ?></td>
                        <td class="text-end"><?= $fmt($r['quantity']) ?></td><td class="text-end fw-semibold"><?= $fmt($r['balance_after']) ?></td>
                        <td class="small"><?= e((string) $r['department_name']) ?><?= $r['reference'] ? ' <code>' . e($r['reference']) . '</code>' : '' ?><?= $r['note'] ? '<div class="text-muted-sm">' . e($r['note']) . '</div>' : '' ?></td>
                        <td class="text-muted-sm"><?= e((string) $r['by_name']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('download') ?> Receive or issue</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/assets/stock/' . $i['id'] . '/movements') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Movement</label>
                            <select class="form-select" name="direction"><option value="out">Issue to a department</option><option value="in">Receive new stock</option><option value="adjust">Stock count (set the balance)</option></select></div>
                        <div class="mb-2"><label class="form-label">Quantity (<?= e($i['unit']) ?>)</label><input class="form-control" type="number" step="0.01" min="0" name="quantity" required></div>
                        <div class="mb-2"><label class="form-label">Department</label><select class="form-select" name="department_id"><?= options($departments, '', 'None') ?></select></div>
                        <div class="mb-2"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Requisition, delivery note or invoice no."></div>
                        <div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note"></div>
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
