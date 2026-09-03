<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Issue Library Book</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/library/loans') ?>">&larr; Back to Loans</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/library/loans/issue') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Book Title <span class="text-danger">*</span></label>
                    <select name="book_id" class="form-select" required>
                        <?= options(array_column($books, 'label', 'id'), old('book_id'), 'Select Book') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Borrower (Student) <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <?= options(array_column($students, 'label', 'id'), old('student_id'), 'Select Student') ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Issue Book</button>
            <a href="<?= url('/library/loans') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
