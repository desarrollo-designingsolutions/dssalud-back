<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConciliationReport extends Model
{
    use Cacheable, HasUuids;

    protected $guarded = [];

    public function elaborator()
    {
        return $this->belongsTo(User::class, 'elaborator_id', 'id');
    }
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'id');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id', 'id');
    }
    public function legal_representative()
    {
        return $this->belongsTo(User::class, 'legal_representative_id', 'id');
    }
    public function health_audit_director()
    {
        return $this->belongsTo(User::class, 'health_audit_director_id', 'id');
    }
    public function vp_planning_control()
    {
        return $this->belongsTo(User::class, 'vp_planning_control_id', 'id');
    }
}
