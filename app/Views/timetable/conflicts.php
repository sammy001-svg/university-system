<?php
layout('layouts.app');
section('content');
$tone = ['Room double-booked' => 'danger', 'Lecturer double-booked' => 'danger', 'Students double-booked' => 'danger',
         'Class scheduled twice' => 'danger', 'Room too small' => 'warning'];
$slotLine = static function (array $s): string {
    return '<div class="fw-semibold">' . e($s['code'] . ' ' . $s['title']) . ' <span class="text-muted-sm">section ' . e($s['section']) . '</span></div>'
        . '<div class="text-muted-sm">' . e(ucfirst($s['day_of_week'])) . ' ' . e(date('H:i', strtotime($s['start_time']))) . '-' . e(date('H:i', strtotime($s['end_time'])))
        . ' &middot; ' . e($s['room_code'] ? $s['room_code'] . ' ' . $s['room_name'] : 'no room')
        . ($s['lecturer_name'] ? ' &middot; ' . e($s['lecturer_name']) : '') . '</div>';
};
?>
<div class="page-head">
    <div>
        <h1>Timetable Conflicts</h1>
        <p class="lede">Clashes already on the timetable. New slots are refused at the point of saving, so anything here was
            scheduled before the checks were switched on, or the rooms and registrations have changed since.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/timetable?semester_id=' . $semesterId) ?>">&larr; Timetable</a>
</div>

<form class="filter-bar mb-3" method="get"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Semester</label>
        <select class="form-select form-select-sm" name="semester_id" data-auto-submit><?= options(array_column($semesters, 'name', 'id'), $semesterId) ?></select></div>
</div></form>

<?php if ($conflicts === []): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('check') ?>
        <p>No clashes: no room, lecturer or student is double-booked in this semester.</p></div></div></div>
<?php else: ?>
    <div class="alert alert-warning"><strong><?= count($conflicts) ?></strong> clash(es) found. Move one of the two classes in each row to resolve it.</div>
    <div class="card">
        <div class="table-wrap">
            <table class="table align-middle mb-0">
                <thead><tr><th>Problem</th><th>Class</th><th>Clashes with</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($conflicts as $c): ?>
                    <tr>
                        <td><span class="badge text-bg-<?= $tone[$c['type']] ?? 'secondary' ?>"><?= e($c['type']) ?></span>
                            <div class="text-muted-sm mt-1"><?= e($c['detail']) ?></div></td>
                        <td><?= $slotLine($c['a']) ?></td>
                        <td><?= $c['b'] === null ? '<span class="text-muted">&ndash;</span>' : $slotLine($c['b']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if (can('timetable.edit')): ?>
                                <a class="btn btn-sm btn-light" href="<?= url('/timetable/' . $c['a']['id'] . '/edit') ?>">Move first</a>
                                <?php if ($c['b'] !== null): ?>
                                    <a class="btn btn-sm btn-light" href="<?= url('/timetable/' . $c['b']['id'] . '/edit') ?>">Move second</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php endsection(); ?>
