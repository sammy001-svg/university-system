<?php
use App\Controllers\HR\VacancyController;

layout('layouts.app');
section('content');
$v = $vacancy;
?>
<div class="page-head">
    <div><h1><?= e($v['title']) ?></h1>
        <p class="lede"><code><?= e($v['reference']) ?></code> &middot; closes <?= fdate($v['closing_date']) ?> &middot; <?= (int) $v['positions'] ?> position(s) &middot; <?= status_badge($v['status']) ?>
            <?php if ($v['status'] === 'open'): ?>&middot; <a href="<?= url('/careers/' . $v['id']) ?>" target="_blank">public page</a><?php endif; ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/hr/vacancies') ?>">&larr; Vacancies</a>
</div>
<nav class="el-tabs">
    <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= url('/hr/vacancies/' . $v['id'] . '/applicants') ?>">All <span class="badge text-bg-light"><?= array_sum($counts) ?></span></a>
    <?php foreach (VacancyController::STAGES as $s): ?>
        <a class="<?= $status === $s ? 'active' : '' ?>" href="<?= url('/hr/vacancies/' . $v['id'] . '/applicants?status=' . $s) ?>"><?= e(humanize($s)) ?>
            <?php if (!empty($counts[$s])): ?><span class="badge text-bg-light"><?= (int) $counts[$s] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<div class="card">
    <div class="table-wrap"><table class="table align-middle mb-0">
        <thead><tr><th>Applicant</th><th>Qualification</th><th>Applied</th><th>CV</th><th>Stage</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state"><?= icon('users') ?><p>No applicants here.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $a): ?>
            <tr>
                <td><div class="fw-semibold"><?= e($a['full_name']) ?></div><div class="text-muted-sm"><?= e($a['email']) ?> &middot; <?= e((string) $a['phone']) ?></div>
                    <?php if ($a['cover_letter']): ?><details class="small mt-1"><summary>Cover letter</summary><div style="white-space:pre-wrap"><?= e($a['cover_letter']) ?></div></details><?php endif; ?></td>
                <td class="small"><?= e((string) $a['qualification']) ?></td>
                <td class="text-muted-sm"><?= fdate($a['created_at']) ?></td>
                <td><?php if ($a['cv_path']): ?><a class="btn btn-sm btn-light" target="_blank" href="<?= url('/hr/applications/' . $a['id'] . '/cv') ?>"><?= icon('download', 'ico-sm') ?> CV</a><?php endif; ?></td>
                <td style="min-width:260px">
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= url('/hr/applications/' . $a['id']) ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <select class="form-select form-select-sm" name="status"><?= options(array_combine(VacancyController::STAGES, array_map('humanize', VacancyController::STAGES)), $a['status']) ?></select>
                            <input class="form-control form-control-sm" name="notes" value="<?= e((string) $a['notes']) ?>" placeholder="Notes">
                            <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                    <?php else: ?><?= status_badge($a['status']) ?><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
