<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Core\Upload;
use App\Models\Document;

final class DocumentController extends ResourceController
{
    protected string $title = 'Documents';
    protected string $entity = 'Document';
    protected string $routeBase = '/documents';
    protected string $permission = 'documents';
    protected string $icon = 'file';
    protected string $lede = 'Policies, forms and reference material shared across the institution.';
    protected string $defaultSort = 'created_at';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new Document();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Document', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'humanize'],
            ['key' => 'file_name', 'label' => 'File'],
            ['key' => 'is_public', 'label' => 'Public', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'created_at', 'label' => 'Uploaded', 'type' => 'ago'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Document title', 'width' => 8, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['general', 'policy', 'form', 'report', 'circular', 'curriculum', 'minutes']],
            ['name' => 'file_path', 'label' => 'File', 'type' => 'file', 'width' => 8],
            ['name' => 'is_public', 'label' => 'Visible to all users', 'type' => 'checkbox', 'width' => 4],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'title' => 'required|max:200',
            'category' => 'required',
            'is_public' => 'nullable|integer',
        ];
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = QueryBuilder::table('documents', 'd')
            ->select('d.*', 'CONCAT(u.first_name, " ", u.last_name) AS uploaded_by_name')
            ->leftJoin('users u', 'u.id = d.uploaded_by');

        if ($request->filled('category')) {
            $query->where('d.category', $request->query('category'));
        }
        // Non-managers only see public documents and their own uploads.
        if (!Auth::can('documents.edit')) {
            $query->whereRaw('(d.is_public = 1 OR d.uploaded_by = ?)', [Auth::id()]);
        }
        return $query;
    }

    protected function filters(): array
    {
        return [[
            'name'    => 'category',
            'label'   => 'Category',
            'width'   => 3,
            'options' => [
                'general' => 'General', 'policy' => 'Policy', 'form' => 'Form', 'report' => 'Report',
                'circular' => 'Circular', 'curriculum' => 'Curriculum', 'minutes' => 'Minutes',
            ],
        ]];
    }

    /** Store the uploaded file and record its metadata. */
    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $file = $request->file('file_path');
        if ($file !== null) {
            $stored = Upload::store($file, 'documents', Upload::DOCUMENTS, 15);
            $data['file_path'] = $stored['path'];
            $data['file_name'] = $stored['name'];
            $data['file_size'] = $stored['size'];
            $data['mime_type'] = $stored['mime'];
        } else {
            unset($data['file_path']);
        }
        if ($id === null) {
            $data['uploaded_by'] = Auth::id();
            if (!isset($data['file_path'])) {
                $this->error('Please choose a file to upload.', $this->routeBase . '/create');
            }
        }
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        Upload::delete($record['file_path'] ?? null);
        return null;
    }
}
