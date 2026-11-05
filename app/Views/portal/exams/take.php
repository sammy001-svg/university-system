<?php
layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
?>
<div class="exam-bar" data-exam
     data-answer-url="<?= e(url('/portal/exams/' . $exam['id'] . '/answer')) ?>"
     data-focus-url="<?= e(url('/portal/exams/' . $exam['id'] . '/focus')) ?>"
     data-seconds="<?= (int) $secondsLeft ?>">
    <div class="me-auto">
        <div class="fw-semibold"><?= e($offering['code']) ?> &middot; <?= e($exam['title']) ?></div>
        <div class="text-muted-sm"><?= e($student['first_name'] . ' ' . $student['last_name']) ?> &middot; <?= e($student['admission_number']) ?></div>
    </div>
    <span class="save-state" data-save-state>All answers saved</span>
    <span class="exam-timer" data-timer role="timer" aria-live="off"><?= icon('timer', 'ico-sm') ?> <span data-clock>--:--</span></span>
    <button type="button" class="btn btn-primary" data-submit-open>Submit exam</button>
</div>

<div class="row g-3">
    <div class="col-lg-9">
        <?php foreach ($paper as $i => $q):
            $saved  = $answers[$q['id']] ?? null;
            $chosen = $saved && $saved['option_ids'] ? array_map('intval', explode(',', $saved['option_ids'])) : []; ?>
            <div class="card mb-3 exam-q" id="q<?= $i + 1 ?>" data-question="<?= $q['id'] ?>" data-type="<?= e($q['type']) ?>" data-index="<?= $i + 1 ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-3 mb-3">
                        <div class="q-body"><span class="q-num">Question <?= $i + 1 ?>.</span><?= e($q['body']) ?></div>
                        <div class="text-nowrap text-muted-sm"><?= $fmt($q['marks']) ?> mark<?= $q['marks'] === 1.0 ? '' : 's' ?></div>
                    </div>
                    <?php if ($q['type'] === 'multiple'): ?><div class="text-muted-sm mb-2">Select all that apply.</div><?php endif; ?>

                    <?php if (in_array($q['type'], ['single', 'true_false', 'multiple'], true)): ?>
                        <?php foreach ($q['options'] as $j => $opt): ?>
                            <label class="exam-choice">
                                <input class="form-check-input" type="<?= $q['type'] === 'multiple' ? 'checkbox' : 'radio' ?>" name="q<?= $q['id'] ?>"
                                       value="<?= $opt['id'] ?>" <?= in_array($opt['id'], $chosen, true) ? 'checked' : '' ?>>
                                <span class="exam-letter"><?= chr(65 + $j) ?>.</span>
                                <span><?= e($opt['body']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php elseif ($q['type'] === 'short_answer'): ?>
                        <input class="form-control" name="q<?= $q['id'] ?>" maxlength="500" autocomplete="off" value="<?= e((string) ($saved['answer_text'] ?? '')) ?>" placeholder="Type your answer">
                    <?php else: ?>
                        <textarea class="form-control" name="q<?= $q['id'] ?>" rows="8" placeholder="Write your answer"><?= e((string) ($saved['answer_text'] ?? '')) ?></textarea>
                        <div class="text-muted-sm mt-1" data-words></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-link px-0 text-muted" data-flag>Flag for review</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="text-center mb-4"><button type="button" class="btn btn-primary btn-lg" data-submit-open>Finish and submit</button></div>
    </div>
    <div class="col-lg-3">
        <div class="card" style="position:sticky; top:96px">
            <div class="card-header">Questions</div>
            <div class="card-body">
                <div class="qnav" data-qnav>
                    <?php foreach ($paper as $i => $q): ?><a href="#q<?= $i + 1 ?>" data-nav="<?= $q['id'] ?>"><?= $i + 1 ?></a><?php endforeach; ?>
                </div>
                <div class="text-muted-sm mt-3"><span data-answered-count>0</span> of <?= count($paper) ?> answered</div>
                <div class="text-muted-sm mt-2"><span class="d-inline-block rounded" style="width:12px;height:12px;background:var(--sidebar-top)"></span> answered
                    &nbsp;<span class="d-inline-block rounded" style="width:12px;height:12px;box-shadow:inset 0 -3px 0 #d97706;border:1px solid var(--line)"></span> flagged</div>
            </div>
        </div>
    </div>
</div>

<!-- Submit confirmation -->
<div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Submit your exam?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-1" data-submit-summary></p><p class="text-muted-sm mb-0">Once submitted you cannot change your answers.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep working</button>
                <form method="post" action="<?= url('/portal/exams/' . $exam['id'] . '/submit') ?>" data-submit-form>
                    <?= csrf_field() ?><input type="hidden" name="auto" value="0">
                    <button class="btn btn-primary">Submit now</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
  'use strict';
  var bar = document.querySelector('[data-exam]');
  var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var stateEl = document.querySelector('[data-save-state]');
  var clock = document.querySelector('[data-clock]');
  var timer = document.querySelector('[data-timer]');
  var submitForm = document.querySelector('[data-submit-form]');
  var deadline = Date.now() + parseInt(bar.dataset.seconds, 10) * 1000;
  var pending = {}, saving = false, submitting = false;

  var post = function (url, body) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(Object.assign({ _token: token }, body))
    }).then(function (r) { return r.json().then(function (j) { j.status = r.status; return j; }); });
  };

  var setState = function (text, error) { stateEl.textContent = text; stateEl.classList.toggle('error', !!error); };

  /* ---- answers ---- */
  var answerOf = function (card) {
    var type = card.dataset.type, id = card.dataset.question;
    if (type === 'short_answer' || type === 'essay') {
      var field = card.querySelector('[name="q' + id + '"]');
      return { question_id: id, answer_text: field.value };
    }
    var ids = [];
    card.querySelectorAll('input[name="q' + id + '"]:checked').forEach(function (i) { ids.push(i.value); });
    return { question_id: id, option_ids: ids };
  };
  var isAnswered = function (card) {
    var a = answerOf(card);
    return a.option_ids ? a.option_ids.length > 0 : a.answer_text.trim() !== '';
  };
  var refreshNav = function () {
    var count = 0;
    document.querySelectorAll('[data-question]').forEach(function (card) {
      var done = isAnswered(card);
      if (done) { count++; }
      document.querySelector('[data-nav="' + card.dataset.question + '"]').classList.toggle('answered', done);
    });
    document.querySelector('[data-answered-count]').textContent = count;
    return count;
  };

  var inflight = Promise.resolve();
  var flush = function () {
    if (saving) { return inflight; }
    var ids = Object.keys(pending);
    if (ids.length === 0) { return Promise.resolve(); }
    saving = true;
    var id = ids[0], body = pending[id];
    delete pending[id];
    setState('Saving...');
    inflight = post(bar.dataset.answerUrl, body).then(function (res) {
      saving = false;
      if (!res.ok) {
        setState(res.message || 'Could not save', true);
        if (res.status === 409 && /time/i.test(res.message || '')) { autoSubmit(); }
        return;
      }
      if (typeof res.seconds_left === 'number') { deadline = Date.now() + res.seconds_left * 1000; }
      if (Object.keys(pending).length) { return flush(); }
      setState('All answers saved at ' + res.saved_at);
    }).catch(function () {
      saving = false;
      pending[id] = pending[id] || body;           // keep it for the next try
      setState('Offline - will retry', true);
    });
    return inflight;
  };
  // Save everything still queued; bounded so a lost connection cannot loop forever.
  var drain = function (tries) {
    return Object.keys(pending).length && tries > 0 ? flush().then(function () { return drain(tries - 1); }) : Promise.resolve();
  };
  var queue = function (card) { pending[card.dataset.question] = answerOf(card); refreshNav(); };

  document.querySelectorAll('[data-question]').forEach(function (card) {
    var textTimer;
    card.addEventListener('change', function (e) {
      if (e.target.matches('input[type=radio], input[type=checkbox]')) { queue(card); flush(); }
    });
    card.addEventListener('input', function (e) {
      if (e.target.matches('textarea, input[type=text], input:not([type])')) {
        var words = card.querySelector('[data-words]');
        if (words) { words.textContent = (e.target.value.trim() ? e.target.value.trim().split(/\s+/).length : 0) + ' words'; }
        clearTimeout(textTimer);
        textTimer = setTimeout(function () { queue(card); flush(); }, 900);
        setState('Unsaved changes');
      }
    });
    card.querySelector('[data-flag]').addEventListener('click', function () {
      var nav = document.querySelector('[data-nav="' + card.dataset.question + '"]');
      nav.classList.toggle('flagged');
      this.textContent = nav.classList.contains('flagged') ? 'Remove flag' : 'Flag for review';
    });
  });
  setInterval(function () { if (Object.keys(pending).length) { flush(); } }, 5000);

  /* ---- timer and submission ---- */
  var autoSubmit = function () {
    if (submitting) { return; }
    submitting = true;
    setState('Time is up - submitting...');
    window.removeEventListener('beforeunload', guard);
    submitForm.querySelector('[name=auto]').value = '1';
    // Save whatever is typed, then submit.
    document.querySelectorAll('[data-question]').forEach(function (card) { pending[card.dataset.question] = answerOf(card); });
    var sent = false;
    var done = function () { if (!sent) { sent = true; submitForm.submit(); } };
    drain(40).then(done, done);
    setTimeout(done, 8000);
  };
  var tick = function () {
    var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
    var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
    clock.textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    timer.classList.toggle('low', left <= 300);
    if (left === 300 || left === 60) { setState(left === 300 ? '5 minutes left' : '1 minute left', true); }
    if (left <= 0) { autoSubmit(); return; }
    setTimeout(tick, 1000);
  };
  tick();

  var modal = new bootstrap.Modal(document.getElementById('submitModal'));
  document.querySelectorAll('[data-submit-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var total = document.querySelectorAll('[data-question]').length, answered = refreshNav();
      document.querySelector('[data-submit-summary]').textContent = 'You have answered ' + answered + ' of ' + total + ' questions.'
        + (answered < total ? ' Unanswered questions score zero.' : '');
      modal.show();
    });
  });
  submitForm.addEventListener('submit', function (e) {
    e.preventDefault();
    submitting = true;
    window.removeEventListener('beforeunload', guard);
    document.querySelectorAll('[data-question]').forEach(function (card) { pending[card.dataset.question] = answerOf(card); });
    var go = function () { submitForm.submit(); };
    drain(40).then(go, go);
  });

  /* ---- integrity: leaving the exam window is recorded ---- */
  var lastReport = 0;
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && !submitting && Date.now() - lastReport > 3000) {
      lastReport = Date.now();
      post(bar.dataset.focusUrl, {}).catch(function () {});
    }
  });
  var guard = function (e) { if (!submitting) { e.preventDefault(); e.returnValue = ''; } };
  window.addEventListener('beforeunload', guard);
  document.addEventListener('contextmenu', function (e) { e.preventDefault(); });

  refreshNav();
})();
</script>
<?php endsection(); ?>
