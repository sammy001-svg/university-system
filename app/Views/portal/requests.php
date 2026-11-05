<?php
use App\Services\Academic\RequestService;

layout('layouts.app');
section('content');
$type = (string) old_raw('request_type', 'leave_of_absence');
?>
<div class="page-head"><div><h1>My Requests</h1><p class="lede">Formal requests to the Office of Admission and Records. You are notified of every decision.</p></div></div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><?= icon('clipboard') ?> My requests</div>
            <div class="card-body py-1">
                <?php if ($rows === []): ?><p class="text-muted-sm py-3 mb-0">You have not made any requests.</p><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <div class="el-item">
                        <div class="el-item-main"><a class="el-item-title d-block" href="<?= url('/requests/' . $r['id']) ?>"><?= e(RequestService::TYPES[$r['request_type']]) ?></a>
                            <div class="el-item-meta"><code><?= e($r['reference']) ?></code> &middot; <?= fdate($r['created_at']) ?><?= $r['decision_note'] ? ' &middot; ' . e(str_limit($r['decision_note'], 60)) : '' ?></div></div>
                        <?= status_badge($r['status']) ?>
                        <?php if ($r['status'] === 'submitted'): ?>
                            <form method="post" action="<?= url('/portal/requests/' . $r['id'] . '/cancel') ?>" data-confirm="Withdraw this request?"><?= csrf_field() ?><button class="btn btn-sm btn-link">Withdraw</button></form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><?= icon('plus') ?> New request</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" action="<?= url('/portal/requests') ?>" data-request-form><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label">Request</label><select class="form-select" name="request_type" data-type><?= options(RequestService::TYPES, $type) ?></select></div>
                    <div class="mb-2" data-for="programme_change"><label class="form-label">Move to programme</label><select class="form-select" name="target_program_id"><?= options($programs, old_raw('target_program_id', ''), 'Choose...') ?></select></div>
                    <div class="mb-2" data-for="grade_appeal late_registration credit_transfer"><label class="form-label">Course</label><select class="form-select" name="offering_id"><?= options($courses, old_raw('offering_id', ''), 'Choose...') ?></select></div>
                    <div class="mb-2" data-for="leave_of_absence deferment withdrawal readmission"><label class="form-label">From (date)</label><input class="form-control" type="date" name="effective_date" value="<?= old('effective_date') ?>"></div>
                    <div class="mb-2"><label class="form-label">Explain your request</label><textarea class="form-control" name="details" rows="5" required><?= old('details') ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Supporting document <span class="text-muted-sm">(optional)</span></label><input class="form-control" type="file" name="attachment"></div>
                    <button class="btn btn-primary w-100">Submit request</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
<?php section('scripts'); ?>
<script>
(function () {
  var f = document.querySelector('[data-request-form]'), t = f.querySelector('[data-type]');
  var sync = function () { f.querySelectorAll('[data-for]').forEach(function (el) { el.style.display = el.dataset.for.split(' ').indexOf(t.value) >= 0 ? '' : 'none'; }); };
  t.addEventListener('change', sync); sync();
})();
</script>
<?php endsection(); ?>
