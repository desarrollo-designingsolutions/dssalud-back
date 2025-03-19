<?php

namespace App\Models;

use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentBatche extends Model
{
    use HasUuids, Cacheable;

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assignment_batch_id');
    }
}
