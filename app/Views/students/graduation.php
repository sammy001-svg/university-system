<?php
use App\Services\Academic\GraduationService;

layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div><h1>Graduation</h1><p class="lede">Graduation applications, clearance and approval, classification and certificate numbers.</p></div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> Graduation list</a>
        <a class="btn btn-sm btn-light" href="<?= url('/alumni') ?>">Alumni</a>
    </div>
</div>

<form class="filter-bar" method="get"><div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Academic year</label><select class="form-select form-select-sm" name="year" data-auto-submit><?= options($years, $yearId) ?></select></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select form-select-sm" name="status" data-auto-submit><?= options(GraduationService::STATUSES, $status, 'All') ?></select></div>
</div></form>

<div class="row g-3 mb-3">
    <?php foreach (GraduationService::STATUSES as $k => $l): ?>
        <div class="col"><a class="mini-stat" href="<?= url('/graduation?year=' . $yearId . '&status=' . $k) ?>"><b><?= (int) ($counts[$k] ?? 0) ?></b><span><?= e($l) ?></span></a></div>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= url('/graduation/bulk/advance') ?>" id="bulk">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center"><?= icon('student') ?> Graduands <span class="badge text-bg-light"><?= count($rows) ?></span>
            <?php if ($canManage && $rows !== []): ?>
                <div class="ms-auto d-flex gap-1 align-items-center">
                    <select class="form-select form-select-sm" name="action" style="width:auto">
                        <option value="clear">Clear ticked</option><option value="approve">Approve ticked</option><option value="graduate">Graduate ticked</option><option value="defer">Defer ticked</option></select>
                    <input class="form-control form-control-sm" type="date" name="graduation_date" value="<?= date('Y-m-d') ?>" title="Graduation date (for Graduate)" style="width:auto">
                    <input class="form-control form-control-sm" name="remarks" placeholder="Remarks (needed to defer)" style="width:200px">
                    <button class="btn btn-sm btn-primary" data-confirm-bulk>Apply</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap"><table class="table table-hover align-middle mb-0">
            <thead><tr><?php if ($canManage): ?><th style="width:1%"><input class="form-check-input" type="checkbox" onclick="document.querySelectorAll('[data-g]').forEach(c => c.checked = this.checked)"></th><?php endif; ?>
                <th>Student</th><th>Programme</th><th class="text-end">CGPA</th><th>Clearance</th><th>Status</th><th>Classification / certificate</th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="8"><div class="empty-state"><?= icon('student') ?><p>No graduation applications for this year.</p></div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): $cgpa = $r['status'] === 'graduated' ? (float) $r['final_cgpa'] : (float) $r['cgpa']; ?>
                <tr>
                    <?php if ($canManage): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-g></td><?php endif; ?>
                    <td><div class="fw-semibold"><?= e($r['name_on_certificate'] ?: $r['first_name'] . ' ' . $r['last_name']) ?></div><div class="text-muted-sm"><?= e($r['admission_number']) ?> &middot; applied <?= fdate($r['applied_at']) ?></div></td>
                    <td class="small"><?= e($r['program_name']) ?></td>
                    <td class="text-end fw-semibold <?= $cgpa < 2 ? 'text-danger' : '' ?>"><?= number_format($cgpa, 2) ?></td>
                    <td><?= (int) $r['open_clearances'] > 0 ? '<span class="badge text-bg-warning">' . (int) $r['open_clearances'] . ' pending</span>' : '<span class="badge text-bg-success">Clear</span>' ?></td>
                    <td><?= status_badge(['pending' => 'submitted', 'cleared' => 'processing', 'approved' => 'approved', 'graduated' => 'completed', 'deferred' => 'deferred'][$r['status']]) ?>
                        <?php if ($r['remarks']): ?><div class="text-muted-sm"><?= e($r['remarks']) ?></div><?php endif; ?></td>
                    <td class="small"><?= $r['classification'] ? e($r['classification']) . '<div class="text-muted-sm">' . e((string) $r['certificate_no']) . ' &middot; ' . fdate($r['graduation_date']) . '</div>' : '' ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/students/' . $r['student_id'] . '/degree-audit') ?>">Audit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </div>
</form>

<?php if ($canManage && $candidates !== []): ?>
    <form method="post" action="<?= url('/graduation/add') ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><?= icon('plus') ?> Final-year students not on the list <span class="badge text-bg-light"><?= count($candidates) ?></span>
            <button class="btn btn-sm btn-outline-primary ms-auto">Add ticked to the list</button></div>
        <div class="table-wrap" style="max-height:340px"><table class="table table-sm mb-0">
            <tbody>
            <?php foreach ($candidates as $c): ?>
                <tr><td style="width:1%"><input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= (int) $c['id'] ?>"></td>
                    <td><?= e($c['last_name'] . ', ' . $c['first_name']) ?> <span class="text-muted-sm"><?= e($c['admission_number']) ?></span></td>
                    <td class="small"><?= e($c['program_name']) ?></td><td class="text-muted-sm">Year <?= (int) $c['year_of_study'] ?></td>
                    <td class="text-end"><a class="small" href="<?= url('/students/' . $c['id'] . '/degree-audit') ?>">Audit</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </form>
<?php endif; ?>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
document.querySelectorAll('[data-confirm-bulk]').forEach(function (b) {
  b.addEventListener('click', function (e) {
    var n = document.querySelectorAll('[data-g]:checked').length;
    if (!n) { e.preventDefault(); alert('Tick at least one graduand.'); return; }
    var sel = b.form.querySelector('select[name="action"]');
    if (!confirm('Apply "' + sel.options[sel.selectedIndex].text + '" to ' + n + ' graduand(s)?')) { e.preventDefault(); }
  });
});
</script>
<?php endsection(); ?>
