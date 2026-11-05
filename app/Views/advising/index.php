<?php
layout('layouts.app');
section('content');
$unassignedTotal = array_sum(array_column($unassigned, 'n'));
?>
<div class="page-head">
    <div><h1>Academic Advising</h1><p class="lede">Every student has an academic advisor who follows their progress and meets them each semester.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= url('/advising/standing') ?>">Academic standing</a>
        <a class="btn btn-sm btn-light" href="<?= url('/advising/mine') ?>">My advisees</a></div>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><?= icon('users') ?> Advisors <span class="badge text-bg-light"><?= count($advisors) ?></span></div>
            <div class="table-wrap"><table class="table align-middle mb-0">
                <thead><tr><th>Advisor</th><th class="text-center">Advisees</th><th>Last advising meeting</th></tr></thead>
                <tbody>
                <?php if ($advisors === []): ?><tr><td colspan="3"><div class="empty-state py-4"><p>No advisors assigned yet.</p></div></td></tr><?php endif; ?>
                <?php foreach ($advisors as $a): ?>
                    <tr><td class="fw-semibold"><?= e($a['name']) ?><div class="text-muted-sm fw-normal"><?= e((string) $a['designation']) ?></div></td>
                        <td class="text-center"><?= (int) $a['advisees'] ?></td><td class="text-muted-sm"><?= $a['last_meeting'] ? fdate($a['last_meeting']) : 'None recorded' ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><?= icon('student') ?> Without an advisor <span class="badge text-bg-<?= $unassignedTotal ? 'warning' : 'success' ?>"><?= $unassignedTotal ?></span></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($unassigned as $u): ?><li class="list-group-item d-flex justify-content-between"><?= e($u['name']) ?><b><?= (int) $u['n'] ?></b></li><?php endforeach; ?>
                <?php if ($unassigned === []): ?><li class="list-group-item text-muted">Every active student has an advisor.</li><?php endif; ?>
            </ul>
        </div>
        <?php if ($canManage): ?>
            <div class="card">
                <div class="card-header"><?= icon('plus') ?> Assign an advisor</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/advising/assign') ?>"><?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Students in programme</label><select class="form-select" name="program_id" required><?= options($programs, '', 'Choose...') ?></select></div>
                        <div class="mb-2"><label class="form-label">Year of study</label><select class="form-select" name="year_of_study"><?= options([1 => 'Year 1', 2 => 'Year 2', 3 => 'Year 3', 4 => 'Year 4', 5 => 'Year 5'], '', 'All years') ?></select></div>
                        <div class="mb-2"><label class="form-label">Advisor</label><select class="form-select" name="advisor_id" required><?= options($staff, '', 'Choose...') ?></select></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="only_unassigned" value="1" id="ou" checked><label class="form-check-label" for="ou">Only students without an advisor</label></div>
                        <button class="btn btn-primary w-100">Assign</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
