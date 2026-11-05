<?php
layout('layouts.app');
section('content');
?>
<div class="page-head"><div><h1>My Course Evaluations</h1><p class="lede">What students said about your courses. Results appear once enough students have responded to keep answers anonymous.</p></div></div>
<div class="card">
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Evaluation</th><th>Course</th><th class="text-center">Responses</th><th class="text-end">Average (1-5)</th><th></th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state"><?= icon('chart') ?><p>No evaluations of your courses yet.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): $shown = (int) $r['responses'] >= (int) $r['min_responses']; ?>
            <tr><td class="small"><?= e($r['period_title']) ?></td><td><b><?= e($r['code']) ?></b> <?= e($r['section']) ?><div class="text-muted-sm"><?= e($r['title']) ?></div></td>
                <td class="text-center"><?= (int) $r['responses'] ?> / <?= (int) $r['enrolled'] ?></td>
                <td class="text-end fw-semibold"><?= $shown && $r['average'] !== null ? number_format((float) $r['average'], 2) : '<span class="text-muted-sm fw-normal">Not enough responses</span>' ?></td>
                <td class="text-end"><?php if ($shown): ?><a class="btn btn-sm btn-light" href="<?= url('/evaluations/' . $r['period_id'] . '/' . $r['offering_id']) ?>">Details</a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
