<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Ticket: <?= e($ticket['ticket_number']) ?></h1>
        <p class="lede"><?= e($ticket['subject']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/services/tickets') ?>">&larr; Back to Tickets</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= icon('tickets') ?> Ticket Details</span>
                <?= status_badge($ticket['status']) ?>
            </div>
            <div class="card-body">
                <p class="mb-3"><?= nl2br(e($ticket['body'])) ?></p>
                <div class="text-muted-sm border-top pt-2">
                    Submitted by <?= e($ticket['first_name'] . ' ' . $ticket['last_name']) ?> on <?= date_fmt($ticket['created_at']) ?>
                </div>
            </div>
        </div>

        <h5 class="mb-3">Replies</h5>
        <?php foreach ($replies as $r): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-primary"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                        <small class="text-muted"><?= date_fmt($r['created_at']) ?></small>
                    </div>
                    <p class="mb-0"><?= nl2br(e($r['body'])) ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (can('tickets.edit')): ?>
        <div class="card mt-4">
            <div class="card-header"><?= icon('plus') ?> Post Reply</div>
            <form method="post" action="<?= url('/services/tickets/' . $ticket['id'] . '/reply') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <textarea name="body" class="form-control" rows="3" placeholder="Type your response..." required></textarea>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if (can('tickets.edit')): ?>
        <div class="card">
            <div class="card-header"><?= icon('edit') ?> Update Ticket Status</div>
            <form method="post" action="<?= url('/services/tickets/' . $ticket['id'] . '/status') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <select name="status" class="form-select mb-3">
                        <?= enum_options(['open','in_progress','resolved','closed'], $ticket['status']) ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary w-100">Update Status</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
