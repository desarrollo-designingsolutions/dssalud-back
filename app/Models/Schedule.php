<?php

namespace App\Models;

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
    ];

    public function getEmailsFormattedStringAttribute(): string
    {
        $emails = json_decode($this->emails, true) ?? [];

        return collect($emails)->implode(', ');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scheduleable()
    {
        return $this->morphTo(__FUNCTION__, 'scheduleable_type', 'scheduleable_id');
    }

    // Relationship to the original event (the event this was rescheduled from)
    public function originalSchedule()
    {
        return $this->belongsTo(Schedule::class, 'rescheduled_from_id');
    }

    // Relationship to events rescheduled from this event
    public function rescheduledEvents()
    {
        return $this->hasMany(Schedule::class, 'rescheduled_from_id');
    }
}
