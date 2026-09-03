<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Compose Message</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/messages') ?>">&larr; Back to Inbox</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/messages') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Recipient <span class="text-danger">*</span></label>
                <select name="recipient_id" class="form-select" required>
                    <?= options(array_column($users, 'label', 'id'), old('recipient_id'), 'Select User') ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" value="<?= e(old('subject')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Message Body <span class="text-danger">*</span></label>
                <textarea name="body" class="form-control" rows="5" required><?= e(old('body')) ?></textarea>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Send Message</button>
            <a href="<?= url('/messages') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
