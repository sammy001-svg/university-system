<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Official Academic Transcript</h1>
        <p class="lede"><?= e($student['first_name'] . ' ' . $student['last_name']) ?> (<?= e($student['admission_number']) ?>) &middot; <?= e($student['program_name']) ?></p>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('download', 'ico-sm') ?> Print Transcript</button>
</div>

<div class="card p-4">
    <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0"><?= e($appName) ?></h3>
            <div class="text-muted-sm">Office of the Academic Registrar &middot; Official Transcript</div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5">CGPA: <?= number_format((float)($student['cgpa'] ?? 0), 2) ?></div>
            <div class="text-muted-sm">Date Issued: <?= date('d M Y') ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6">
            <div><strong>Student Name:</strong> <?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
            <div><strong>Admission No:</strong> <code><?= e($student['admission_number']) ?></code></div>
            <div><strong>Programme:</strong> <?= e($student['program_name']) ?> (<?= e($student['program_code']) ?>)</div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <div><strong>Faculty:</strong> <?= e($student['faculty_name'] ?? '—') ?></div>
            <div><strong>Department:</strong> <?= e($student['department_name'] ?? '—') ?></div>
            <div><strong>Current Year:</strong> Year <?= (int)$student['year_of_study'] ?></div>
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
                    <th>Academic Term</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="6"><div class="empty-state text-sm"><?= icon('results') ?><p>No published academic results found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="text-center"><?= (int)$r['credit_hours'] ?></td>
                    <td class="text-center"><?= number_format((float)$r['total_score'], 1) ?></td>
                    <td class="text-center fw-bold"><?= e($r['grade']) ?></td>
                    <td class="text-muted-sm"><?= e($r['academic_year']) ?> - <?= e($r['semester_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="border-top pt-4 mt-4 d-flex justify-content-between text-muted-sm">
        <div>Certified Official Document</div>
        <div>Registrar Signature: ______________________</div>
    </div>
</div>
<?php endsection(); ?>
