<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>My Results</h1><p class="lede"><?= e($student['admission_number']) ?> &middot; <?= e($student['program_name']) ?></p></div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/portal/transcript') ?>"><?= icon('file','ico-sm') ?> Full Transcript</a>
</div>

<?php if (!empty($history)): ?>
<div class="row g-3 mb-4">
    <?php foreach ($history as $h): ?>
    <div class="col-md-4">
        <div class="stat">
            <div>
                <div class="stat-value"><?= number_format((float)($h['sgpa'] ?? 0), 2) ?></div>
                <div class="stat-label"><?= e($h['semester_name']) ?> SGPA</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-md-4">
        <div class="stat tone-green">
            <div>
                <div class="stat-value"><?= number_format((float)($student['cgpa'] ?? 0), 2) ?></div>
                <div class="stat-label">Cumulative GPA</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><?= icon('results') ?> Course Results</div>
    <div class="table-wrap">
        <table class="table align-middle mb-0">
            <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th class="text-center">Score</th><th class="text-center">Grade</th><th>Semester</th><th>Outcome</th></tr></thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('results') ?><p>No published results yet.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="text-center"><?= (int)$r['credit_hours'] ?></td>
                    <td class="text-center"><?= number_format((float)$r['total_score'], 1) ?></td>
                    <td class="text-center"><strong><?= e($r['grade']) ?></strong></td>
                    <td class="text-muted-sm"><?= e($r['semester_name']) ?></td>
                    <td><?= status_badge($r['outcome'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
