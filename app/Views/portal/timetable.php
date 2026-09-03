<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Class Timetable</h1>
        <p class="lede"><?= e($semester['name'] ?? 'Current Semester') ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('clock') ?> Weekly Schedule</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Room</th>
                    <th>Lecturer</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($slots)): ?>
                <tr><td colspan="6"><div class="empty-state"><?= icon('clock') ?><p>No registered classes scheduled for this semester.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($slots as $s): ?>
                <tr>
                    <td class="fw-bold text-capitalize"><?= e($s['day_of_week']) ?></td>
                    <td><code><?= e(substr($s['start_time'], 0, 5)) ?> - <?= e(substr($s['end_time'], 0, 5)) ?></code></td>
                    <td><code><?= e($s['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['title']) ?></td>
                    <td><span class="badge text-bg-light"><?= e($s['room_code'] ?? 'TBA') ?></span></td>
                    <td class="text-muted-sm"><?= e($s['lecturer_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
