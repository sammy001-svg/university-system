<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ResearchOutput extends Model
{
    protected string $table = 'research_outputs';
    protected bool $timestamps = true;
    protected array $fillable = ['staff_id', 'output_type', 'title', 'authors', 'venue', 'year', 'url', 'amount', 'status', 'peer_reviewed', 'abstract'];
    protected array $searchable = ['r.title', 'r.authors', 'r.venue'];
}
