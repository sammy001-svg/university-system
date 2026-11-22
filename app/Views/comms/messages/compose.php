<?php
layout('layouts.app');
section('content');

$oldIds = array_map('intval', (array) old_raw('recipient_ids', []));
if ($oldIds === [] && old('recipient_id') !== '') {
    $oldIds = [(int) old('recipient_id')];
}
$oldIds = array_values(array_unique(array_filter($oldIds, static fn (int $id): bool => $id > 0)));
$jsonUsers = json_encode(array_map(static fn (array $u): array => [
    'id'   => (int) $u['id'],
    'name' => trim($u['first_name'] . ' ' . $u['last_name']),
    'user' => (string) $u['username'],
    'mail' => (string) ($u['email'] ?? ''),
    'type' => (string) $u['user_type'],
    'code' => (string) ($u['code'] ?? ''),
], $users), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<div class="page-head">
    <div>
        <h1>Compose Message</h1>
        <p class="lede">Send a message to one or more users across the university.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/messages') ?>">&larr; Back to Inbox</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/messages') ?>" id="compose_form">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="recipient_search">Recipients <span class="text-danger">*</span></label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light" id="recipient_count">0 selected</span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-none" id="clear_recipients">Clear all</button>
                    </div>
                </div>

                <div id="selected_chips" class="d-flex flex-wrap gap-1 mb-2 d-none"></div>
                <div id="recipient_inputs"></div>

                <div class="row g-2">
                    <div class="col-md-9 position-relative">
                        <input type="text" id="recipient_search" class="form-control" autocomplete="off"
                               placeholder="Search users by name, username, email or ID…"
                               role="combobox" aria-expanded="false" aria-controls="recipient_results">
                        <div id="recipient_dropdown" class="card position-absolute w-100 shadow-sm d-none" style="z-index:1050;left:0;">
                            <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center bg-light small">
                                <span class="text-muted" id="match_summary">Showing users</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="select_visible">Select matching</button>
                            </div>
                            <ul id="recipient_results" class="list-group list-group-flush" role="listbox" style="max-height:280px;overflow-y:auto;"></ul>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="recipient_type" class="form-select" aria-label="Filter by user type">
                            <option value="">All user types</option>
                            <option value="student">Students</option>
                            <option value="lecturer">Lecturers</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Administrators</option>
                            <option value="parent">Parents</option>
                        </select>
                    </div>
                </div>
                <div class="form-text">Search and click users to add or remove them. You can pick as many recipients as needed.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="msg_subject">Subject <span class="text-danger">*</span></label>
                <input type="text" id="msg_subject" name="subject" class="form-control" maxlength="200" value="<?= e(old('subject')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="msg_body">Message Body <span class="text-danger">*</span></label>
                <textarea id="msg_body" name="body" class="form-control" rows="5" required><?= e(old('body')) ?></textarea>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Send Message</button>
            <a href="<?= url('/messages') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
    const users = <?= $jsonUsers ?>;
    const preselected = <?= json_encode($oldIds) ?>;

    const searchInput = document.getElementById('recipient_search');
    const typeSelect  = document.getElementById('recipient_type');
    const dropdown    = document.getElementById('recipient_dropdown');
    const list        = document.getElementById('recipient_results');
    const chipsBox    = document.getElementById('selected_chips');
    const inputsBox   = document.getElementById('recipient_inputs');
    const countBadge  = document.getElementById('recipient_count');
    const clearBtn    = document.getElementById('clear_recipients');
    const matchInfo   = document.getElementById('match_summary');
    const selectVis   = document.getElementById('select_visible');
    const form        = document.getElementById('compose_form');

    const MAX = 60;
    const norm = (v) => String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();
    const byId = new Map();
    users.forEach((u) => {
        u._hay = norm(u.name + ' ' + u.user + ' ' + u.mail + ' ' + u.code + ' ' + u.type);
        byId.set(u.id, u);
    });

    const selected = new Set(preselected.filter((id) => byId.has(id)));
    let filteredAll = [];
    let visible = [];
    let active = -1;

    function filter() {
        const q = norm(searchInput.value);
        const type = typeSelect.value;
        const terms = q ? q.split(' ') : [];
        filteredAll = users.filter((u) => {
            if (type && u.type !== type) return false;
            return terms.every((t) => u._hay.includes(t));
        });
        visible = filteredAll.slice(0, MAX);
        if (active >= visible.length) active = -1;
    }

    function open(show) {
        dropdown.classList.toggle('d-none', !show);
        searchInput.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    function renderSelected() {
        chipsBox.innerHTML = '';
        inputsBox.innerHTML = '';
        const count = selected.size;
        countBadge.textContent = count + ' selected';
        chipsBox.classList.toggle('d-none', count === 0);
        clearBtn.classList.toggle('d-none', count < 2);
        if (count > 0) {
            searchInput.setCustomValidity('');
            searchInput.classList.remove('is-invalid');
        }
        selected.forEach((id) => {
            const u = byId.get(id);
            if (!u) return;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'recipient_ids[]';
            hidden.value = String(u.id);
            inputsBox.appendChild(hidden);

            const chip = document.createElement('span');
            chip.className = 'badge text-bg-primary d-inline-flex align-items-center gap-1 py-1 px-2 fw-normal';
            chip.style.fontSize = '0.85rem';
            chip.appendChild(document.createTextNode(u.name + ' (' + u.user + ')'));

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn-close btn-close-white ms-1';
            rm.style.fontSize = '0.6rem';
            rm.setAttribute('aria-label', 'Remove ' + u.name);
            rm.addEventListener('click', () => {
                selected.delete(u.id);
                renderSelected();
                if (!dropdown.classList.contains('d-none')) renderList();
            });
            chip.appendChild(rm);
            chipsBox.appendChild(chip);
        });
    }

    function renderList() {
        filter();
        list.innerHTML = '';
        matchInfo.textContent = filteredAll.length > MAX
            ? 'Showing ' + MAX + ' of ' + filteredAll.length + ' matches (keep typing to narrow)'
            : filteredAll.length + ' matching user' + (filteredAll.length === 1 ? '' : 's');
        selectVis.textContent = filteredAll.length > MAX ? 'Select first ' + MAX : 'Select all (' + visible.length + ')';
        selectVis.classList.toggle('d-none', visible.length === 0);

        if (!visible.length) {
            const li = document.createElement('li');
            li.className = 'list-group-item text-muted small';
            li.textContent = 'No matching users found';
            list.appendChild(li);
        }

        visible.forEach((u, i) => {
            const isPicked = selected.has(u.id);
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2'
                + (i === active ? ' active' : '');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', isPicked ? 'true' : 'false');
            li.style.cursor = 'pointer';

            const left = document.createElement('div');
            left.className = 'd-flex align-items-center gap-2 min-w-0';

            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.className = 'form-check-input mt-0';
            chk.checked = isPicked;
            chk.tabIndex = -1;

            const info = document.createElement('div');
            const nameEl = document.createElement('div');
            nameEl.className = 'fw-semibold';
            nameEl.textContent = u.name;
            const metaEl = document.createElement('small');
            metaEl.className = i === active ? '' : 'text-muted';
            metaEl.textContent = '@' + u.user + (u.code ? ' · ' + u.code : '') + (u.mail ? ' · ' + u.mail : '');
            info.append(nameEl, metaEl);
            left.append(chk, info);

            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge text-bg-light text-capitalize';
            typeBadge.textContent = u.type;

            li.append(left, typeBadge);
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                toggleUser(u.id);
            });
            list.appendChild(li);
        });
        open(true);
    }

    function toggleUser(id) {
        if (selected.has(id)) selected.delete(id);
        else selected.add(id);
        renderSelected();
        renderList();
    }

    searchInput.addEventListener('input', () => { active = -1; renderList(); });
    searchInput.addEventListener('focus', renderList);
    searchInput.addEventListener('blur', () => setTimeout(() => open(false), 150));
    typeSelect.addEventListener('change', () => { active = -1; renderList(); searchInput.focus(); });

    clearBtn.addEventListener('click', () => {
        selected.clear();
        renderSelected();
        if (!dropdown.classList.contains('d-none')) renderList();
    });

    selectVis.addEventListener('mousedown', (e) => {
        e.preventDefault();
        visible.forEach((u) => selected.add(u.id));
        renderSelected();
        renderList();
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (dropdown.classList.contains('d-none')) renderList();
            active = Math.min(active + 1, visible.length - 1);
            renderList();
            const el = list.children[active];
            if (el) el.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = Math.max(active - 1, 0);
            renderList();
            const el = list.children[active];
            if (el) el.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && !dropdown.classList.contains('d-none')) {
            e.preventDefault();
            const pick = visible[active >= 0 ? active : (visible.length === 1 ? 0 : -1)];
            if (pick) toggleUser(pick.id);
        } else if (e.key === 'Escape') {
            open(false);
        }
    });

    form.addEventListener('submit', (e) => {
        if (selected.size === 0) {
            e.preventDefault();
            searchInput.setCustomValidity('Please select at least one recipient.');
            searchInput.classList.add('is-invalid');
            searchInput.reportValidity();
        }
    });

    renderSelected();
})();
</script>
<?php endsection(); ?>
