<?php
layout('layouts.app');
section('content');
?>
<div class="page-head"><div><h1>Clubs &amp; Societies</h1><p class="lede">Get involved: join clubs and societies that match your interests.</p></div></div>
<?php if ($clubs === []): ?><div class="card"><div class="card-body"><div class="empty-state"><?= icon('users') ?><p>No clubs are registered yet.</p></div></div></div><?php endif; ?>
<div class="row g-3">
    <?php foreach ($clubs as $c): ?>
        <div class="col-md-6 col-xl-4">
            <div class="course-card">
                <div class="course-card-band" style="<?= $c['my_role'] ? 'background:var(--ok)' : '' ?>"></div>
                <div class="course-card-body">
                    <span class="course-code"><?= e(strtoupper(humanize($c['category']))) ?></span>
                    <h3><?= e($c['name']) ?></h3>
                    <?php if ($c['description']): ?><p class="small text-muted mb-2"><?= e(str_limit($c['description'], 140)) ?></p><?php endif; ?>
                    <div class="text-muted-sm"><?= (int) $c['members'] ?> members<?= $c['patron_name'] ? ' &middot; patron ' . e($c['patron_name']) : '' ?></div>
                    <?php if ($c['meeting_schedule']): ?><div class="text-muted-sm"><?= e($c['meeting_schedule']) ?></div><?php endif; ?>
                </div>
                <div class="course-card-foot">
                    <?php if ($c['my_role']): ?>
                        <span class="text-success fw-semibold"><?= $c['my_role'] === 'member' ? 'You are a member' : 'You are ' . e(humanize($c['my_role'])) ?></span>
                        <?php if ($c['my_role'] === 'member'): ?>
                            <form class="ms-auto" method="post" action="<?= url('/portal/clubs/' . $c['id']) ?>" data-confirm="Leave <?= e($c['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="leave" value="1"><button class="btn btn-sm btn-link p-0">Leave</button></form>
                        <?php endif; ?>
                    <?php elseif ((int) $c['open_to_join'] === 1): ?>
                        <form method="post" action="<?= url('/portal/clubs/' . $c['id']) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Join</button></form>
                    <?php else: ?><span>By invitation</span><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
