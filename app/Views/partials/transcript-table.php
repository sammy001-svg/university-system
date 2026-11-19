<?php
/**
 * The body of a transcript: every published course by term, each term's GPA, and the
 * cumulative GPA where an academic year closes. Expects $tx (AcademicRecord::transcript).
 */
$num = static fn (float $v): string => $v === floor($v) ? (string) (int) $v : number_format($v, 2);
?>
<table class="tx-table">
    <thead>
    <tr>
        <th style="width:14%">SEMESTER</th>
        <th style="width:14%">COURSE NO</th>
        <th>COURSE TITLE</th>
        <th style="width:10%">CREDIT HOUR</th>
        <th style="width:11%">LETTER GRADE</th>
        <th style="width:11%">GRADE POINT</th>
    </tr>
    </thead>
    <tbody>
    <?php if (($tx['terms'] ?? []) === []): ?>
        <tr><td colspan="6" class="c" style="padding:18px">No published results on record.</td></tr>
    <?php endif; ?>
    <?php foreach ($tx['terms'] as $term): $rows = $term['rows']; $first = true; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <?php if ($first): ?>
                    <td class="term" rowspan="<?= count($rows) ?>"><?= e($term['label']) ?></td>
                <?php endif; $first = false; ?>
                <td><?= e($r['code']) ?></td>
                <td><?= e($r['title']) ?></td>
                <td class="c"><?= (int) $r['credit_hours'] ?></td>
                <td class="c"><?= e((string) ($r['grade'] ?? '')) ?></td>
                <td class="c"><?= e(($r['outcome'] ?? '') === 'audit' ? 'AU' : $num((float) $r['quality_points'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="gpa">
            <td colspan="3" class="label">Semester GPA: <?= number_format($term['gpa'], 2) ?></td>
            <td class="c"><?= (int) $term['credits'] ?></td>
            <td></td>
            <td class="c"><?= e($num((float) $term['points'])) ?></td>
        </tr>
        <?php if ($term['year_end']): ?>
            <tr class="gpa">
                <td colspan="3" class="label">Cumulative GPA: <?= number_format($term['cgpa'], 2) ?></td>
                <td class="c"><?= (int) $term['cum_credits'] ?></td>
                <td></td>
                <td class="c"><?= e($num((float) $term['cum_points'])) ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>
