<?php
use App\Services\Academic\RequestService;

layout('layouts.app');
section('content');
$open = in_array($r['status'], ['submitted', 'under_review'], true);
?>
<div class="page-head">
    <div><h1><?= e(RequestService::TYPES[$r['request_type']]) ?></h1>
        <p class="lede"><code><?= e($r['reference']) ?></code> &middot; submitted <?= fdatetime($r['created_at']) ?> &middot; <?= status_badge($r['status']) ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url($isOwner ? '/portal/requests' : '/requests') ?>">&larr; Back</a>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-header"><?= icon('clipboard') ?> The request</div><div class="card-body">
            <div style="white-space:pre-wrap"><?= e($r['details']) ?></div>
            <dl class="row small mt-3 mb-0">
                <?php if ($r['effective_date']): ?><dt class="col-sm-4 text-muted fw-normal">From</dt><dd class="col-sm-8"><?= fdate($r['effective_date']) ?></dd><?php endif; ?>
                <?php if ($r['course_label']): ?><dt class="col-sm-4 text-muted fw-normal">Course</dt><dd class="col-sm-8"><?= e($r['course_label']) ?></dd><?php endif; ?>
                <?php if ($r['target_program_name']): ?><dt class="col-sm-4 text-muted fw-normal">Move to</dt><dd class="col-sm-8"><?= e($r['target_program_name']) ?></dd><?php endif; ?>
                <?php if ($r['attachment_path']): ?><dt class="col-sm-4 text-muted fw-normal">Attachment</dt><dd class="col-sm-8"><a target="_blank" href="<?= url('/requests/' . $r['id'] . '/attachment') ?>"><?= e($r['attachment_name']) ?></a></dd><?php endif; ?>
            </dl>
        </div></div>
        <?php if (!$open): ?>
            <div class="card"><div class="card-header"><?= icon('check') ?> Decision</div><div class="card-body">
                <p class="mb-1"><b><?= e(ucfirst($r['status'])) ?></b><?= $r['decided_at'] ? ' on ' . fdate($r['decided_at']) : '' ?><?= $r['reviewer_name'] ? ' by ' . e($r['reviewer_name']) : '' ?>.</p>
                <?php if ($r['decision_note']): ?><div style="white-space:pre-wrap"><?= e($r['decision_note']) ?></div><?php endif; ?>
            </div></div>
        <?php endif; ?>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-header"><?= icon('student') ?> Student</div><div class="card-body small">
            <div class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></div>
            <div><?= e($r['admission_number']) ?> &middot; Year <?= (int) $r['year_of_study'] ?> &middot; <?= e($r['student_status']) ?></div>
            <div class="text-muted"><?= e($r['program_name']) ?></div><div class="text-muted"><?= e((string) $r['email']) ?> &middot; <?= e((string) $r['phone']) ?></div>
            <?php if (can('students.view')): ?><a class="d-block mt-2" href="<?= url('/students/' . $r['student_id']) ?>">Student record</a><?php endif; ?>
            <?php if ($history !== []): ?><div class="mt-2 text-muted">Earlier requests: <?= e(implode(', ', array_map(static fn ($h) => RequestService::TYPES[$h['request_type']] . ' (' . $h['status'] . ')', $history))) ?></div><?php endif; ?>
        </div></div>
        <?php if ($canReview && $open): ?>
            <div class="card"><div class="card-header"><?= icon('check') ?> Decide</div><div class="card-body">
                <?php if (isset(RequestService::EFFECTS[$r['request_type']])): ?><div class="alert alert-info py-2 small">On approval: <?= e(RequestService::EFFECTS[$r['request_type']]) ?></div><?php endif; ?>
                <form method="post" action="<?= url('/requests/' . $r['id'] . '/decide') ?>"><?= csrf_field() ?>
                    <label class="form-label small">Note to the student</label>
                    <textarea class="form-control mb-2" name="note" rows="3" placeholder="Required when rejecting"></textarea>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success flex-fill" name="decision" value="approve">Approve</button>
                        <button class="btn btn-outline-danger flex-fill" name="decision" value="reject">Reject</button>
                    </div>
                    <?php if ($r['status'] === 'submitted'): ?><button class="btn btn-sm btn-link w-100 mt-1" name="decision" value="review">Mark as under review</button><?php endif; ?>
                </form>
            </div></div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
