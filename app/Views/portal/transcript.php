<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Academic Transcript</h1>
        <p class="lede"><?= e($student['first_name'] . ' ' . $student['last_name']) ?> (<?= e($student['admission_number']) ?>)</p>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('download', 'ico-sm') ?> Print Transcript</button>
</div>

<div class="card p-4">
    <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0"><?= e($appName) ?></h3>
            <div class="text-muted-sm">Student Self-Service Transcript</div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5">CGPA: <?= number_format((float)($student['cgpa'] ?? 0), 2) ?></div>
        </div>
    </div>

    <div class="table-wrap mb-4">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Course Title</th>
                    <th class="text-center">Credits</th>
                    <th class="text-center">Score</th>
                    <th class="text-center">Grade</th>
                    <th>Term</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="6"><div class="empty-state text-sm"><?= icon('results') ?><p>No results published.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="text-center"><?= (int)$r['credit_hours'] ?></td>
                    <td class="text-center"><?= number_format((float)$r['total_score'], 1) ?></td>
                    <td class="text-center fw-bold"><?= e($r['grade']) ?></td>
                    <td class="text-muted-sm"><?= e($r['semester_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
