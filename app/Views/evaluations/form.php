<?php
use App\Controllers\Quality\EvaluationController as EC;

layout('layouts.app');
section('content');
$section = null;
?>
<div class="page-head">
    <div><h1>Evaluate <?= e($course['code']) ?></h1><p class="lede"><?= e($course['title']) ?><?= $course['lecturer_name'] ? ' &middot; ' . e($course['lecturer_name']) : '' ?> &middot; anonymous</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/portal/evaluations') ?>">&larr; Back</a>
</div>
<form method="post" action="<?= url('/portal/evaluations/' . $period['id'] . '/' . $course['offering_id']) ?>" class="card" style="max-width:920px">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table align-middle mb-0">
            <thead><tr><th>How far do you agree?</th><?php foreach (EC::SCALE as $v => $l): ?><th class="text-center small" style="width:9%"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($questions as $q): ?>
                <?php if ($q['section'] !== $section): $section = $q['section']; ?><tr><td colspan="6" class="fw-bold bg-light"><?= e($section) ?></td></tr><?php endif; ?>
                <tr><td><?= e($q['body']) ?></td>
                    <?php foreach (EC::SCALE as $v => $l): ?>
                        <td class="text-center"><input class="form-check-input" type="radio" name="rating[<?= (int) $q['id'] ?>]" value="<?= $v ?>" required aria-label="<?= e($l) ?>"></td>
                    <?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <label class="form-label">Comments <span class="text-muted-sm">(optional: what worked well, what could be better)</span></label>
        <textarea class="form-control mb-3" name="comments" rows="4" maxlength="3000"></textarea>
        <button class="btn btn-primary">Submit evaluation</button>
    </div>
</form>
<?php endsection(); ?>
