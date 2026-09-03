<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Raise Support Ticket</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/services/tickets') ?>">&larr; Back to Tickets</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/services/tickets') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" value="<?= e(old('subject')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <?= enum_options(['technical','academic','finance','hostel','library','other'], old('category', 'technical')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <?= enum_options(['low','normal','high','urgent'], old('priority', 'normal')) ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description / Problem Details <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control" rows="5" required><?= e(old('body')) ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Submit Ticket</button>
            <a href="<?= url('/services/tickets') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
