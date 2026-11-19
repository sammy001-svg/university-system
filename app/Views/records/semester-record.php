<?php
/** Student records: one semester's grade report, on the Office of Admission and Records form. */
use App\Services\Academic\AcademicRecord;

layout('layouts.document');
section('content');
$num  = static fn (float $v): string => $v === floor($v) ? (string) (int) $v : number_format($v, 2);
$rows = $record['rows'] ?? [];
$fill = max(0, 8 - count($rows));
?>
<div class="toolbar text-center py-3">
    <?php if (count($semesters) > 1): ?>
        <form method="get" action="<?= e($baseUrl) ?>" class="d-inline-block me-2">
            <select name="semester_id" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                <?= options(array_column($semesters, 'label', 'id'), $semesterId) ?>
            </select>
        </form>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" onclick="window.print()">Print / save as PDF</button>
    <?php if (!empty($transcriptUrl)): ?><a class="btn btn-light btn-sm" href="<?= e($transcriptUrl) ?>">Full transcript</a><?php endif; ?>
    <?php if (!empty($backUrl)): ?><a class="btn btn-light btn-sm" href="<?= e($backUrl) ?>">Back</a><?php endif; ?>
</div>

<div class="sheet">
    <?php partial('partials.record-letterhead', ['office' => 'Office of Admission and Records', 'compact' => true]) ?>

    <?php if ($record === null): ?>
        <p class="body-text text-center" style="margin-top:40px">No published results on record for this student.</p>
    <?php else: ?>
        <table class="grid-box">
            <tr>
                <td style="width:40%"><span class="k">Student:</span> <b><?= e(AcademicRecord::formalName($student)) ?></b></td>
                <td style="width:32%"><span class="k">ID:</span> <b><?= e($student['admission_number']) ?></b></td>
                <td style="width:28%"><span class="k">Sex:</span> <b><?= e(ucfirst((string) ($student['gender'] ?? '')) ?: 'N/A') ?></b></td>
            </tr>
            <tr>
                <td><span class="k">Status:</span> <b><?= e($record['standing']) ?></b></td>
                <td><span class="k">Sem:</span> <b><?= e($record['semester']['name']) ?></b></td>
                <td><span class="k">Year:</span> <b><?= e($record['semester']['academic_year']) ?></b></td>
            </tr>
            <tr>
                <td><span class="k">Major:</span> <b><?= e($student['program_name']) ?></b></td>
                <td><span class="k">College:</span> <b><?= e($student['faculty_name']) ?></b></td>
                <td><span class="k">Tel:</span> <b><?= e((string) ($student['phone'] ?? '') ?: 'N/A') ?></b></td>
            </tr>
            <tr>
                <td><span class="k">Minor:</span> <b><?= e((string) ($student['minor_name'] ?? '') ?: 'N/A') ?></b></td>
                <td colspan="2"></td>
            </tr>
        </table>

        <table class="rec-table">
            <thead>
            <tr>
                <th style="width:6%">No</th>
                <th style="width:17%">Course Code</th>
                <th>Course Title</th>
                <th style="width:13%">Credit Hours</th>
                <th style="width:10%">Grade</th>
                <th style="width:11%">Point</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td class="c"><?= $i + 1 ?></td>
                    <td><?= e($r['code']) ?></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="c"><?= (int) $r['credit_hours'] ?></td>
                    <td class="c"><?= e((string) ($r['grade'] ?? '')) ?></td>
                    <td class="c"><?= e(($r['outcome'] ?? '') === 'audit' ? 'AU' : $num((float) $r['quality_points'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $fill; $i++): ?>
                <tr><td class="c"><?= count($rows) + $i + 1 ?></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
            <tr class="sum">
                <td colspan="3" class="r">Total</td>
                <td class="c"><?= (int) $record['credits'] ?></td>
                <td></td>
                <td class="c"><?= e($num((float) $record['points'])) ?></td>
            </tr>
            <tr class="sum">
                <td colspan="3" class="r">GPA</td>
                <td class="c"><?= (int) $record['credits'] ?></td>
                <td></td>
                <td class="c"><?= number_format((float) $record['gpa'], 2) ?></td>
            </tr>
            </tbody>
        </table>

        <div class="sig-lines">
            <div>Admission Office: <span class="line"></span> Date: <span class="line" style="min-width:110px"></span></div>
            <div>Dean: <span class="line" style="min-width:187px"></span> Date: <span class="line" style="min-width:110px"></span></div>
            <div>Dept. Chair Sign <span class="line"></span></div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
