<?php
/**
 * Searchable class offering picker used by scheduling forms.
 * Expects: $offerings (list from Oversight::offerings()), $selectedOfferingId (?int).
 */
$pickedId = (int) old_raw('offering_id', $selectedOfferingId ?? 0);
$formatOffering = static function (array $o): string {
    $lect = $o['lecturer_name'] !== '' ? ' · ' . $o['lecturer_name'] : '';
    return $o['code'] . ' Sec ' . $o['section'] . ' — ' . $o['title'] . ' (' . $o['semester_name'] . $lect . ')';
};
$pickedLabel = '';
foreach ($offerings as $o) {
    if ((int) $o['id'] === $pickedId) {
        $pickedLabel = $formatOffering($o);
        break;
    }
}
$jsonOfferings = json_encode(array_map(static fn (array $o): array => [
    'id'       => (int) $o['id'],
    'code'     => (string) $o['code'],
    'section'  => (string) $o['section'],
    'title'    => (string) $o['title'],
    'semester' => (string) $o['semester_name'],
    'current'  => (int) $o['is_current'] === 1,
    'lecturer' => (string) $o['lecturer_name'],
    'enrolled' => (int) $o['registered'],
    'label'    => $formatOffering($o),
], $offerings), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<div class="position-relative" data-offering-picker>
    <input type="hidden" name="offering_id" id="offering_id" value="<?= $pickedLabel !== '' ? $pickedId : '' ?>">
    <input type="text" id="offering_search" class="form-control" autocomplete="off"
           value="<?= e($pickedLabel) ?>"
           placeholder="Search by course code, title, semester or lecturer…"
           role="combobox" aria-expanded="false" aria-controls="offering_results" required>
    <ul id="offering_results" class="list-group position-absolute w-100 shadow-sm d-none"
        role="listbox" style="z-index:1050;max-height:300px;overflow-y:auto;"></ul>
</div>
<div class="form-text">Start typing a course code or name, then pick the section from the list.</div>

<script>
(function () {
    const offerings = <?= $jsonOfferings ?>;
    const input  = document.getElementById('offering_search');
    const hidden = document.getElementById('offering_id');
    const list   = document.getElementById('offering_results');
    const form   = input.closest('form');
    const MAX    = 50;
    let matches  = [];
    let active   = -1;

    const norm = (v) => String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();
    offerings.forEach((o) => {
        o._hay = norm(o.code + ' ' + o.section + ' ' + o.title + ' ' + o.semester + ' ' + o.lecturer);
    });

    function search(q) {
        q = norm(q);
        if (!q) return offerings.slice(0, MAX);
        const terms = q.split(' ');
        return offerings.filter((o) => terms.every((t) => o._hay.includes(t))).slice(0, MAX);
    }

    function open(show) {
        list.classList.toggle('d-none', !show);
        input.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    function render() {
        list.innerHTML = '';
        if (!matches.length) {
            const li = document.createElement('li');
            li.className = 'list-group-item text-muted small';
            li.textContent = 'No matching class offerings';
            list.appendChild(li);
        }
        matches.forEach((o, i) => {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2' + (i === active ? ' active' : '');
            li.setAttribute('role', 'option');
            li.style.cursor = 'pointer';

            const left = document.createElement('div');
            const top = document.createElement('div');
            top.className = 'fw-semibold';
            top.textContent = o.code + ' Sec ' + o.section + ' — ' + o.title;
            const sub = document.createElement('small');
            sub.className = i === active ? '' : 'text-muted';
            sub.textContent = o.semester + (o.lecturer ? ' · ' + o.lecturer : ' · Unassigned');
            left.append(top, sub);

            const right = document.createElement('small');
            right.className = 'text-nowrap ' + (i === active ? '' : 'text-muted');
            right.textContent = o.enrolled + ' students';

            li.append(left, right);
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(o); });
            list.appendChild(li);
        });
        open(true);
    }

    function choose(o) {
        hidden.value = o.id;
        input.value = o.label;
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
    input.addEventListener('focus', () => {
        if (!hidden.value) { matches = search(input.value); render(); }
    });
    input.addEventListener('blur', () => setTimeout(() => open(false), 120));
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
            input.setCustomValidity('Please select a class offering from the list.');
            input.classList.add('is-invalid');
            input.reportValidity();
        }
    });
})();
</script>
