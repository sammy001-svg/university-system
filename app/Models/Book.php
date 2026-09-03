<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Book extends Model
{
    protected string $table = 'books';
    protected array $fillable = [
        'accession_number', 'isbn', 'title', 'author', 'publisher', 'edition', 'publication_year',
        'category_id', 'shelf_location', 'total_copies', 'available_copies', 'price',
        'cover_image', 'description', 'status',
    ];
    protected array $searchable = ['b.title', 'b.author', 'b.isbn', 'b.accession_number', 'b.publisher'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('books', 'b')
            ->select(
                'b.*', 'bc.name AS category_name',
                '(SELECT COUNT(*) FROM book_loans bl WHERE bl.book_id = b.id AND bl.status IN ("borrowed","overdue")) AS on_loan'
            )
            ->leftJoin('book_categories bc', 'bc.id = b.category_id');
    }

    public function nextAccessionNumber(): string
    {
        $count = (int) Database::scalar('SELECT COUNT(*) FROM books');
        do {
            $count++;
            $number = 'ACC-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
        } while ($this->exists('accession_number', $number));
        return $number;
    }

    public function refreshAvailability(int $bookId): void
    {
        Database::statement(
            'UPDATE books SET available_copies = GREATEST(total_copies -
                (SELECT COUNT(*) FROM book_loans WHERE book_id = ? AND status IN ("borrowed","overdue")), 0)
             WHERE id = ?',
            [$bookId, $bookId]
        );
    }
}
