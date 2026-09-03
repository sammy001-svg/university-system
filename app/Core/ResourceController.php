<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Convention-driven CRUD controller.
 *
 * A subclass declares its model, labels, permission prefix, table columns and
 * form fields; index/create/store/edit/update/destroy come for free, complete
 * with search, filtering, pagination, validation, auditing and flash messages.
 */
abstract class ResourceController extends Controller
{
    protected Model $model;
    /** Plural heading, e.g. "Faculties". */
    protected string $title = 'Records';
    /** Singular noun used in messages, e.g. "Faculty". */
    protected string $entity = 'Record';
    /** URL prefix without a trailing slash, e.g. "/academics/faculties". */
    protected string $routeBase = '/';
    /** Permission prefix; ".view", ".create", ".edit", ".delete" are appended. */
    protected string $permission = '';
    protected string $lede = '';
    protected string $icon = 'clipboard';
    protected int $listPerPage = 20;
    protected string $defaultSort = 'id';
    protected string $defaultDir = 'desc';

    /** @return array<int,array<string,mixed>> table column definitions */
    abstract protected function columns(): array;

    /** @return array<int,array<string,mixed>> form field definitions */
    abstract protected function fields(): array;

    /** Validation rules; $id is null when creating. */
    abstract protected function rules(?int $id): array;

    /** Base listing query - override to join related tables. */
    protected function listQuery(Request $request): QueryBuilder
    {
        return $this->model->query();
    }

    /** Extra data every form needs (dropdown options and the like). */
    protected function formData(): array
    {
        return [];
    }

    /** Hook: adjust the payload just before it is written. */
    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        return $data;
    }

    /** Hook: run after a create or update. */
    protected function afterSave(int $id, array $data, Request $request, bool $isNew): void
    {
    }

    /** Hook: veto a delete by returning a message. */
    protected function guardDelete(array $record): ?string
    {
        return null;
    }

    /** @return array<int,array<string,mixed>> filter-bar controls */
    protected function filters(): array
    {
        return [];
    }

    /** @return array<int,array<string,mixed>> extra per-row links */
    protected function rowActions(): array
    {
        return [];
    }

    protected function permit(string $action): void
    {
        if ($this->permission !== '') {
            $this->authorize($this->permission . '.' . $action);
        }
    }

    /** Friendly field labels derived from the form definition. */
    protected function labels(): array
    {
        $labels = [];
        foreach ($this->fields() as $field) {
            $labels[$field['name']] = $field['label'];
        }
        return $labels;
    }

    public function index(Request $request): string
    {
        $this->permit('view');

        $sort = (string) $request->query('sort', $this->defaultSort);
        $dir  = strtolower((string) $request->query('dir', $this->defaultDir)) === 'asc' ? 'ASC' : 'DESC';

        $allowedSorts = array_column($this->columns(), 'key');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = $this->defaultSort;
        }

        $query = $this->listQuery($request)
            ->search((string) $request->query('q', ''), $this->model->searchable())
            ->orderBy($sort, $dir);

        $result = $query->paginate($this->page($request), $this->perPage($request, $this->listPerPage));

        return $this->view('resource.index', array_merge($this->formData(), [
            'pageTitle'  => $this->title,
            'title'      => $this->title,
            'lede'       => $this->lede,
            'iconName'   => $this->icon,
            'entity'     => $this->entity,
            'routeBase'  => $this->routeBase,
            'columns'    => $this->columns(),
            'result'     => $result,
            'sort'       => $sort,
            'dir'        => strtolower($dir),
            'filters'    => $this->filters(),
            'canCreate'  => $this->permission === '' || Auth::can($this->permission . '.create'),
            'canEdit'    => $this->permission === '' || Auth::can($this->permission . '.edit'),
            'canDelete'  => $this->permission === '' || Auth::can($this->permission . '.delete'),
            'rowActions' => $this->rowActions(),
        ]));
    }

    public function create(Request $request): string
    {
        $this->permit('create');
        return $this->view('resource.form', array_merge($this->formData(), [
            'pageTitle' => 'New ' . $this->entity,
            'title'     => 'New ' . $this->entity,
            'iconName'  => $this->icon,
            'entity'    => $this->entity,
            'routeBase' => $this->routeBase,
            'fields'    => $this->fields(),
            'record'    => [],
            'isNew'     => true,
        ]));
    }

    public function store(Request $request): never
    {
        $this->permit('create');
        $this->verifyCsrf($request);

        $data = $this->validate($request, $this->rules(null), $this->labels());
        $data = $this->beforeSave($data, $request, null);

        $id = $this->model->create($data);
        Audit::created($this->entity, $id, $data, $this->permission);
        $this->afterSave($id, $data, $request, true);

        Session::clearOldInput();
        $this->success($this->entity . ' created successfully.', $this->routeBase);
    }

    public function edit(Request $request, string $id): string
    {
        $this->permit('edit');
        $record = $this->findOrFail($this->model, (int) $id, $this->entity);

        return $this->view('resource.form', array_merge($this->formData(), [
            'pageTitle' => 'Edit ' . $this->entity,
            'title'     => 'Edit ' . $this->entity,
            'iconName'  => $this->icon,
            'entity'    => $this->entity,
            'routeBase' => $this->routeBase,
            'fields'    => $this->fields(),
            'record'    => $record,
            'isNew'     => false,
        ]));
    }

    public function update(Request $request, string $id): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);

        $record = $this->findOrFail($this->model, (int) $id, $this->entity);
        $data   = $this->validate($request, $this->rules((int) $id), $this->labels());
        $data   = $this->beforeSave($data, $request, (int) $id);

        $this->model->update((int) $id, $data);
        Audit::updated($this->entity, (int) $id, $record, $data, $this->permission);
        $this->afterSave((int) $id, $data, $request, false);

        Session::clearOldInput();
        $this->success($this->entity . ' updated successfully.', $this->routeBase);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->permit('delete');
        $this->verifyCsrf($request);

        $record = $this->findOrFail($this->model, (int) $id, $this->entity);

        $veto = $this->guardDelete($record);
        if ($veto !== null) {
            $this->error($veto, $this->routeBase);
        }

        try {
            $this->model->delete((int) $id);
        } catch (\PDOException $e) {
            Logger::warning('Delete blocked by constraint', ['entity' => $this->entity, 'id' => $id]);
            $this->error(
                'This ' . strtolower($this->entity) . ' is referenced by other records and cannot be deleted.',
                $this->routeBase
            );
        }

        Audit::deleted($this->entity, (int) $id, $record, $this->permission);
        $this->success($this->entity . ' deleted.', $this->routeBase);
    }
}
