<?php
/** Transcript of academic record, on the form the Office of Admissions, Records and General Services prints. */
layout('layouts.document');
section('content');
$printedOn = $printedOn ?? date('Y-m-d');
?>
<div class="toolbar text-center py-3">
    <button class="btn btn-primary btn-sm" onclick="window.print()">Print / save as PDF</button>
    <?php if (!empty($backUrl)): ?><a class="btn btn-light btn-sm" href="<?= e($backUrl) ?>">Back</a><?php endif; ?>
    <?php if (!empty($recordUrl)): ?><a class="btn btn-light btn-sm" href="<?= e($recordUrl) ?>">Semester grade report</a><?php endif; ?>
</div>

<div class="sheet">
    <?php partial('partials.record-letterhead', ['office' => 'OFFICE OF ADMISSIONS, RECORDS AND GENERAL SERVICES']) ?>

    <div class="record-title">TRANSCRIPT OF ACADEMIC RECORD</div>

    <?php partial('partials.transcript-facts', ['student' => $student, 'tx' => $tx, 'printedOn' => $printedOn]) ?>
    <div style="height:10px"></div>
    <?php partial('partials.transcript-table', ['tx' => $tx]) ?>

    <?php if (($tx['terms'] ?? []) !== []): ?>
        <table class="grid-box" style="margin-top:10px">
            <tr>
                <td style="width:34%"><span class="k">Credit hours earned:</span> <b><?= (int) $tx['credits'] ?></b></td>
                <td style="width:33%"><span class="k">Cumulative GPA:</span> <b><?= number_format((float) $tx['cgpa'], 2) ?></b></td>
                <td style="width:33%"><span class="k">Status:</span> <b><?= e(humanize((string) $student['status'])) ?></b></td>
            </tr>
        </table>
    <?php endif; ?>

    <div class="signature" style="margin-top:34px">
        <div>
            <div style="height:40px"></div>
            <div class="line">Registrar<br><span style="font-size:12px;color:#666">Office of Admissions and Records</span></div>
        </div>
        <div>
            <div style="height:40px"></div>
            <div class="line">Official seal<br><span style="font-size:12px;color:#666">Not valid unless sealed</span></div>
        </div>
    </div>

    <div class="doc-foot">
        <span>Transcript printed <?= e(date('m/d/Y', strtotime($printedOn))) ?> &nbsp;|&nbsp; Page 1</span>
        <span class="motto"><?= e((string) setting('institution_motto', '')) ?></span>
        <span><?= e((string) setting('institution_email', '')) ?></span>
    </div>
</div>
<?php endsection(); ?>
