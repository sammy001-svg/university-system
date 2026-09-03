<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Academic Transcripts</h1>
        <p class="lede">Generate and inspect official student transcripts.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('student') ?> Student Transcripts (<?= count($students) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Programme</th>
                    <th class="text-center">CGPA</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('student') ?><p>No students found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= e($s['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= e($s['program_name']) ?></td>
                    <td class="text-center fw-bold"><?= number_format((float)$s['cgpa'], 2) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="<?= url('/students/' . $s['id'] . '/transcript') ?>"><?= icon('file', 'ico-sm') ?> View Transcript</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
