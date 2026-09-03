<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Attendance Tracking</h1>
        <p class="lede">Class attendance sessions and records.</p>
    </div>
    <?php if (can('attendance.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/attendance/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New Session
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('attendance') ?> Attendance Sessions (<?= count($sessions) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Section</th>
                    <th>Session Type</th>
                    <th>Topic</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sessions)): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('attendance') ?><p>No attendance sessions created.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><?= date_fmt($s['session_date']) ?></td>
                    <td><code><?= e($s['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['title']) ?></td>
                    <td>Sec <?= e($s['section']) ?></td>
                    <td><?= e(humanize($s['session_type'])) ?></td>
                    <td><?= e($s['topic'] ?? '—') ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/attendance/sessions/' . $s['id']) ?>"><?= icon('edit', 'ico-sm') ?> Mark Attendance</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
