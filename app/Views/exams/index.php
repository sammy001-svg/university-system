<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Examinations</h1>
        <p class="lede">Scheduled course examinations &amp; time slots.</p>
    </div>
    <?php if (can('exams.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/exams/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Schedule Exam
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('exams') ?> Examination Schedule (<?= count($exams) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Exam Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($exams)): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('exams') ?><p>No examinations scheduled.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($exams as $e): ?>
                <tr>
                    <td><code><?= e($e['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($e['title']) ?></td>
                    <td><?= e(humanize($e['exam_type'])) ?></td>
                    <td><?= date_fmt($e['exam_date']) ?></td>
                    <td><?= e(substr($e['start_time'], 0, 5)) ?></td>
                    <td><?= (int)$e['duration_mins'] ?> mins</td>
                    <td><?= e($e['room_name'] ?? 'TBA') ?></td>
                    <td><?= status_badge($e['status']) ?></td>
                    <td class="text-end">
                        <?php if (can('exams.edit')): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/exams/' . $e['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
