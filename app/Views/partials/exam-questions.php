<?php
/**
 * The question paper editor: the questions as they stand, and the form that adds
 * or edits one. Shared by the lecturer's exam page and the academic office's, so
 * a paper set from either place is identical.
 *
 * Expects:
 *   $exam, $questions, $locked (students have started, so the paper is fixed),
 *   $editing  the question being edited, or null,
 *   $paths    ['save', 'self', 'question', 'deleteExam' => null to hide]
 */
use App\Services\Elearning\ExamService;

$editing = $editing ?? null;
$qType   = (string) old_raw('question_type', $editing['question_type'] ?? 'single');
$qOpts   = $editing['options'] ?? [];
$tfTrue  = $editing === null || ($editing['question_type'] === 'true_false' && (int) ($qOpts[0]['is_correct'] ?? 1) === 1);
?>
<div class="row g-3">
    <div class="col-xl-7">
        <?php if ($questions === []): ?>
            <div class="card"><div class="card-body"><div class="empty-state"><?= icon('exam') ?>
                <p>No questions yet. Add the first one with the form.</p></div></div></div>
        <?php endif; ?>
        <?php foreach ($questions as $i => $q): ?>
            <div class="q-card" id="q<?= $q['id'] ?>">
                <div class="d-flex gap-2 align-items-start">
                    <div class="flex-grow-1">
                        <div class="text-muted-sm mb-1"><?= e(ExamService::QUESTION_TYPES[$q['question_type']]) ?> &middot;
                            <?= rtrim(rtrim(number_format((float) $q['marks'], 2), '0'), '.') ?> mark<?= (float) $q['marks'] === 1.0 ? '' : 's' ?></div>
                        <div class="q-body"><span class="q-num">Q<?= $i + 1 ?>.</span><?= e($q['body']) ?></div>
                    </div>
                    <?php if (!$locked): ?>
                        <div class="d-flex gap-1">
                            <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $arrow): ?>
                                <form method="post" action="<?= url($paths['question'] . $q['id'] . '/move') ?>">
                                    <?= csrf_field() ?><input type="hidden" name="direction" value="<?= $dir ?>">
                                    <button class="btn btn-sm btn-light" title="Move <?= $dir ?>" <?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($questions) - 1) ? 'disabled' : '' ?>><?= $arrow ?></button>
                                </form>
                            <?php endforeach; ?>
                            <a class="btn btn-sm btn-light" href="<?= url($paths['self'] . '?question=' . $q['id']) ?>#question-form" title="Edit"><?= icon('edit', 'ico-sm') ?></a>
                            <form method="post" action="<?= url($paths['question'] . $q['id']) ?>" data-confirm="Delete question <?= $i + 1 ?>?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($q['options'] !== []): ?>
                    <ul class="q-options">
                        <?php foreach ($q['options'] as $j => $opt): ?>
                            <li class="<?= (int) $opt['is_correct'] === 1 ? 'correct' : '' ?>">
                                <?php if ($q['question_type'] === 'short_answer'): ?>Accepted: <?php else: ?><b><?= chr(65 + $j) ?>.</b> <?php endif; ?>
                                <?= e($opt['body']) ?><?= (int) $opt['is_correct'] === 1 && $q['question_type'] !== 'short_answer' ? ' &check;' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($q['question_type'] === 'essay'): ?>
                    <div class="text-muted-sm mt-2">Marked by hand after the exam.</div>
                <?php endif; ?>
                <?php if ($q['explanation']): ?>
                    <div class="text-muted-sm mt-2"><b>Explanation:</b> <?= e($q['explanation']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="col-xl-5">
        <?php if (!$locked): ?>
            <div class="card" id="question-form" style="position:sticky; top:16px">
                <div class="card-header"><?= icon($editing ? 'edit' : 'plus') ?> <?= $editing ? 'Edit question' : 'Add a question' ?>
                    <?php if ($editing): ?><a class="ms-auto small fw-normal" href="<?= url($paths['self']) ?>">Cancel</a><?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url($paths['save']) ?>" data-question-form>
                        <?= csrf_field() ?>
                        <?php if ($editing): ?><input type="hidden" name="question_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <label class="form-label">Question type</label>
                                <select class="form-select" name="question_type" data-qtype><?= options(ExamService::QUESTION_TYPES, $qType) ?></select>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Marks</label>
                                <input class="form-control" type="number" name="marks" min="0.25" step="0.25" value="<?= e((string) old_raw('marks', $editing['marks'] ?? 1)) ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Question <span class="req">*</span></label>
                            <textarea class="form-control" name="body" rows="4" required><?= e((string) old_raw('body', $editing['body'] ?? '')) ?></textarea>
                        </div>

                        <div data-for="single multiple short_answer">
                            <label class="form-label" data-options-label>Choices <span class="text-muted-sm">(tick the correct answer)</span></label>
                            <div data-options>
                                <?php
                                $optionRows = $qOpts !== [] && $editing['question_type'] !== 'true_false' ? $qOpts : array_fill(0, 4, ['body' => '', 'is_correct' => 0]);
                                foreach ($optionRows as $k => $opt): ?>
                                    <div class="option-row">
                                        <input class="form-check-input" type="checkbox" name="option_correct[]" value="<?= $k ?>" <?= (int) $opt['is_correct'] === 1 ? 'checked' : '' ?> title="Correct answer">
                                        <input class="form-control form-control-sm" name="option_body[<?= $k ?>]" value="<?= e($opt['body']) ?>" placeholder="Choice <?= chr(65 + $k) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light mb-2" data-add-option>+ Add another</button>
                        </div>

                        <div data-for="true_false" class="mb-2">
                            <label class="form-label">Correct answer</label>
                            <div class="d-flex gap-3">
                                <label class="form-check"><input class="form-check-input" type="radio" name="true_false_answer" value="true" <?= $tfTrue ? 'checked' : '' ?>> True</label>
                                <label class="form-check"><input class="form-check-input" type="radio" name="true_false_answer" value="false" <?= $tfTrue ? '' : 'checked' ?>> False</label>
                            </div>
                        </div>

                        <div data-for="essay" class="alert alert-info py-2 small">Students type their answer; it is marked by hand from the results page.</div>

                        <div class="mb-3">
                            <label class="form-label">Explanation <span class="text-muted-sm">(optional, shown with the results)</span></label>
                            <textarea class="form-control form-control-sm" name="explanation" rows="2"><?= e((string) old_raw('explanation', $editing['explanation'] ?? '')) ?></textarea>
                        </div>
                        <button class="btn btn-primary w-100"><?= $editing ? 'Save question' : 'Add question' ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$locked && !empty($paths['deleteExam'])): ?>
            <form class="mt-3 text-end" method="post" action="<?= url($paths['deleteExam']) ?>" data-confirm="Delete this exam and all its questions?">
                <?= csrf_field() ?><?= method_field('DELETE') ?>
                <button class="btn btn-sm btn-link text-danger">Delete this exam</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
  var form = document.querySelector('[data-question-form]');
  if (!form) { return; }
  var type = form.querySelector('[data-qtype]');
  var list = form.querySelector('[data-options]');
  var label = form.querySelector('[data-options-label]');
  var sync = function () {
    form.querySelectorAll('[data-for]').forEach(function (el) {
      el.style.display = el.getAttribute('data-for').split(' ').indexOf(type.value) >= 0 ? '' : 'none';
    });
    var short = type.value === 'short_answer';
    label.innerHTML = short ? 'Accepted answers <span class="text-muted-sm">(any of these scores full marks; capitals and spacing are ignored)</span>'
                            : 'Choices <span class="text-muted-sm">(tick the correct answer' + (type.value === 'multiple' ? 's' : '') + ')</span>';
    list.querySelectorAll('input[type=checkbox]').forEach(function (box) { box.style.display = short ? 'none' : ''; });
  };
  type.addEventListener('change', sync);
  // One-answer questions: ticking a choice clears the others.
  list.addEventListener('change', function (e) {
    if (type.value === 'single' && e.target.type === 'checkbox' && e.target.checked) {
      list.querySelectorAll('input[type=checkbox]').forEach(function (box) { if (box !== e.target) { box.checked = false; } });
    }
  });
  form.querySelector('[data-add-option]').addEventListener('click', function () {
    var n = list.children.length;
    var row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = '<input class="form-check-input" type="checkbox" name="option_correct[]" value="' + n + '" title="Correct answer">'
      + '<input class="form-control form-control-sm" name="option_body[' + n + ']" placeholder="Choice ' + String.fromCharCode(65 + n) + '">';
    list.appendChild(row);
    sync();
  });
  sync();
})();
</script>
