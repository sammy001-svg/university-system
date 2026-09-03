<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Attendance Records</h1>
        <p class="lede"><?= e($semester['name'] ?? 'Current Semester') ?></p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('attendance') ?> Course Attendance Rates</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th class="text-center">Attended Sessions</th>
                    <th class="text-center">Total Sessions</th>
                    <th class="text-center">Attendance %</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('attendance') ?><p>No attendance records found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($records as $r): 
                $rate = $r['total'] > 0 ? round(($r['attended'] / $r['total']) * 100, 1) : 0;
            ?>
                <tr>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($r['title']) ?></td>
                    <td class="text-center"><?= (int)$r['attended'] ?></td>
                    <td class="text-center"><?= (int)$r['total'] ?></td>
                    <td class="text-center fw-bold <?= $rate < 75 ? 'text-danger' : 'text-success' ?>"><?= $rate ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
