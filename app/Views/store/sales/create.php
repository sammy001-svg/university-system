<?php
layout('layouts.app');
section('content');

// Restore the basket and customer after a failed submission.
$oldIds  = (array) old_raw('product_id', []);
$oldQtys = (array) old_raw('quantity', []);
$oldBasket = [];
foreach ($oldIds as $i => $pid) {
    if ((int) $pid > 0 && (float) ($oldQtys[$i] ?? 0) > 0) {
        $oldBasket[] = ['id' => (int) $pid, 'qty' => (float) $oldQtys[$i]];
    }
}
$oldType = in_array(old('customer_type'), ['student', 'staff', 'walk_in'], true) ? old('customer_type') : 'student';
$json = static fn ($v): string => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$methodLabels = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'card' => 'Card', 'bank_transfer' => 'Bank transfer'];
?>
<div class="page-head">
    <div><h1>Point of Sale</h1><p class="lede">Sell store items to students, staff and walk-in customers.</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/store/sales') ?>">Store Sales &rarr;</a>
</div>

<?php if ($products === []): ?>
    <div class="alert alert-warning">There are no products for sale yet. <?php if (can('store.manage')): ?><a href="<?= url('/store/products/create') ?>">Add a product</a> and receive stock first.<?php endif; ?></div>
<?php endif; ?>

<form method="post" action="<?= url('/store/sales') ?>" id="pos_form" autocomplete="off">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('search') ?> Add items</div>
            <div class="card-body">
                <div class="position-relative">
                    <input type="text" id="product_search" class="form-control form-control-lg" placeholder="Search products by name, SKU or category…"
                           role="combobox" aria-expanded="false" aria-controls="product_results">
                    <ul id="product_results" class="list-group position-absolute w-100 shadow-sm d-none" role="listbox" style="z-index:1050;max-height:320px;overflow-y:auto;"></ul>
                </div>
                <div class="form-text">Press Enter to add the highlighted product. Out-of-stock items can't be added.</div>
            </div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Item</th><th class="text-end" style="width:110px">Price</th><th style="width:130px">Qty</th><th class="text-end" style="width:130px">Amount</th><th style="width:48px"></th></tr></thead>
                    <tbody id="cart_body">
                        <tr id="cart_empty"><td colspan="5"><div class="empty-state py-4"><p>No items yet. Search above to add products.</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><?= icon('user') ?> Customer</div>
            <div class="card-body">
                <div class="btn-group w-100 mb-3" role="group">
                    <?php foreach (['student' => 'Student', 'staff' => 'Staff', 'walk_in' => 'Walk-in'] as $val => $lbl): ?>
                        <input type="radio" class="btn-check" name="customer_type" id="ct_<?= $val ?>" value="<?= $val ?>" <?= $oldType === $val ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="ct_<?= $val ?>"><?= $lbl ?></label>
                    <?php endforeach; ?>
                </div>

                <div data-customer="student" class="position-relative">
                    <input type="hidden" name="student_id" id="student_id" value="<?= e(old('student_id')) ?>">
                    <input type="text" id="student_search" class="form-control" placeholder="Search by name or admission number…">
                    <ul id="student_results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1050;max-height:260px;overflow-y:auto;"></ul>
                </div>
                <div data-customer="staff" class="position-relative">
                    <input type="hidden" name="staff_id" id="staff_id" value="<?= e(old('staff_id')) ?>">
                    <input type="text" id="staff_search" class="form-control" placeholder="Search by name or staff number…">
                    <ul id="staff_results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1050;max-height:260px;overflow-y:auto;"></ul>
                </div>
                <div data-customer="walk_in">
                    <input type="text" name="customer_name" class="form-control" maxlength="150" value="<?= e(old('customer_name')) ?>" placeholder="Customer name (optional)">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('money') ?> Payment</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span id="t_subtotal">0.00</span></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label for="discount" class="mb-0">Discount</label>
                    <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm text-end" style="max-width:130px" value="<?= e(old('discount', '0')) ?>">
                </div>
                <div class="d-flex justify-content-between border-top pt-2 mb-3 fs-5 fw-bold"><span>Total (<?= e(currency_code()) ?>)</span><span id="t_total">0.00</span></div>

                <div class="mb-2"><label class="form-label">Payment method</label>
                    <select name="payment_method" id="payment_method" class="form-select">
                        <?php foreach ($methods as $m): ?><option value="<?= e($m) ?>" <?= old('payment_method', 'cash') === $m ? 'selected' : '' ?>><?= e($methodLabels[$m] ?? humanize($m)) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mb-2" id="cash_box"><label class="form-label">Cash received</label>
                    <input type="number" step="0.01" min="0" name="amount_tendered" id="amount_tendered" class="form-control" value="<?= e(old('amount_tendered')) ?>" placeholder="Leave blank for exact amount">
                    <div class="d-flex justify-content-between mt-1 small"><span class="text-muted">Change</span><strong id="t_change">0.00</strong></div></div>
                <div class="mb-2 d-none" id="ref_box"><label class="form-label">Transaction reference</label>
                    <input type="text" name="reference" class="form-control" maxlength="80" value="<?= e(old('reference')) ?>" placeholder="e.g. M-Pesa code"></div>
                <div class="mb-3"><label class="form-label">Note</label><input type="text" name="note" class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div>

                <button type="submit" class="btn btn-primary btn-lg w-100" id="pos_submit" disabled>Complete Sale</button>
            </div>
        </div>
    </div>
</div>
</form>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
    const products = <?= $json(array_map(fn ($p) => [
        'id' => (int) $p['id'], 'sku' => (string) $p['sku'], 'name' => (string) $p['name'], 'category' => (string) $p['category'],
        'unit' => (string) $p['unit'], 'price' => (float) $p['price'], 'stock' => (float) $p['quantity'],
    ], $products)) ?>;
    const students = <?= $json(array_map(fn ($s) => ['id' => (int) $s['id'], 'code' => (string) $s['code'], 'name' => (string) $s['name']], $students)) ?>;
    const staff    = <?= $json(array_map(fn ($s) => ['id' => (int) $s['id'], 'code' => (string) $s['code'], 'name' => (string) $s['name']], $staff)) ?>;
    const oldBasket = <?= $json($oldBasket) ?>;

    const norm  = (v) => String(v ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    const money = (n) => (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const byId  = (list) => Object.fromEntries(list.map((x) => [x.id, x]));
    const productIndex = byId(products);

    /**
     * Type-ahead list: filters `items` by every typed word against `fields`,
     * renders up to 50 matches, keyboard (arrows, Enter, Esc) and mouse selection.
     */
    function typeahead({ input, list, items, fields, render, onPick, isDisabled }) {
        items.forEach((it) => { it._hay = fields.map((f) => norm(it[f])).join(' | '); });
        let matches = [], active = -1;
        const open = (show) => { list.classList.toggle('d-none', !show); input.setAttribute('aria-expanded', show ? 'true' : 'false'); };
        function search() {
            const q = norm(input.value);
            const terms = q ? q.split(' ') : [];
            matches = items.filter((it) => terms.every((t) => it._hay.includes(t))).slice(0, 50);
            active = matches.findIndex((m) => !(isDisabled && isDisabled(m)));
            draw();
        }
        function draw() {
            list.innerHTML = '';
            if (!matches.length) {
                const li = document.createElement('li');
                li.className = 'list-group-item text-muted small';
                li.textContent = 'No matches';
                list.appendChild(li);
            }
            matches.forEach((m, i) => {
                const li = document.createElement('li');
                const disabled = isDisabled && isDisabled(m);
                li.className = 'list-group-item list-group-item-action d-flex justify-content-between gap-2'
                    + (i === active ? ' active' : '') + (disabled ? ' disabled text-muted' : '');
                li.style.cursor = disabled ? 'not-allowed' : 'pointer';
                render(m, li);
                li.addEventListener('mousedown', (e) => { e.preventDefault(); if (!disabled) pick(m); });
                list.appendChild(li);
            });
            open(true);
            const el = list.children[active];
            if (el) el.scrollIntoView({ block: 'nearest' });
        }
        function pick(m) { onPick(m); open(false); }
        function step(dir) {
            if (!matches.length) return;
            let i = active;
            for (let n = 0; n < matches.length; n++) {
                i = (i + dir + matches.length) % matches.length;
                if (!(isDisabled && isDisabled(matches[i]))) { active = i; break; }
            }
            draw();
        }
        input.addEventListener('input', search);
        input.addEventListener('focus', search);
        input.addEventListener('blur', () => setTimeout(() => open(false), 120));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); list.classList.contains('d-none') ? search() : step(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); if (active >= 0 && matches[active]) pick(matches[active]); }
            else if (e.key === 'Escape') { open(false); }
        });
    }

    const text = (tag, cls, value) => { const el = document.createElement(tag); if (cls) el.className = cls; el.textContent = value; return el; };

    /* ---------------- Cart ---------------- */
    const cartBody = document.getElementById('cart_body');
    const cartEmpty = document.getElementById('cart_empty');
    const cart = new Map(); // product id -> { row, qtyInput }

    function addToCart(p, qty = 1) {
        if (cart.has(p.id)) {
            const line = cart.get(p.id);
            line.qtyInput.value = Math.min(p.stock, (parseFloat(line.qtyInput.value) || 0) + qty);
            recalc();
            line.qtyInput.focus();
            return;
        }
        const tr = document.createElement('tr');
        const tdName = document.createElement('td');
        tdName.appendChild(text('div', 'fw-semibold', p.name));
        tdName.appendChild(text('div', 'text-muted-sm', p.sku + ' · ' + p.stock + ' ' + p.unit + ' in stock'));
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'product_id[]'; hidden.value = p.id;
        tdName.appendChild(hidden);

        const tdQty = document.createElement('td');
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number'; qtyInput.name = 'quantity[]'; qtyInput.className = 'form-control form-control-sm';
        qtyInput.min = '0.01'; qtyInput.step = 'any'; qtyInput.max = String(p.stock); qtyInput.required = true;
        qtyInput.value = Math.min(qty, p.stock);
        qtyInput.addEventListener('input', recalc);
        tdQty.appendChild(qtyInput);

        const tdAmount = text('td', 'text-end fw-semibold', '0.00');
        const tdRemove = document.createElement('td');
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-sm btn-light'; btn.title = 'Remove'; btn.innerHTML = '&times;';
        btn.addEventListener('click', () => { tr.remove(); cart.delete(p.id); recalc(); });
        tdRemove.appendChild(btn);

        tr.append(tdName, text('td', 'text-end', money(p.price)), tdQty, tdAmount, tdRemove);
        cartBody.appendChild(tr);
        cart.set(p.id, { row: tr, qtyInput, amountCell: tdAmount, product: p });
        recalc();
    }

    /* ---------------- Totals ---------------- */
    const discountEl = document.getElementById('discount');
    const methodEl   = document.getElementById('payment_method');
    const tenderedEl = document.getElementById('amount_tendered');
    const submitBtn  = document.getElementById('pos_submit');
    let total = 0;

    function recalc() {
        let subtotal = 0, valid = cart.size > 0;
        cart.forEach(({ qtyInput, amountCell, product }) => {
            const q = parseFloat(qtyInput.value) || 0;
            const bad = q <= 0 || q > product.stock;
            qtyInput.classList.toggle('is-invalid', bad);
            if (bad) valid = false;
            const amt = Math.round(product.price * q * 100) / 100;
            amountCell.textContent = money(amt);
            subtotal += amt;
        });
        cartEmpty.classList.toggle('d-none', cart.size > 0);
        const discount = Math.max(0, parseFloat(discountEl.value) || 0);
        discountEl.classList.toggle('is-invalid', discount > subtotal);
        if (discount > subtotal) valid = false;
        total = Math.max(0, subtotal - discount);
        document.getElementById('t_subtotal').textContent = money(subtotal);
        document.getElementById('t_total').textContent = money(total);

        const cash = methodEl.value === 'cash';
        document.getElementById('cash_box').classList.toggle('d-none', !cash);
        document.getElementById('ref_box').classList.toggle('d-none', cash);
        const tendered = tenderedEl.value === '' ? total : (parseFloat(tenderedEl.value) || 0);
        const short = cash && tendered < total;
        tenderedEl.classList.toggle('is-invalid', short);
        document.getElementById('t_change').textContent = money(cash ? Math.max(0, tendered - total) : 0);
        if (short) valid = false;
        submitBtn.disabled = !valid;
    }
    [discountEl, tenderedEl].forEach((el) => el.addEventListener('input', recalc));
    methodEl.addEventListener('change', recalc);

    /* ---------------- Product search ---------------- */
    const productSearch = document.getElementById('product_search');
    typeahead({
        input: productSearch,
        list: document.getElementById('product_results'),
        items: products,
        fields: ['name', 'sku', 'category'],
        isDisabled: (p) => p.stock <= 0,
        render: (p, li) => {
            const left = document.createElement('div');
            left.appendChild(text('div', 'fw-semibold', p.name));
            left.appendChild(text('small', '', p.sku + ' · ' + p.category));
            const right = document.createElement('div');
            right.className = 'text-end';
            right.appendChild(text('div', 'fw-semibold', money(p.price)));
            right.appendChild(text('small', '', p.stock > 0 ? p.stock + ' ' + p.unit + ' left' : 'Out of stock'));
            li.append(left, right);
        },
        onPick: (p) => { addToCart(p); productSearch.value = ''; productSearch.focus(); },
    });

    /* ---------------- Customer pickers ---------------- */
    function personPicker(kind, people) {
        const input = document.getElementById(kind + '_search');
        const hidden = document.getElementById(kind + '_id');
        const index = byId(people);
        const label = (p) => p.code + ' – ' + p.name;
        if (hidden.value && index[hidden.value]) input.value = label(index[hidden.value]);
        else hidden.value = '';
        input.addEventListener('input', () => { hidden.value = ''; validateCustomer(); });
        typeahead({
            input, list: document.getElementById(kind + '_results'), items: people, fields: ['code', 'name'],
            render: (p, li) => { const d = document.createElement('div'); d.appendChild(text('strong', '', p.code)); d.appendChild(document.createTextNode(' – ' + p.name)); li.appendChild(d); },
            onPick: (p) => { hidden.value = p.id; input.value = label(p); input.classList.remove('is-invalid'); validateCustomer(); },
        });
        return { input, hidden };
    }
    const pickers = { student: personPicker('student', students), staff: personPicker('staff', staff) };

    const typeRadios = document.querySelectorAll('input[name="customer_type"]');
    const currentType = () => document.querySelector('input[name="customer_type"]:checked').value;
    function showCustomer() {
        const t = currentType();
        document.querySelectorAll('[data-customer]').forEach((el) => el.classList.toggle('d-none', el.dataset.customer !== t));
        validateCustomer();
    }
    function validateCustomer() {
        Object.entries(pickers).forEach(([kind, p]) => p.input.setCustomValidity(
            kind === currentType() && !p.hidden.value ? 'Select a ' + kind + ' from the list.' : ''
        ));
    }
    typeRadios.forEach((r) => r.addEventListener('change', showCustomer));

    document.getElementById('pos_form').addEventListener('submit', (e) => {
        validateCustomer();
        const p = pickers[currentType()];
        if (p && !p.hidden.value) {
            e.preventDefault();
            p.input.classList.add('is-invalid');
            p.input.reportValidity();
            return;
        }
        submitBtn.disabled = true; // guard against double submission
    });

    oldBasket.forEach(({ id, qty }) => { if (productIndex[id]) addToCart(productIndex[id], qty); });
    showCustomer();
    recalc();
})();
</script>
<?php endsection(); ?>

