<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Accommodation</h1>
        <p class="lede">Hostel room allocation status.</p>
    </div>
</div>

<div class="card p-4">
    <?php if (!$allocation): ?>
        <div class="empty-state">
            <?= icon('hostel') ?>
            <p>You have no active hostel room allocation.</p>
        </div>
    <?php else: ?>
        <h5 class="fw-bold mb-3"><?= e($allocation['hostel_name']) ?></h5>
        <dl class="row mb-0">
            <dt class="col-sm-4">Room Number</dt><dd class="col-sm-8">Rm <?= e($allocation['room_number']) ?></dd>
            <dt class="col-sm-4">Floor</dt><dd class="col-sm-8"><?= e($allocation['floor'] ?? 'G') ?></dd>
            <dt class="col-sm-4">Check-In Date</dt><dd class="col-sm-8"><?= date_fmt($allocation['check_in_date']) ?></dd>
            <dt class="col-sm-4">Allocation Status</dt><dd class="col-sm-8"><?= status_badge($allocation['status']) ?></dd>
        </dl>
    <?php endif; ?>
</div>
<?php endsection(); ?>
