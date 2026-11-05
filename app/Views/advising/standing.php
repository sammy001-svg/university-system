<?php
use App\Controllers\Students\AdvisingController as A;

layout('layouts.app');
section('content');
$tabs = ['probation' => 'Academic probation', 'warning' => 'Academic warning', 'deans_list' => "Dean's List"];
$help = [
    'probation'  => 'Cumulative GPA below ' . number_format($minCgpa, 2) . '. These students need an advising meeting and a study plan.',
    'warning'    => 'Last semester GPA below ' . number_format($minCgpa, 2) . ' while the cumulative GPA is still above it.',
    'deans_list' => 'Last semester GPA of ' . number_format(A::DEANS_LIST_GPA, 2) . ' or higher on ' . A::DEANS_LIST_CREDITS . ' or more credit hours.',
];
$rows = $groups[$list] ?? [];
?>
<div class="page-head">
    <div><h1>Academic Standing</h1><p class="lede">Based on published results.</p></div>
    <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> Export this list</a>
</div>
<nav class="el-tabs">
    <?php foreach ($tabs as $k => $l): ?>
        <a class="<?= $list === $k ? 'active' : '' ?>" href="<?= url('/advising/standing?list=' . $k) ?>"><?= e($l) ?>
            <span class="badge text-bg-<?= $k === 'deans_list' ? 'success' : ($k === 'probation' ? 'danger' : 'warning') ?>"><?= count($groups[$k]) ?></span></a>
    <?php endforeach; ?>
</nav>
<p class="text-muted-sm"><?= e($help[$list] ?? '') ?></p>
<div class="card">
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Student</th><th>Programme</th><th class="text-end">CGPA</th><th class="text-end">Last semester</th><th>Advisor</th><th></th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><?= icon('check') ?><p>No students on this list.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div><div class="text-muted-sm"><?= e($r['admission_number']) ?> &middot; Year <?= (int) $r['year_of_study'] ?></div></td>
                <td class="small"><?= e($r['program_name']) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float) $r['cgpa'], 2) ?></td>
                <td class="text-end"><?= $r['last_gpa'] !== null ? number_format((float) $r['last_gpa'], 2) : '' ?><div class="text-muted-sm"><?= e((string) $r['last_term']) ?></div></td>
                <td class="small"><?= $r['advisor_name'] ? e($r['advisor_name']) : '<span class="text-danger">None</span>' ?></td>
                <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/advising/students/' . $r['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
