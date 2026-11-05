<?php
layout('layouts.app');
section('content');
$totalBudget = array_sum(array_map(static fn ($l) => (float) $l['amount'], $lines));
$totalSpent  = array_sum(array_column($lines, 'actual'));
$pct = $totalBudget > 0 ? round($totalSpent / $totalBudget * 100) : 0;
?>
<div class="page-head">
    <div><h1>Budgets</h1><p class="lede">Budget against actual spending (approved and paid expenses) for <?= e((string) ($year['name'] ?? '')) ?>
        <?= $year ? '(' . fdate($year['start_date']) . ' &ndash; ' . fdate($year['end_date']) . ')' : '' ?>.</p></div>
    <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> Export</a>
</div>
<form class="filter-bar" method="get"><div class="row g-2"><div class="col-md-3"><select class="form-select form-select-sm" name="year" data-auto-submit><?= options($years, $yearId) ?></select></div></div></form>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="mini-stat"><b><?= e(money($totalBudget)) ?></b><span>Budget</span></div></div>
    <div class="col-md-4"><div class="mini-stat"><b><?= e(money($totalSpent)) ?></b><span>Spent (<?= $pct ?>%)</span></div></div>
    <div class="col-md-4"><div class="mini-stat"><b class="<?= $totalBudget - $totalSpent < 0 ? 'text-danger' : '' ?>"><?= e(money($totalBudget - $totalSpent)) ?></b><span>Remaining</span></div></div>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Department</th><th>Category</th><th class="text-end">Budget</th><th class="text-end">Spent</th><th style="width:22%">Used</th><th class="text-end">Remaining</th></tr></thead>
                <tbody>
                <?php if ($lines === []): ?><tr><td colspan="6"><div class="empty-state"><?= icon('money') ?><p>No budget set and no spending recorded for this year.</p></div></td></tr><?php endif; ?>
                <?php foreach ($lines as $l): $used = (float) $l['amount'] > 0 ? $l['actual'] / (float) $l['amount'] * 100 : ($l['actual'] > 0 ? 100 : 0); $left = (float) $l['amount'] - $l['actual']; ?>
                    <tr>
                        <td class="small"><?= e($l['department_name']) ?></td><td><?= e($l['category']) ?><?= $l['id'] === null ? ' <span class="badge text-bg-warning">No budget</span>' : '' ?></td>
                        <td class="text-end"><?= e(money($l['amount'])) ?></td><td class="text-end"><?= e(money($l['actual'])) ?></td>
                        <td><div class="d-flex align-items-center gap-2"><div class="meter flex-fill <?= $used > 100 ? 'bad' : ($used > 85 ? 'warn' : 'ok') ?>"><span style="width:<?= min(100, round($used)) ?>%"></span></div><span class="small"><?= round($used) ?>%</span></div></td>
                        <td class="text-end fw-semibold <?= $left < 0 ? 'text-danger' : '' ?>"><?= e(money($left)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="col-xl-4">
            <div class="card"><div class="card-header"><?= icon('plus') ?> Set a budget line</div><div class="card-body">
                <form method="post" action="<?= url('/finance/budgets') ?>"><?= csrf_field() ?>
                    <input type="hidden" name="academic_year_id" value="<?= (int) $yearId ?>">
                    <div class="mb-2"><label class="form-label">Department</label><select class="form-select" name="department_id"><?= options($departments, '', 'Institution-wide') ?></select></div>
                    <div class="mb-2"><label class="form-label">Expense category</label><input class="form-control" name="category" list="cats" required>
                        <datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                    <div class="mb-2"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0" name="amount" required></div>
                    <div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
                    <button class="btn btn-primary w-100">Save</button>
                    <div class="form-text">Setting a line that exists replaces its amount. Use the same category names as on expenses so spending is matched.</div>
                </form></div></div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
