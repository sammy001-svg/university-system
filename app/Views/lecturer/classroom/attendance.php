<?php
layout('layouts.app');
section('content');
$joined = count(array_filter($students, static fn (array $s): bool => $s['first_joined_at'] !== null));
?>
<div class="page-head">
    <div>
        <h1><?= e($session['title']) ?></h1>
        <p class="lede"><?= e($offering['code']) ?> &middot; <?= fdatetime($session['starts_at']) ?> &middot;
            <strong><?= $joined ?></strong> of <?= count($students) ?> students joined</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $offering['id'] . '/classroom') ?>">&larr; Online classroom</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover mb-0">
            <thead><tr><th>Student ID</th><th>Name</th><th>Joined</th><th>Last joined</th><th class="text-end">Times</th></tr></thead>
            <tbody>
            <?php if ($students === []): ?>
                <tr><td colspan="5"><div class="empty-state"><?= icon('users') ?><p>No students on this class list.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= e($s['admission_number']) ?></code></td>
                    <td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
                    <td><?= $s['first_joined_at'] ? '<span class="badge text-bg-success">' . e(date('H:i', strtotime($s['first_joined_at']))) . '</span>' : '<span class="badge text-bg-light">Did not join</span>' ?></td>
                    <td class="text-muted-sm"><?= $s['last_joined_at'] ? fdatetime($s['last_joined_at']) : '' ?></td>
                    <td class="text-end"><?= (int) $s['join_count'] ?: '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
