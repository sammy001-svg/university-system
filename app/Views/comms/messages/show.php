<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1><?= e($message['subject']) ?></h1>
        <p class="lede">Date: <?= date_fmt($message['created_at']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/messages') ?>">&larr; Back to Inbox</a>
</div>

<div class="card p-4">
    <div class="card-body">
        <p class="card-text text-body"><?= nl2br(e($message['body'])) ?></p>
    </div>
</div>
<?php endsection(); ?>
