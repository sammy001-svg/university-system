<?php
declare(strict_types=1);

namespace App\Controllers\Library;

use App\Core\ResourceController;
use App\Core\Database;
use App\Models\Book;

final class BookController extends ResourceController
{
    protected string $title     = 'Books';
    protected string $entity    = 'Book';
    protected string $routeBase = '/library/books';
    protected string $permission = 'library';
    protected string $icon      = 'library';
    protected string $lede      = 'Library catalogue.';
    protected string $defaultSort = 'title';
    protected string $defaultDir  = 'asc';

    public function __construct() { $this->model = new Book(); }

    protected function columns(): array
    {
        return [
            ['key' => 'accession_number', 'label' => 'Accession No.', 'type' => 'code'],
            ['key' => 'title', 'label' => 'Title', 'type' => 'strong'],
            ['key' => 'author', 'label' => 'Author', 'type' => 'text'],
            ['key' => 'isbn', 'label' => 'ISBN', 'type' => 'text'],
            ['key' => 'available_copies', 'label' => 'Available', 'type' => 'number'],
            ['key' => 'total_copies', 'label' => 'Total', 'type' => 'number'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'width' => 8, 'required' => true],
            ['name' => 'author', 'label' => 'Author', 'width' => 4, 'required' => true],
            ['name' => 'isbn', 'label' => 'ISBN', 'width' => 4],
            ['name' => 'accession_number', 'label' => 'Accession No.', 'width' => 4, 'required' => true],
            ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'width' => 4, 'options' => $this->categoryOptions()],
            ['name' => 'publisher', 'label' => 'Publisher', 'width' => 6],
            ['name' => 'publish_year', 'label' => 'Year', 'type' => 'number', 'width' => 3],
            ['name' => 'edition', 'label' => 'Edition', 'width' => 3],
            ['name' => 'total_copies', 'label' => 'Total Copies', 'type' => 'number', 'width' => 4, 'required' => true],
            ['name' => 'available_copies', 'label' => 'Available', 'type' => 'number', 'width' => 4],
            ['name' => 'location', 'label' => 'Shelf Location', 'width' => 4],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'title'             => 'required|max:255',
            'author'            => 'required|max:200',
            'isbn'              => 'nullable|max:20',
            'accession_number'  => 'required|max:40|unique:books,accession_number,' . ($id ?? ''),
            'category_id'       => 'nullable|integer|exists:book_categories,id',
            'publisher'         => 'nullable|max:150',
            'publish_year'      => 'nullable|integer',
            'edition'           => 'nullable|max:20',
            'total_copies'      => 'required|integer',
            'available_copies'  => 'nullable|integer',
            'location'          => 'nullable|max:80',
        ];
    }

    private function categoryOptions(): array
    {
        $rows = Database::select('SELECT id, name FROM book_categories ORDER BY name');
        $out  = [];
        foreach ($rows as $row) { $out[$row['id']] = $row['name']; }
        return $out;
    }
}
