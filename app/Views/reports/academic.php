<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Academic Grade Distribution Report</h1>
        <p class="lede">Institutional grade statistics &amp; performance metrics.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/reports') ?>">&larr; Back to Reports</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('results') ?> Grade Distribution</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Grade</th>
                    <th class="text-center">Count</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($gradeData as $row): ?>
                <tr>
                    <td class="fw-bold fs-5"><?= e($row['grade']) ?></td>
                    <td class="text-center fw-semibold"><?= number_format((int)$row['count']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
