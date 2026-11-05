<?php
layout('layouts.app');
section('content');
$a = $asset;
$total = array_sum(array_map(static fn ($r) => (float) $r['cost'], $rows));
?>
<div class="page-head">
    <div><h1><?= e($a['name']) ?></h1>
        <p class="lede"><code><?= e($a['tag']) ?></code> &middot; <?= e(humanize($a['category'])) ?> &middot; <?= status_badge($a['status']) ?>
            &middot; condition <?= e(humanize($a['asset_condition'])) ?><?= $a['warranty_until'] ? ' &middot; warranty until ' . fdate($a['warranty_until']) : '' ?></p></div>
    <div class="d-flex gap-2">
        <?php if ($canManage): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('/assets/' . $a['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit asset</a><?php endif; ?>
        <a class="btn btn-sm btn-light" href="<?= url('/assets') ?>">&larr; Asset register</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('settings') ?> Maintenance and repairs <span class="ms-auto small fw-normal">Total cost <?= e(money($total)) ?></span></div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Work done</th><th>Vendor</th><th class="text-end">Cost</th><th>Recorded by</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state py-4"><p>No maintenance recorded.</p></div></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="text-nowrap"><?= fdate($r['performed_on']) ?></td><td><?= e($r['description']) ?></td><td class="text-muted-sm"><?= e((string) $r['vendor']) ?></td>
                        <td class="text-end"><?= $r['cost'] !== null ? e(money($r['cost'])) : '' ?></td><td class="text-muted-sm"><?= e((string) $r['by_name']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('plus') ?> Record maintenance</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/assets/' . $a['id'] . '/maintenance') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Date</label><input class="form-control" type="date" name="performed_on" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="mb-2"><label class="form-label">Work done</label><input class="form-control" name="description" required maxlength="255"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Cost</label><input class="form-control" type="number" step="0.01" min="0" name="cost"></div>
                            <div class="col-6"><label class="form-label">Vendor</label><input class="form-control" name="vendor"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Asset is now</label>
                            <select class="form-select" name="set_status"><option value="">Unchanged</option><option value="under_repair">Under repair</option><option value="in_use">Back in use</option></select></div>
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
