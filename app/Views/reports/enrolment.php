<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Enrolment Report</h1>
        <p class="lede">Student enrolment breakdown by programme and gender.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/reports') ?>">&larr; Back to Reports</a>
</div>

<div class="card">
    <div class="card-header"><?= icon('reports') ?> Programme Enrolment Summary</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Programme</th>
                    <th class="text-center">Active Students</th>
                    <th class="text-center">Male</th>
                    <th class="text-center">Female</th>
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= e($row['faculty']) ?></td>
                    <td class="fw-semibold"><?= e($row['program']) ?></td>
                    <td class="text-center text-success fw-bold"><?= (int)$row['active'] ?></td>
                    <td class="text-center"><?= (int)$row['male'] ?></td>
                    <td class="text-center"><?= (int)$row['female'] ?></td>
                    <td class="text-center fw-bold"><?= (int)$row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
