<?php
layout('layouts.app');
section('content');
$a = $audit;
$s = $a['student'];
$label = ['complete' => ['Complete', 'success'], 'in_progress' => ['In progress', 'info'], 'failed' => ['Failed - retake', 'danger'],
          'outstanding' => ['To take', 'light'], 'open' => ['Open', 'light']];
$years = [1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior', 4 => 'Senior', 5 => 'Fifth year'];
?>
<div class="page-head">
    <div>
        <h1>Degree audit</h1>
        <p class="lede"><?= e(trim($s['first_name'] . ' ' . $s['last_name'])) ?> &middot; <?= e($s['admission_number']) ?> &middot;
            <?= e($s['program_name']) ?><?= $s['minor_name'] ? ' &middot; minor ' . e($s['minor_name']) : '' ?> &middot; <?= e($a['standing']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body d-flex align-items-center gap-4">
            <div class="result-ring" style="--pct: <?= $a['percent'] ?>"><div><?= $a['percent'] ?>%</div></div>
            <div>
                <div class="fw-semibold">of the programme complete</div>
                <div class="text-muted-sm"><?= $a['earned'] ?> of <?= $a['required'] ?> credit hours earned</div>
                <div class="text-muted-sm"><?= $a['running'] ?> in progress &middot; <?= $a['remaining'] ?> remaining</div>
            </div>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 h-100">
            <div class="col-6 col-md-3"><div class="mini-stat h-100"><b><?= $a['cgpa'] !== null ? number_format($a['cgpa'], 2) : '&ndash;' ?></b><span>CGPA (min. <?= number_format($a['minCgpa'], 2) ?>)</span></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat h-100"><b><?= $a['counts']['outstanding'] + $a['counts']['failed'] ?></b><span>Courses still needed</span></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat h-100"><b><?= $a['counts']['failed'] ?></b><span>To retake</span></div></div>
            <div class="col-6 col-md-3"><div class="mini-stat h-100"><b><?= $a['termsLeft'] === 0 ? 'Done' : $a['termsLeft'] ?></b><span><?= $a['termsLeft'] === 0 ? 'All credits earned' : 'Semesters to go (est.)' ?></span></div></div>
        </div>
    </div>
</div>

<div class="alert alert-<?= $a['eligible'] ? 'success' : 'info' ?>">
    <?php if ($a['eligible']): ?>
        <strong>Eligible to graduate.</strong> Every requirement is met and the CGPA is at least <?= number_format($a['minCgpa'], 2) ?>.
    <?php else: ?>
        <strong>Not yet eligible to graduate.</strong>
        <?= $a['remaining'] > 0 ? $a['remaining'] . ' credit hours remain' : 'All credits are earned' ?><?= $a['counts']['failed'] > 0 ? ', ' . $a['counts']['failed'] . ' course(s) must be retaken' : '' ?><?= $a['cgpa'] !== null && $a['cgpa'] < $a['minCgpa'] ? ', and the CGPA is below the ' . number_format($a['minCgpa'], 2) . ' required' : '' ?>.
    <?php endif; ?>
</div>

<?php foreach ($a['terms'] as $year => $semesters): ?>
    <div class="card mb-3">
        <div class="card-header"><?= icon('book') ?> Year <?= (int) $year ?> &middot; <?= e($years[$year] ?? '') ?></div>
        <div class="row g-0">
            <?php foreach ($semesters as $semester => $items):
                $done = array_sum(array_map(static fn ($i) => $i['status'] === 'complete' ? $i['credits'] : 0, $items));
                $all  = array_sum(array_column($items, 'credits')); ?>
                <div class="col-lg-6 <?= $semester === 1 ? 'border-end' : '' ?>">
                    <div class="px-3 pt-3 d-flex justify-content-between"><b>Semester <?= (int) $semester ?></b><span class="text-muted-sm"><?= $done ?> / <?= $all ?> credits</span></div>
                    <div class="table-wrap">
                        <table class="table table-sm align-middle mb-2">
                            <tbody>
                            <?php foreach ($items as $item): [$text, $tone] = $label[$item['status']]; ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="<?= $item['row']['course_id'] ? '' : 'fst-italic' ?>"><?= e($item['label']) ?></div>
                                        <?php if ($item['filled_by']): ?><div class="text-muted-sm">met by <?= e($item['filled_by']) ?></div><?php endif; ?>
                                        <?php if ($item['type'] !== 'core'): ?><span class="text-muted-sm"><?= e(ucfirst($item['type'])) ?></span><?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted-sm"><?= $item['credits'] ?></td>
                                    <td class="text-center fw-semibold"><?= e((string) ($item['result']['grade'] ?? '')) ?></td>
                                    <td class="pe-3 text-end"><span class="badge text-bg-<?= $tone ?>"><?= e($text) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($a['terms'] === []): ?>
    <div class="alert alert-warning">This programme has no curriculum on record yet, so the audit cannot be worked out.</div>
<?php endif; ?>

<?php if ($a['extra'] !== []): ?>
    <div class="card">
        <div class="card-header"><?= icon('check') ?> Other passed courses (not counted towards the programme)</div>
        <div class="card-body small"><?= e(implode(', ', array_map(static fn ($r) => $r['code'] . ' ' . $r['title'] . ' (' . $r['grade'] . ')', $a['extra']))) ?></div>
    </div>
<?php endif; ?>
<?php endsection(); ?>
