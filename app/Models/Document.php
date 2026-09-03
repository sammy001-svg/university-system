<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Document extends Model
{
    protected string $table = 'documents';
    protected bool $timestamps = false;
    protected array $fillable = ['title', 'category', 'file_name', 'file_path', 'file_size', 'mime_type', 'owner_type', 'owner_id', 'uploaded_by', 'is_public'];
    protected array $searchable = ['title', 'file_name'];
}
