<?php

namespace App\Models;

use App\Enums\Schedule\ScheduleResponseStatusEnum;
use App\Enums\TypeEvent\TypeEventEnum;
use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use Cacheable, HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $casts = [
        'type_event' => TypeEventEnum::class,
        'response_status' => ScheduleResponseStatusEnum::class,
    ];

    public function getEmailsFormattedAttribute(): string
    {
        $emails = json_decode($this->emails, true) ?? [];
        return collect($emails)->implode(', ');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function third()
    {
        return $this->belongsTo(Third::class, 'third_id', 'id');
    }
}
