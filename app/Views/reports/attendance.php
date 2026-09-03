<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Course Attendance Report</h1>
        <p class="lede">Course-level attendance compliance percentages.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/reports') ?>">&larr; Back to Reports</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('attendance') ?> Course Attendance Metrics</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th class="text-center">Total Sessions</th>
                    <th class="text-center">Attended Records</th>
                    <th class="text-center">Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><code><?= e($row['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($row['title']) ?></td>
                    <td class="text-center"><?= (int)$row['total_records'] ?></td>
                    <td class="text-center"><?= (int)$row['attended'] ?></td>
                    <td class="text-center fw-bold <?= (float)$row['rate'] < 75 ? 'text-danger' : 'text-success' ?>"><?= number_format((float)$row['rate'], 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
