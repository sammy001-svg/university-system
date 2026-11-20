<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Record Payment</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/finance/payments') ?>">&larr; Back to Payments</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/finance/payments') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <?php
                        $oldStudentId = (string) old('student_id');
                        $oldStudentLabel = '';
                        foreach ($students as $s) {
                            if ((string) $s['id'] === $oldStudentId) { $oldStudentLabel = $s['label']; break; }
                        }
                    ?>
                    <div class="student-picker position-relative">
                        <input type="hidden" name="student_id" id="student_id" value="<?= e($oldStudentLabel !== '' ? $oldStudentId : '') ?>">
                        <input type="text" id="student_search" class="form-control" autocomplete="off"
                               placeholder="Search by name or admission number…" value="<?= e($oldStudentLabel) ?>"
                               role="combobox" aria-expanded="false" aria-controls="student_results" required>
                        <ul id="student_results" class="list-group position-absolute w-100 shadow-sm d-none"
                            role="listbox" style="z-index:1050;max-height:280px;overflow-y:auto;"></ul>
                    </div>
                    <div class="form-text">Type at least part of the student's name or admission number, then pick from the list.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount (<?= e(currency_code()) ?>) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= e(old('amount')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="method" class="form-select" required>
                        <?= enum_options(['cash','mpesa','bank_transfer','cheque','card','other'], old('method', 'mpesa')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Transaction Reference / Code</label>
                    <input type="text" name="reference" class="form-control" value="<?= e(old('reference')) ?>" placeholder="e.g. M-Pesa Code or Check No.">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Record Payment &amp; Issue Receipt</button>
            <a href="<?= url('/finance/payments') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
    const students = <?= json_encode(array_map(fn($s) => [
        'id'    => (int) $s['id'],
        'adm'   => (string) $s['admission_number'],
        'name'  => (string) $s['name'],
        'label' => (string) $s['label'],
    ], $students), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    const input   = document.getElementById('student_search');
    const hidden  = document.getElementById('student_id');
    const list    = document.getElementById('student_results');
    const form    = input.closest('form');
    const MAX     = 50;
    let matches   = [];
    let active    = -1;

    const norm = (v) => String(v).toLowerCase().replace(/\s+/g, ' ').trim();
    students.forEach((s) => { s._adm = norm(s.adm); s._name = norm(s.name); });

    function search(q) {
        q = norm(q);
        if (!q) return students.slice(0, MAX);
        const terms = q.split(' ');
        const res = students.filter((s) =>
            s._adm.includes(q) || terms.every((t) => s._name.includes(t) || s._adm.includes(t))
        );
        // Exact / prefix admission-number matches first
        res.sort((a, b) => (b._adm.startsWith(q) - a._adm.startsWith(q)));
        return res.slice(0, MAX);
    }

    function render() {
        list.innerHTML = '';
        if (!matches.length) {
            const li = document.createElement('li');
            li.className = 'list-group-item text-muted small';
            li.textContent = 'No matching students';
            list.appendChild(li);
        }
        matches.forEach((s, i) => {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action' + (i === active ? ' active' : '');
            li.setAttribute('role', 'option');
            li.style.cursor = 'pointer';
            const adm = document.createElement('strong');
            adm.textContent = s.adm;
            li.appendChild(adm);
            li.appendChild(document.createTextNode(' – ' + s.name));
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(s); });
            list.appendChild(li);
        });
        open(true);
    }

    function open(show) {
        list.classList.toggle('d-none', !show);
        input.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    function choose(s) {
        hidden.value = s.id;
        input.value = s.label;
        input.setCustomValidity('');
        input.classList.remove('is-invalid');
        open(false);
    }

    input.addEventListener('input', () => {
        hidden.value = '';
        active = -1;
        matches = search(input.value);
        render();
    });
    input.addEventListener('focus', () => { if (!hidden.value) { matches = search(input.value); render(); } });
    input.addEventListener('blur', () => setTimeout(() => open(false), 100));
    input.addEventListener('keydown', (e) => {
        if (list.classList.contains('d-none') && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            matches = search(input.value); render();
        }
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, matches.length - 1); render(); scroll(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); scroll(); }
        else if (e.key === 'Enter' && !list.classList.contains('d-none')) {
            e.preventDefault();
            const pick = matches[active >= 0 ? active : (matches.length === 1 ? 0 : -1)];
            if (pick) choose(pick);
        } else if (e.key === 'Escape') { open(false); }
    });
    function scroll() { const el = list.children[active]; if (el) el.scrollIntoView({ block: 'nearest' }); }

    form.addEventListener('submit', (e) => {
        if (!hidden.value) {
            e.preventDefault();
            input.setCustomValidity('Please select a student from the list.');
            input.classList.add('is-invalid');
            input.reportValidity();
        }
    });
})();
</script>
<?php endsection(); ?>
