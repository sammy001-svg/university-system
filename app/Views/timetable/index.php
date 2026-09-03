<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Timetable</h1>
        <p class="lede">Lecture room schedules &amp; class timetables.</p>
    </div>
    <?php if (can('timetable.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/timetable/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Add Timetable Slot
        </a>
    <?php endif; ?>
</div>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Semester</label>
            <select name="semester_id" class="form-select form-select-sm" data-auto-submit>
                <?= options(array_column($semesters, 'name', 'id'), $current['id'] ?? '', 'Select Semester') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Load Timetable</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header"><?= icon('clock') ?> Schedule Slots (<?= count($slots) ?>)</div>
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
                    <th>Type</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($slots)): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('clock') ?><p>No timetable slots configured for this semester.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($slots as $s): ?>
                <tr>
                    <td class="fw-bold text-capitalize"><?= e($s['day_of_week']) ?></td>
                    <td><code><?= e(substr($s['start_time'], 0, 5)) ?> - <?= e(substr($s['end_time'], 0, 5)) ?></code></td>
                    <td><code><?= e($s['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['title']) ?></td>
                    <td><span class="badge text-bg-light"><?= e($s['room_code'] ?? 'TBA') ?></span></td>
                    <td class="text-muted-sm"><?= e($s['lecturer_name'] ?? '—') ?></td>
                    <td><?= e(humanize($s['session_type'])) ?></td>
                    <td class="text-end">
                        <?php if (can('timetable.delete')): ?>
                            <form method="post" action="<?= url('/timetable/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Remove slot?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
