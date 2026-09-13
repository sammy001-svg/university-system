<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1><?= e($structure['name']) ?></h1>
        <p class="lede"><?= e($structure['study_mode'] ?? 'full_time') ?> &middot; Year <?= (int)$structure['year_of_study'] ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures') ?>">&larr; Back to Fee Structures</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('finance') ?> Fee Items</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th class="text-end">Amount (<?= e(currency_code()) ?>)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $total = 0;
                    if (empty($items)): ?>
                        <tr><td colspan="3"><div class="empty-state"><?= icon('finance') ?><p>No fee items configured.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): 
                        $total += (float)$item['amount'];
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= e($item['fee_type_name']) ?></td>
                            <td class="text-end"><?= e(money($item['amount'])) ?></td>
                            <td class="text-end">
                                <?php if (can('finance.manage')): ?>
                                    <form method="post" action="<?= url('/finance/fee-structures/' . $structure['id'] . '/items/' . $item['id'] . '/delete') ?>" onsubmit="return confirm('Remove fee item?')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><?= icon('trash', 'ico-sm') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($items)): ?>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Total Fee Amount</td>
                            <td class="text-end"><?= e(money($total)) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (can('finance.manage')): ?>
        <div class="card">
            <div class="card-header"><?= icon('plus') ?> Add Fee Item</div>
            <form method="post" action="<?= url('/finance/fee-structures/' . $structure['id'] . '/items') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                        <select name="fee_type_id" class="form-select" required>
                            <?= options(array_column($feeTypes, 'name', 'id'), '', 'Select Fee Type') ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (<?= e(currency_code()) ?>) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Add Item</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
