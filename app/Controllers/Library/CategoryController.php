<?php
declare(strict_types=1);

namespace App\Controllers\Library;

use App\Core\ResourceController;
use App\Models\BookCategory;

final class CategoryController extends ResourceController
{
    protected string $title = 'Book Categories';
    protected string $entity = 'Category';
    protected string $routeBase = '/library/categories';
    protected string $permission = 'library';
    protected string $icon = 'book';
    protected string $lede = 'Classification used to organise the library catalogue.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new BookCategory();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Category', 'type' => 'strong'],
            ['key' => 'description', 'label' => 'Description'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Category code', 'width' => 4, 'required' => true],
            ['name' => 'name', 'label' => 'Category name', 'width' => 8, 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:20|unique:book_categories,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'description' => 'nullable|max:255',
        ];
    }
}
