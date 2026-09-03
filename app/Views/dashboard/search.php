<?php
layout('layouts.app');
$pageTitle = 'Search';
section('content');

$groups = [
    'students' => ['Students', 'student', '/students/{id}'],
    'staff'    => ['Staff', 'users', '/staff/{id}'],
    'programs' => ['Programmes', 'book', '/academics/programs/{id}/curriculum'],
    'courses'  => ['Courses', 'book', '/academics/courses/{id}/edit'],
    'books'    => ['Library titles', 'book', '/library/books/{id}/edit'],
];
$totalFound = array_sum(array_map('count', $results));
?>
<div class="page-head">
    <div>
        <h1>Search results</h1>
        <p class="lede"><?= $totalFound ?> match(es) for &ldquo;<?= e($term) ?>&rdquo;</p>
    </div>
</div>

<?php if ($term === ''): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('search') ?><p>Type something in the search box above.</p></div></div></div>
<?php elseif ($totalFound === 0): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('search') ?><p>Nothing matched your search.</p></div></div></div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($groups as $key => [$label, $iconName, $urlPattern]): ?>
        <?php if (empty($results[$key])) { continue; } ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><?= icon($iconName) ?> <?= e($label) ?>
                    <span class="badge text-bg-light"><?= count($results[$key]) ?></span></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($results[$key] as $row): ?>
                        <li class="list-group-item">
                            <a href="<?= url(str_replace('{id}', (string) $row['id'], $urlPattern)) ?>" class="fw-semibold">
                                <?= e($row['title'] ?? $row['name'] ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?>
                            </a>
                            <div class="text-muted-sm">
                                <?= e($row['code'] ?? $row['admission_number'] ?? $row['staff_number'] ?? $row['accession_number'] ?? '') ?>
                                <?php if (!empty($row['program_name'])): ?> &middot; <?= e($row['program_name']) ?><?php endif; ?>
                                <?php if (!empty($row['author'])): ?> &middot; <?= e($row['author']) ?><?php endif; ?>
                                <?php if (!empty($row['designation'])): ?> &middot; <?= e($row['designation']) ?><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
