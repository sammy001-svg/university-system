<?php layout('layouts.app'); section('content'); ?>
<?php
$yearNames = [1 => 'Freshman – Year One', 2 => 'Sophomore – Year Two', 3 => 'Junior – Year Three', 4 => 'Senior – Year Four', 5 => 'Year Five', 6 => 'Year Six'];
$typeBadge = static function (array $row): string {
    return match ($row['course_type']) {
        'minor'         => '<span class="badge text-bg-secondary">Minor</span>',
        'elective'      => '<span class="badge text-bg-info">Elective</span>',
        'concentration' => '<span class="badge text-bg-warning">Content area</span>',
        'common'        => '<span class="badge text-bg-light">General</span>',
        'audit'         => '<span class="badge text-bg-light">Audit</span>',
        default         => '<span class="badge text-bg-primary">Core</span>',
    };
};
$notes = 0;
foreach ($curriculum as $semesters) {
    foreach ($semesters as $rows) {
        $notes += count(array_filter(array_column($rows, 'note')));
    }
}
foreach ($options as $lists) {
    foreach ($lists as $rows) {
        $notes += count(array_filter(array_column($rows, 'note')));
    }
}
$required = $program['total_credit_hours'] !== null ? (int) $program['total_credit_hours'] : null;

/** Render one plan or option row: course or slot, with its source note. */
$describe = static function (array $row): string {
    $html = $row['course_id'] !== null
        ? '<code>' . e($row['code']) . '</code> <span class="fw-semibold">' . e($row['title']) . '</span>'
        : '<span class="fst-italic text-muted">' . e($row['slot_label']) . '</span>';
    if (!empty($row['note'])) {
        $html .= '<div class="text-warning-emphasis small mt-1">' . icon('clock', 'ico-sm') . ' ' . e($row['note']) . '</div>';
    }
    return $html;
};
?>
<div class="page-head">
    <div>
        <h1><?= e($program['name']) ?></h1>
        <p class="lede">
            <?= e($program['code']) ?> &middot; <?= e($program['award'] ?? '') ?> &middot;
            <?= e($program['department_name']) ?>, <?= e($program['faculty_name']) ?>
        </p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/academics/programs') ?>">&larr; Back to Programmes</a>
</div>

<div class="row g-3 mb-4">
    <?php $tiles = [
        ['Level', humanize($program['level']), 'book'],
        ['Duration', rtrim(rtrim((string) $program['duration_years'], '0'), '.') . ' years', 'calendar'],
        ['Credits for Graduation', $required !== null ? (string) $required : '—', 'check'],
        ['Credits in This Plan', (string) $planCredits, 'clipboard'],
    ];
    foreach ($tiles as [$label, $value, $iconName]): ?>
        <div class="col-6 col-xl-3">
            <div class="stat"><span class="stat-icon"><?= icon($iconName, 'ico-lg') ?></span>
                <div><div class="stat-value"><?= e($value) ?></div><div class="stat-label"><?= e($label) ?></div></div></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($program['description'])): ?>
    <div class="alert alert-info"><?= e($program['description']) ?></div>
<?php endif; ?>
<?php if ($required !== null && $planCredits > 0 && $planCredits !== $required): ?>
    <div class="alert alert-warning">The semesters below add up to <strong><?= $planCredits ?></strong> credit hours, but the programme requires <strong><?= $required ?></strong>. Check the curriculum against the official document.</div>
<?php endif; ?>
<?php if ($notes > 0): ?>
    <div class="alert alert-warning"><?= icon('clock') ?> <?= $notes ?> item<?= $notes === 1 ? '' : 's' ?> below carr<?= $notes === 1 ? 'ies' : 'y' ?> a note where the source document was corrected or is unclear. Codes ending in “B” or containing “TBA” are provisional.</div>
<?php endif; ?>

<?php if (empty($curriculum)): ?>
    <div class="card mb-4"><div class="card-body"><div class="empty-state"><?= icon('course') ?><p>No curriculum has been published for this programme yet.</p></div></div></div>
<?php endif; ?>

<?php foreach ($curriculum as $year => $semesters): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold"><?= icon('calendar') ?> <?= e($yearNames[$year] ?? 'Year ' . $year) ?></div>
    <div class="row g-0">
        <?php foreach ($semesters as $semester => $rows): ?>
        <div class="col-lg-6 <?= $semester > 1 ? 'border-start' : '' ?>">
            <div class="px-3 pt-3 fw-semibold"><?= $semester === 1 ? '1st' : ($semester === 2 ? '2nd' : $semester . 'th') ?> Semester, Year <?= (int) $year ?></div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Course</th><th class="text-center">Credits</th><th></th><?php if (can('academics.delete')): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php $previousGroup = null; foreach ($rows as $row): ?>
                        <?php if ($row['choice_group'] !== null && $row['choice_group'] === $previousGroup): ?>
                            <tr><td colspan="4" class="py-0 text-muted small text-center">— or —</td></tr>
                        <?php endif; $previousGroup = $row['choice_group']; ?>
                        <tr>
                            <td><?= $describe($row) ?></td>
                            <td class="text-center"><?= (int) $row['credit_hours'] ?></td>
                            <td><?= $typeBadge($row) ?></td>
                            <?php if (can('academics.delete')): ?>
                            <td class="text-end">
                                <form method="post" action="<?= url('/academics/programs/' . $program['id'] . '/curriculum/' . $row['id']) ?>" onsubmit="return confirm('Remove this row from the curriculum?')">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Remove"><?= icon('trash', 'ico-sm') ?></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fw-bold bg-light"><td>Total</td><td class="text-center"><?= \App\Models\Program::termCredits($rows) ?></td><td colspan="2"></td></tr></tfoot>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php
$listTitles = ['elective' => 'Elective Courses', 'concentration' => 'Content-Area Concentrations', 'minor' => 'Minor Requirements'];
foreach ($listTitles as $type => $title):
    if (empty($options[$type])) { continue; } ?>
<div class="card mb-4">
    <div class="card-header fw-semibold"><?= icon('book') ?> <?= e($title) ?></div>
    <div class="row g-0">
    <?php foreach ($options[$type] as $label => $rows): ?>
        <div class="col-lg-<?= count($options[$type]) > 1 ? '6' : '12' ?>">
            <div class="px-3 pt-3 fw-semibold"><?= e($label) ?></div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <tbody>
                    <?php $previousGroup = null; foreach ($rows as $row): ?>
                        <?php if ($row['choice_group'] !== null && $row['choice_group'] === $previousGroup): ?>
                            <tr><td colspan="2" class="py-0 text-muted small text-center">— or —</td></tr>
                        <?php endif; $previousGroup = $row['choice_group']; ?>
                        <tr><td><?= $describe($row) ?></td><td class="text-center"><?= (int) $row['credit_hours'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fw-bold bg-light"><td>Total</td><td class="text-center"><?= \App\Models\Program::termCredits($rows) ?></td></tr></tfoot>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (can('academics.create')): ?>
<div class="card">
    <div class="card-header"><?= icon('plus') ?> Add to Curriculum</div>
    <form method="post" action="<?= url('/academics/programs/' . $program['id'] . '/curriculum') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select">
                        <?= options(array_column($courses, 'label', 'id'), '', '— Placeholder slot (name it on the right) —') ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Or slot label</label>
                    <input type="text" name="slot_label" class="form-control" placeholder="e.g. Minor course">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="course_type" class="form-select" required>
                        <?= options(['core' => 'Core', 'common' => 'General', 'elective' => 'Elective', 'minor' => 'Minor', 'concentration' => 'Content area', 'audit' => 'Audit'], 'core') ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Credits <span class="text-danger">*</span></label>
                    <input type="number" name="credit_hours" class="form-control" min="0" max="12" value="3" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <select name="year_of_study" class="form-select" required>
                        <?= options(array_combine(range(1, 6), array_map(static fn ($y) => 'Year ' . $y, range(1, 6))), 1) ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester_number" class="form-select" required>
                        <?= options([1 => '1st Semester', 2 => '2nd Semester'], 1) ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control" maxlength="255" placeholder="Optional">
                </div>
            </div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary">Add</button></div>
    </form>
</div>
<?php endif; ?>
<?php endsection(); ?>
