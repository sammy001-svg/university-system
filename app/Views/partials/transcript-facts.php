<?php
/**
 * The bordered three-by-three block of facts at the head of a transcript.
 * Expects $student (App\Services\Academic\AcademicRecord::student) and $tx (::transcript).
 */
use App\Services\Academic\AcademicRecord;

$span = static function (?string $from, ?string $to): string {
    if ($from === null) { return 'N/A'; }
    return date('M Y', strtotime($from)) . ' - ' . ($to !== null ? date('M Y', strtotime($to)) : 'to date');
};
?>
<table class="grid-box">
    <tr>
        <td style="width:28%"><span class="k">Student ID#:</span><br><b><?= e($student['admission_number']) ?></b></td>
        <td style="width:44%"><span class="k">Student Name:</span><br><b><?= e(AcademicRecord::formalName($student)) ?></b></td>
        <td style="width:28%"><span class="k">Gender:</span><br><b><?= e(ucfirst((string) ($student['gender'] ?? '')) ?: 'N/A') ?></b></td>
    </tr>
    <tr>
        <td><span class="k">Date of Birth:</span><br><b><?= $student['date_of_birth'] ? e(date('F d, Y', strtotime((string) $student['date_of_birth']))) : 'N/A' ?></b></td>
        <td><span class="k">Program:</span><br><b><?= e($student['program_name']) ?></b></td>
        <td><span class="k">Printed on:</span><br><b><?= e(date('F d, Y', strtotime((string) ($printedOn ?? date('Y-m-d'))))) ?></b></td>
    </tr>
    <tr>
        <td><span class="k">Date Enrolled:</span><br><b><?= $student['admission_date'] ? e(date('F d, Y', strtotime((string) $student['admission_date']))) : 'N/A' ?></b></td>
        <td><span class="k">Duration:</span><br><b><?= e($span($tx['from'] ?? null, $tx['to'] ?? null)) ?></b></td>
        <td><span class="k">Minor:</span><br><b><?= e((string) ($student['minor_name'] ?? '') ?: 'N/A') ?></b></td>
    </tr>
</table>
