<?php
/**
 * Generic list screen.
 * Expects: title, lede, iconName, entity, routeBase, columns, result,
 *          sort, dir, filters, canCreate/canEdit/canDelete, rowActions
 */
layout('layouts.app');
section('content');
$rows = $result['data'];
?>
<div class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <?php if ($lede !== ''): ?><p class="lede"><?= e($lede) ?></p><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>">
            <?= icon('download', 'ico-sm') ?> Export
        </a>
        <?php if ($canCreate): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url($routeBase . '/create') ?>">
                <?= icon('plus', 'ico-sm') ?> New <?= e($entity) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= url($routeBase) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><?= icon('search', 'ico-sm') ?></span>
                <input type="search" name="q" class="form-control" value="<?= e($_GET['q'] ?? '') ?>"
                       placeholder="Search <?= e(strtolower($title)) ?>...">
            </div>
        </div>

        <?php foreach ($filters as $filter): ?>
            <div class="col-md-<?= (int) ($filter['width'] ?? 3) ?>">
                <label class="form-label"><?= e($filter['label']) ?></label>
                <select name="<?= e($filter['name']) ?>" class="form-select form-select-sm" data-auto-submit>
                    <?= options($filter['options'], $_GET[$filter['name']] ?? '', $filter['placeholder'] ?? 'All') ?>
                </select>
            </div>
        <?php endforeach; ?>

        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100" type="submit">Apply</button>
        </div>
        <?php if (array_filter($_GET ?? [], static fn ($v, $k) => $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH)): ?>
            <div class="col-auto">
                <a class="btn btn-sm btn-link" href="<?= url($routeBase) ?>">Clear</a>
            </div>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <?= icon($iconName) ?> <?= e($title) ?>
        <span class="badge text-bg-light ms-1"><?= number_format((int) $result['total']) ?></span>
        <span class="ms-auto text-muted-sm fw-normal">
            <?php if ((int) $result['total'] > 0): ?>
                Showing <?= (int) $result['from'] ?>&ndash;<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th <?= isset($column['align']) ? 'class="text-' . e($column['align']) . '"' : '' ?>>
                        <?php if (($column['sortable'] ?? true) !== false): ?>
                            <?= sort_link($column['label'], $column['key'], $sort, $dir) ?>
                        <?php else: ?>
                            <?= e($column['label']) ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th class="text-end" style="width:1%">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="<?= count($columns) + 1 ?>">
                        <div class="empty-state">
                            <?= icon($iconName) ?>
                            <p class="mb-1">No <?= e(strtolower($title)) ?> found.</p>
                            <?php if ($canCreate): ?>
                                <a href="<?= url($routeBase . '/create') ?>">Create the first one</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <td <?= isset($column['align']) ? 'class="text-' . e($column['align']) . '"' : '' ?>>
                            <?php partial('resource.cell', ['column' => $column, 'row' => $row, 'routeBase' => $routeBase]); ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end text-nowrap">
                        <?php foreach ($rowActions as $action): ?>
                            <a class="btn btn-sm btn-light" title="<?= e($action['label']) ?>"
                               href="<?= url(str_replace('{id}', (string) $row['id'], $action['url'])) ?>">
                                <?= icon($action['icon'] ?? 'eye', 'ico-sm') ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($canEdit): ?>
                            <a class="btn btn-sm btn-light" title="Edit" href="<?= url($routeBase . '/' . $row['id'] . '/edit') ?>">
                                <?= icon('edit', 'ico-sm') ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form class="d-inline" method="post" action="<?= url($routeBase . '/' . $row['id']) ?>"
                                  data-confirm="Delete this <?= e(strtolower($entity)) ?>? This cannot be undone.">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) $result['last_page'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int) $result['page'] ?> of <?= (int) $result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
