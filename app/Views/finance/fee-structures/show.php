<?php layout('layouts.app'); section('content'); ?>
<?php
$total = 0.0;
foreach ($items as $item) {
    $total += (float) $item['amount'];
}
$nextNumber = $hasSavedPlan ? max(array_column($plan, 'installment_number')) + 1 : 1;
?>
<div class="page-head">
    <div>
        <h1><?= e($structure['name']) ?></h1>
        <p class="lede">
            <?= e(\App\Models\FeeStructure::scopeLabel($structure)) ?>
            &middot; <?= e(\App\Models\FeeStructure::STATUSES[$structure['student_status']] ?? '') ?>
            &middot; <?= e($structure['academic_year']) ?>
            &middot; <?= $structure['year_of_study'] !== null ? 'Year ' . (int) $structure['year_of_study'] : 'Any year' ?>
            &middot; <?= e($structure['study_mode'] !== null ? humanize($structure['study_mode']) : 'Any mode') ?>
            &middot; <?= status_badge($structure['status']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures') ?>">&larr; Back to Fee Structures</a>
        <?php if (can('finance.manage')): ?>
            <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures/' . $structure['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><?= icon('money') ?> Fee Items</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Billed In</th>
                            <th class="text-end">Amount (<?= e(currency_code()) ?>)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('money') ?><p>No fee items configured.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($item['fee_type_name']) ?></td>
                            <td class="text-muted-sm"><?= $item['category'] === 'tuition' ? 'Spread across installments' : '1st installment' ?></td>
                            <td class="text-end"><?= e(money($item['amount'])) ?></td>
                            <td class="text-end">
                                <?php if (can('finance.manage')): ?>
                                    <form method="post" action="<?= url('/finance/fee-structures/' . $structure['id'] . '/items/' . $item['id']) ?>" onsubmit="return confirm('Remove fee item?')">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
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
                            <td colspan="2">Total Fee Amount</td>
                            <td class="text-end"><?= e(money($total)) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('calendar') ?> Installment Plan &amp; Sample Bill</div>
            <?php if (!$hasSavedPlan): ?>
                <div class="card-body pb-0">
                    <div class="alert alert-info mb-0">No plan saved yet, so the default applies: <?= e((string) setting('fee_threshold_percent', 60)) ?>% of tuition in the 1st installment and the rest in a 2nd. Add installments to replace it.</div>
                </div>
            <?php endif; ?>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Installment</th>
                            <th class="text-end">Share of Tuition</th>
                            <th>Due</th>
                            <th class="text-end">Sample Amount (<?= e(currency_code()) ?>)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plan as $i => $step): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= e($step['label']) ?>
                                <?php if ($i === 0): ?><span class="badge text-bg-danger ms-1">Compulsory</span><?php endif; ?>
                            </td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format($step['tuition_percent'], 2), '0'), '.')) ?>%</td>
                            <td class="text-muted-sm"><?= $step['due_days'] === 0 ? 'Semester start' : e(sprintf('%+d days from semester start', $step['due_days'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(money($preview[$i]['amount'] ?? 0)) ?></td>
                            <td class="text-end">
                                <?php if ($hasSavedPlan && can('finance.manage')): ?>
                                    <form method="post" action="<?= url('/finance/fee-structures/' . $structure['id'] . '/installments/' . $step['id']) ?>" onsubmit="return confirm('Remove this installment?')">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
                                        <button class="btn btn-sm btn-outline-danger"><?= icon('trash', 'ico-sm') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted-sm">
                The 1st installment carries every non-tuition fee plus its share of tuition. It is compulsory: once it is paid in full the student is enrolled for the semester and registration is complete. Sample amounts are before any scholarship.
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (can('finance.manage')): ?>
        <div class="card mb-4">
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
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Add Item</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('calendar') ?> Add / Update Installment</div>
            <form method="post" action="<?= url('/finance/fee-structures/' . $structure['id'] . '/installments') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">No. <span class="text-danger">*</span></label>
                            <input type="number" name="installment_number" min="1" max="6" class="form-control" value="<?= (int) $nextNumber ?>" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control" value="<?= e(match ((int) $nextNumber) { 1 => '1st', 2 => '2nd', 3 => '3rd', default => $nextNumber . 'th' }) ?> Installment" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">% of Tuition <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" max="100" name="tuition_percent" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Due (days) <span class="text-danger">*</span></label>
                            <input type="number" name="due_days" class="form-control" value="0" required>
                        </div>
                    </div>
                    <div class="form-text mt-2">Days after the semester start. The last installment takes any tuition left over.</div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Save Installment</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
