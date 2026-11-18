<?php
use App\Core\Session;
use App\Services\Academic\ScheduleConflict;

layout('layouts.app');
section('content');
$s       = $slot;
$clashes = Session::getFlash('clashes') ?: [];
$action  = $isNew ? '/timetable' : '/timetable/' . $s['id'];
$val     = static fn (string $k, $d = '') => old_raw($k, $s[$k] ?? $d);
$time    = static fn (string $k, string $d): string => date('H:i', strtotime((string) old_raw($k, $s[$k] ?? $d)) ?: strtotime($d));
?>
<div class="page-head">
    <div>
        <h1><?= $isNew ? 'Add a timetable slot' : 'Move a timetable slot' ?></h1>
        <p class="lede">A room holds one class at a time, a lecturer teaches one class at a time, and a student attends one class at a time.
            The system checks all three before saving.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/timetable') ?>">&larr; Timetable</a>
</div>

<?php if ($clashes !== []): ?>
    <div class="alert alert-danger">
        <strong>Not scheduled.</strong> This slot clashes with what is already on the timetable:
        <ul class="mb-0 mt-1"><?php foreach ($clashes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= url($action) ?>" class="card" style="max-width:900px" data-slot-form
      data-check="<?= e(url('/timetable/check')) ?>" data-token="<?= e(csrf_token()) ?>" data-slot-id="<?= $isNew ? '' : (int) $s['id'] ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="offering_id">Class <span class="req">*</span></label>
                <select class="form-select" id="offering_id" name="offering_id" required>
                    <?= options(array_column($offerings, 'label', 'id'), $val('offering_id'), 'Choose the class...') ?>
                </select>
                <div class="form-text">Classes of the current semester. The semester is taken from the class.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="session_type">Session type <span class="req">*</span></label>
                <select class="form-select" id="session_type" name="session_type" required>
                    <?= enum_options(['lecture', 'tutorial', 'practical', 'seminar'], $val('session_type', 'lecture')) ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="day_of_week">Day <span class="req">*</span></label>
                <select class="form-select" id="day_of_week" name="day_of_week" required>
                    <?= enum_options(ScheduleConflict::DAYS, $val('day_of_week', 'monday')) ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="start_time">Starts <span class="req">*</span></label>
                <input class="form-control" type="time" id="start_time" name="start_time" value="<?= e($time('start_time', '08:00')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="end_time">Ends <span class="req">*</span></label>
                <input class="form-control" type="time" id="end_time" name="end_time" value="<?= e($time('end_time', '10:00')) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="room_id">Room</label>
                <select class="form-select" id="room_id" name="room_id">
                    <option value="">To be announced</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $val('room_id') === (string) $r['id'] ? 'selected' : '' ?>>
                            <?= e($r['label']) ?><?= $r['status'] !== 'available' ? ' - ' . e(humanize($r['status'])) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="alert mb-0 d-none" data-check-result></div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 align-items-center">
        <button class="btn btn-primary"><?= $isNew ? 'Add slot' : 'Save changes' ?></button>
        <a class="btn btn-light" href="<?= url('/timetable') ?>">Cancel</a>
        <span class="text-muted-sm ms-auto" data-check-note></span>
    </div>
</form>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
  var form = document.querySelector('[data-slot-form]');
  if (!form) { return; }
  var box = form.querySelector('[data-check-result]');
  var note = form.querySelector('[data-check-note]');
  var fields = ['offering_id', 'day_of_week', 'start_time', 'end_time', 'room_id'];
  var timer, last = '';

  var render = function (res) {
    var list = (res.errors || []).concat(res.warnings || []);
    if (!res.offering) { box.className = 'alert mb-0 d-none'; note.textContent = ''; return; }
    if (list.length === 0) {
      box.className = 'alert alert-success mb-0';
      box.textContent = 'That time and room are free.';
      note.textContent = '';
      return;
    }
    box.className = 'alert mb-0 alert-' + ((res.errors || []).length ? 'danger' : 'warning');
    box.innerHTML = '<strong>' + ((res.errors || []).length ? 'This slot clashes:' : 'Worth checking:') + '</strong><ul class="mb-0 mt-1"></ul>';
    var ul = box.querySelector('ul');
    list.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    note.textContent = (res.errors || []).length ? 'Change the time or the room to continue.' : '';
  };

  var check = function () {
    var data = { _token: form.dataset.token, slot_id: form.dataset.slotId };
    fields.forEach(function (f) { data[f] = form.querySelector('[name="' + f + '"]').value; });
    if (!data.offering_id) { render({ offering: false }); return; }
    var key = JSON.stringify(data);
    if (key === last) { return; }
    last = key;
    fetch(form.dataset.check, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json(); })
      .then(function (res) { render(Object.assign({ offering: true }, res)); })
      .catch(function () { /* the server checks again when the form is submitted */ });
  };

  fields.forEach(function (f) {
    form.querySelector('[name="' + f + '"]').addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 150); });
  });
  check();
})();
</script>
<?php endsection(); ?>
