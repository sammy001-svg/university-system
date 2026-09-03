<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Support Desk Tickets</h1>
        <p class="lede">Helpdesk requests &amp; support tickets.</p>
    </div>
    <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/services/tickets/create') ?>">
        <?= icon('plus', 'ico-sm') ?> New Ticket
    </a>
</div>

<div class="card">
    <div class="card-header"><?= icon('tickets') ?> Helpdesk Queue (<?= count($tickets) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ref No.</th>
                    <th>Subject</th>
                    <th>Requester</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('tickets') ?><p>No support tickets.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><code><?= e($t['ticket_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($t['subject']) ?></td>
                    <td class="text-muted-sm"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td>
                    <td><?= e(humanize($t['category'])) ?></td>
                    <td><?= status_badge($t['priority']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/services/tickets/' . $t['id']) ?>"><?= icon('eye', 'ico-sm') ?> View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
