<?php
use App\Controllers\StudentLife\ClubController;

layout('layouts.app');
section('content');
$roles = array_combine(ClubController::ROLES, array_map('humanize', ClubController::ROLES));
?>
<div class="page-head">
    <div><h1><?= e($club['name']) ?></h1><p class="lede"><?= e(humanize($club['category'])) ?> &middot; <?= count($rows) ?> members<?= $club['meeting_schedule'] ? ' &middot; ' . e($club['meeting_schedule']) : '' ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/clubs') ?>">&larr; Clubs</a>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Student</th><th>Programme</th><th>Role</th><th>Joined</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state"><?= icon('users') ?><p>No members yet.</p></div></td></tr><?php endif; ?>
                <?php foreach ($rows as $m): ?>
                    <tr><td><div class="fw-semibold"><?= e($m['last_name'] . ', ' . $m['first_name']) ?></div><div class="text-muted-sm"><?= e($m['admission_number']) ?> &middot; <?= e((string) $m['phone']) ?></div></td>
                        <td class="small"><?= e($m['program_name']) ?></td>
                        <td><?= $m['role'] === 'member' ? 'Member' : '<span class="badge text-bg-primary">' . e(humanize($m['role'])) . '</span>' ?></td>
                        <td class="text-muted-sm"><?= fdate($m['joined_on']) ?></td>
                        <?php if ($canManage): ?>
                            <td class="text-end"><form class="d-inline" method="post" action="<?= url('/clubs/' . $club['id'] . '/members') ?>" data-confirm="Remove from the club?"><?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int) $m['student_id'] ?>"><input type="hidden" name="remove" value="1"><button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button></form></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="col-xl-4">
            <div class="card"><div class="card-header"><?= icon('plus') ?> Add a member or set an officer</div><div class="card-body">
                <form method="post" action="<?= url('/clubs/' . $club['id'] . '/members') ?>"><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label">Student</label><select class="form-select" name="student_id" required><?= options($students, '', 'Choose...') ?></select></div>
                    <div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role"><?= options($roles, 'member') ?></select></div>
                    <button class="btn btn-primary w-100">Save</button>
                </form></div></div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
