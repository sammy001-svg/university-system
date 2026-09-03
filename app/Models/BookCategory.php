<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class BookCategory extends Model
{
    protected string $table = 'book_categories';
    protected bool $timestamps = false;
    protected array $fillable = ['code', 'name', 'description'];
    protected array $searchable = ['code', 'name'];
}
