<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Institutional Financial Report</h1>
        <p class="lede">Total billing, fee collections and monthly payment trends.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/reports') ?>">&larr; Back to Reports</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat">
            <span class="stat-icon"><?= icon('finance', 'ico-lg') ?></span>
            <div>
                <div class="stat-value"><?= e(money($summary['total_billed'] ?? 0)) ?></div>
                <div class="stat-label">Total Billed</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat tone-green">
            <span class="stat-icon"><?= icon('check', 'ico-lg') ?></span>
            <div>
                <div class="stat-value"><?= e(money($summary['total_collected'] ?? 0)) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat tone-red">
            <span class="stat-icon"><?= icon('warning', 'ico-lg') ?></span>
            <div>
                <div class="stat-value"><?= e(money($summary['outstanding'] ?? 0)) ?></div>
                <div class="stat-label">Outstanding Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('finance') ?> Monthly Collections Trend</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-end">Amount Collected (<?= e(currency_code()) ?>)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthly as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['month']) ?></td>
                    <td class="text-end text-success fw-bold"><?= e(money($row['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
