<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ApplicationDocument extends Model
{
    protected string $table = 'application_documents';
    protected bool $timestamps = false;
    protected array $fillable = ['application_id', 'document_type', 'file_name', 'file_path', 'file_size'];
    protected array $searchable = [];
}
