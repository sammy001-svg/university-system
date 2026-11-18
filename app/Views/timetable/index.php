<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Timetable</h1>
        <p class="lede">Lecture room schedules &amp; class timetables.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm <?= $clashes ? 'btn-outline-danger' : 'btn-light' ?>" href="<?= url('/timetable/conflicts?semester_id=' . ($current['id'] ?? '')) ?>">
            <?= icon('shield', 'ico-sm') ?> Conflicts<?= $clashes ? ' <span class="badge text-bg-danger">' . $clashes . '</span>' : '' ?>
        </a>
        <?php if (can('timetable.create')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/timetable/create') ?>">
                <?= icon('plus', 'ico-sm') ?> Add Timetable Slot
            </a>
        <?php endif; ?>
    </div>
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
                    <th>Section</th>
                    <th>Room</th>
                    <th>Lecturer</th>
                    <th>Type</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($slots)): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('clock') ?><p>No timetable slots configured for this semester.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($slots as $s): ?>
                <tr>
                    <td class="fw-bold text-capitalize"><?= e($s['day_of_week']) ?></td>
                    <td><code><?= e(substr($s['start_time'], 0, 5)) ?> - <?= e(substr($s['end_time'], 0, 5)) ?></code></td>
                    <td><code><?= e($s['code']) ?></code></td>
                    <td class="fw-semibold"><?= e($s['title']) ?></td>
                    <td class="text-muted-sm"><?= e($s['section']) ?></td>
                    <td><span class="badge text-bg-light"><?= e($s['room_code'] ?? 'TBA') ?></span></td>
                    <td class="text-muted-sm"><?= e($s['lecturer_name'] ?? '—') ?></td>
                    <td><?= e(humanize($s['session_type'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('timetable.edit')): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/timetable/' . $s['id'] . '/edit') ?>" title="Move this class"><?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
                        <?php if (can('timetable.delete')): ?>
                            <form class="d-inline" method="post" action="<?= url('/timetable/' . $s['id']) ?>" data-confirm="Remove this slot from the timetable?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
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
