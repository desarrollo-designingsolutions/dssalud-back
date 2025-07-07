<?php

namespace App\Models;

use App\Enums\Schedule\ScheduleResponseStatusEnum;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleConciliation extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'response_status' => ScheduleResponseStatusEnum::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function third()
    {
        return $this->belongsTo(Third::class, 'third_id', 'id');
    }

    public function reconciliation_group()
    {
        return $this->belongsTo(ReconciliationGroup::class, 'reconciliation_group_id', 'id');
    }
}
