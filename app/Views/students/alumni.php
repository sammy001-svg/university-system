<?php
layout('layouts.app');
section('content');
$total = count($rows);
?>
<div class="page-head">
    <div><h1>Alumni</h1><p class="lede">Graduates of the university, with the career details they share through their alumni profile.</p></div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> Export</a>
        <a class="btn btn-sm btn-light" href="<?= url('/graduation') ?>">Graduation</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $total ?></b><span>Alumni</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $total ? round($employed / $total * 100) : 0 ?>%</b><span>Report being employed</span></div></div>
</div>
<form class="filter-bar" method="get"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Search</label><input class="form-control form-control-sm" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Name, ID, employer or job"></div>
    <div class="col-md-3"><label class="form-label">Programme</label><select class="form-select form-select-sm" name="program_id" data-auto-submit><?= options($programs, $_GET['program_id'] ?? '', 'All') ?></select></div>
    <div class="col-md-2"><label class="form-label">Class of</label><select class="form-select form-select-sm" name="year" data-auto-submit><?= options(array_combine($years, $years) ?: [], $_GET['year'] ?? '', 'All') ?></select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</div></form>
<div class="card">
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Graduate</th><th>Programme</th><th>Graduated</th><th>Now</th><th>Contact</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state"><?= icon('student') ?><p>No alumni yet.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><div class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></div><div class="text-muted-sm"><?= e($r['admission_number']) ?></div></td>
                <td class="small"><?= e($r['program_name']) ?><div class="text-muted-sm"><?= e((string) $r['classification']) ?></div></td>
                <td class="text-muted-sm"><?= fdate($r['graduation_date']) ?></td>
                <td class="small"><?= $r['employer'] ? e(trim($r['job_title'] . ', ' . $r['employer'], ', ')) : '<span class="text-muted">Not shared</span>' ?>
                    <?php if ($r['further_study']): ?><div class="text-muted-sm">Studying: <?= e($r['further_study']) ?></div><?php endif; ?>
                    <?php if ($r['city'] || $r['country']): ?><div class="text-muted-sm"><?= e(trim($r['city'] . ', ' . $r['country'], ', ')) ?></div><?php endif; ?></td>
                <td class="small"><?= e((string) $r['email']) ?><div class="text-muted-sm"><?= e((string) $r['phone']) ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
