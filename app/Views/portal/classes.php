<?php
use App\Services\Elearning\ClassroomService;

layout('layouts.app');
section('content');
$row = static function (array $s, string $mode): string {
    $t = strtotime($s['starts_at']);
    ob_start(); ?>
    <div class="el-item">
        <div class="el-when <?= $s['phase'] === 'live' ? 'live' : '' ?>"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
        <div class="el-item-main">
            <div class="el-item-title"><a href="<?= url('/portal/courses/' . $s['offering_id']) ?>"><?= e($s['code']) ?></a> &middot; <?= e($s['title']) ?>
                <?php if ($s['phase'] === 'live'): ?><span class="live-badge ms-1">Live</span>
                <?php elseif ($s['phase'] === 'cancelled'): ?><span class="badge text-bg-secondary">Cancelled</span><?php endif; ?></div>
            <div class="el-item-meta"><?= date('l j M, H:i', $t) ?>&ndash;<?= date('H:i', strtotime(ClassroomService::endsAt($s))) ?>
                &middot; <?= e($s['course_title']) ?>
                <?php if ($mode === 'past' && $s['phase'] === 'ended'): ?>&middot; <?= $s['joined_at'] ? '<span class="text-success">attended</span>' : 'not attended' ?><?php endif; ?>
                <?php if ($mode !== 'past' && $s['passcode']): ?>&middot; passcode <code><?= e($s['passcode']) ?></code><?php endif; ?></div>
        </div>
        <?php if ($s['can_join']): ?>
            <a class="btn btn-sm btn-primary" href="<?= url('/portal/classes/' . $s['id'] . '/join') ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Join</a>
        <?php elseif ($mode === 'upcoming'): ?>
            <span class="text-muted-sm text-nowrap">in <?= e(time_until($s['starts_at'])) ?></span>
        <?php elseif ($s['recording_url'] && $s['phase'] === 'ended'): ?>
            <a class="btn btn-sm btn-light" href="<?= e($s['recording_url']) ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Recording</a>
        <?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
    <div>
        <h1>Online Classes</h1>
        <p class="lede">Live classes for your registered courses. The Join button opens <?= ClassroomService::EARLY_JOIN_MINUTES ?> minutes before each class starts.</p>
    </div>
    <?php if ($rate !== null): ?>
        <div class="text-end"><div class="figure-lg"><?= $rate ?>%</div><div class="text-muted-sm">online class attendance</div></div>
    <?php endif; ?>
</div>

<?php if ($groups['live'] !== []): ?>
    <div class="card mb-3" style="border-color:#fecaca">
        <div class="card-header"><span class="live-badge">Live now</span></div>
        <div class="card-body py-1"><?php foreach ($groups['live'] as $s) { echo $row($s, 'live'); } ?></div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><?= icon('calendar') ?> Upcoming <span class="badge text-bg-light"><?= count($groups['upcoming']) ?></span></div>
            <div class="card-body py-1">
                <?php if ($groups['upcoming'] === []): ?><div class="empty-state py-4"><?= icon('video') ?><p>No upcoming online classes.</p></div><?php endif; ?>
                <?php foreach ($groups['upcoming'] as $s) { echo $row($s, 'upcoming'); } ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><?= icon('clock') ?> Past classes and recordings</div>
            <div class="card-body py-1">
                <?php if ($groups['past'] === []): ?><p class="text-muted-sm py-3 mb-0">None yet.</p><?php endif; ?>
                <?php foreach (array_slice($groups['past'], 0, 20) as $s) { echo $row($s, 'past'); } ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
