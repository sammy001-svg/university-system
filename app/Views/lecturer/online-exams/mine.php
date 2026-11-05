<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div>
        <h1>Online Examinations</h1>
        <p class="lede">Quizzes, tests and examinations in your classes. To set a new one, open the class's online classroom.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/teaching') ?>">My Classes</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Exam</th><th>Class</th><th>Window</th><th>Status</th><th class="text-end">Sat</th><th class="text-end">To mark</th><th></th></tr></thead>
            <tbody>
            <?php if ($exams === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('exam') ?><p>No online exams yet. Open a class from My Classes and choose "New online exam".</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($exams as $x): $phase = ExamService::phase($x); ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= url('/teaching/online-exams/' . $x['id']) ?>"><?= e($x['title']) ?></a>
                        <div class="text-muted-sm"><?= e(ExamService::TYPES[$x['exam_type']]) ?> &middot; <?= (int) $x['question_count'] ?> questions &middot; <?= (int) $x['duration_minutes'] ?> min</div></td>
                    <td><code><?= e($x['code']) ?></code> <?= e($x['section']) ?><div class="text-muted-sm"><?= e($x['semester_name']) ?></div></td>
                    <td class="text-muted-sm"><?= fdatetime($x['opens_at']) ?><br><?= fdatetime($x['closes_at']) ?></td>
                    <td><?= status_badge(['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'][$phase]) ?></td>
                    <td class="text-end"><?= (int) $x['attempts'] ?> / <?= (int) $x['enrolled_count'] ?></td>
                    <td class="text-end"><?= (int) $x['to_mark'] > 0 ? '<span class="badge text-bg-warning">' . (int) $x['to_mark'] . '</span>' : '' ?></td>
                    <td class="text-end"><?php if ($phase !== 'draft'): ?><a class="btn btn-sm btn-light" href="<?= url('/teaching/online-exams/' . $x['id'] . '/results') ?>">Results</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
