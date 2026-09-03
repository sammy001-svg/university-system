<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Hostel Allocations</h1>
        <p class="lede">Room assignments &amp; student accommodation.</p>
    </div>
    <?php if (can('hostel.allocate')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/hostel/allocations/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Allocate Room
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('hostel') ?> Room Allocations (<?= count($allocations) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Hostel</th>
                    <th>Room</th>
                    <th>Floor</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($allocations)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('hostel') ?><p>No active hostel allocations.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($allocations as $a): ?>
                <tr>
                    <td class="fw-semibold"><?= e($a['hostel_name']) ?></td>
                    <td>Rm <?= e($a['room_number']) ?></td>
                    <td><?= e($a['floor'] ?? 'G') ?></td>
                    <td><code><?= e($a['admission_number']) ?></code></td>
                    <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-end">
                        <?php if ($a['status'] === 'active' && can('hostel.allocate')): ?>
                            <form method="post" action="<?= url('/hostel/allocations/' . $a['id'] . '/checkout') ?>" class="d-inline" onsubmit="return confirm('Check out student?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-warning">Checkout</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
