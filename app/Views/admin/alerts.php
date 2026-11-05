<?php
use App\Services\Alerts\Alerts;

layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div><h1>Email and SMS alerts</h1>
        <p class="lede">Messages sent to students, staff and parents. Email is <?= $mailOn ? 'delivered by the mail server' : '<strong>only logged</strong> (MAIL_ENABLED=false in .env)' ?>;
            SMS uses the <strong><?= e($smsDriver) ?></strong> gateway<?= $smsDriver === 'log' ? ' (nothing is sent - set SMS_DRIVER in .env)' : '' ?>.
            Choose which events go by SMS under <a href="<?= url('/admin/settings?group=notifications') ?>">Settings &rsaquo; Notifications</a>.</p></div>
    <form method="post" action="<?= url('/admin/alerts/send') ?>"><?= csrf_field() ?>
        <?php if (!empty($counts['failed'])): ?><input type="hidden" name="retry_failed" value="1"><?php endif; ?>
        <button class="btn btn-sm btn-outline-primary"><?= !empty($counts['failed']) ? 'Retry failed and send queued' : 'Send queued now' ?></button></form>
</div>

<nav class="el-tabs">
    <?php foreach (['' => 'All', 'queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'] as $key => $label): ?>
        <a class="<?= $status === $key ? 'active' : '' ?>" href="<?= url('/admin/alerts' . ($key !== '' ? '?status=' . $key : '')) ?>"><?= $label ?>
            <?php if ($key !== '' && !empty($counts[$key])): ?><span class="badge text-bg-<?= $key === 'failed' ? 'danger' : 'light' ?>"><?= (int) $counts[$key] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>When</th><th>To</th><th>Channel</th><th>Event</th><th>Message</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><?= icon('mail') ?><p>No messages.</p></div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted-sm text-nowrap"><?= fdatetime($r['created_at']) ?></td>
                    <td><div class="fw-semibold small"><?= e((string) $r['name']) ?></div><div class="text-muted-sm"><?= e($r['recipient']) ?></div></td>
                    <td><span class="badge text-bg-light"><?= strtoupper(e($r['channel'])) ?></span></td>
                    <td class="small"><?= e(Alerts::EVENTS[$r['event']] ?? $r['event']) ?></td>
                    <td class="small" style="max-width:380px"><?= e(str_limit($r['subject'] ?: strip_tags((string) $r['body']), 110)) ?></td>
                    <td><?= status_badge(['sent' => 'completed', 'queued' => 'pending', 'failed' => 'failed'][$r['status']]) ?>
                        <?php if ($r['error']): ?><div class="text-muted-sm"><?= e($r['error']) ?></div><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
