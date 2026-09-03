<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1><?= e($isNew ? 'Publish Announcement' : 'Edit Announcement') ?></h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/announcements') ?>">&larr; Back to Announcements</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/announcements') : url('/announcements/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= e(old('title', $record['title'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Target Audience <span class="text-danger">*</span></label>
                    <select name="audience" class="form-select" required>
                        <?= enum_options(['all','students','staff','lecturers','admins'], old('audience', $record['audience'] ?? 'all')) ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Body Content <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control" rows="6" required><?= e(old('body', $record['body'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Publish Announcement' : 'Save Changes' ?></button>
            <a href="<?= url('/announcements') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
