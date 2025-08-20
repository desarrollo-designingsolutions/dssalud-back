<?php

namespace App\Http\Resources\Conciliation;

use App\Http\Resources\User\UserSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConciliationGenerateConciliationReportFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            "reconciliation_group_id" => $this->reconciliation_group_id,
            "company_id" => $this->company_id,

            "dateConciliation" => $this->dateConciliation,
            "nameIPSrepresentative" => $this->nameIPSrepresentative,
            "positionIPSrepresentative" => $this->positionIPSrepresentative,

            "elaborator_id" => new UserSelectInfiniteResource($this->elaborator),
            "elaborator_position" => $this->elaborator_position,
            "reviewer_id" => new UserSelectInfiniteResource($this->reviewer),
            "reviewer_position" => $this->reviewer_position,
            "approver_id" => new UserSelectInfiniteResource($this->approver),
            "approver_position" => $this->approver_position,
            "legal_representative_id" => new UserSelectInfiniteResource($this->legal_representative),
            "legal_representative_position" => $this->legal_representative_position,
            "health_audit_director_id" => new UserSelectInfiniteResource($this->health_audit_director),
            "health_audit_director_position" => $this->health_audit_director_position,
            "vp_planning_control_id" => new UserSelectInfiniteResource($this->vp_planning_control),
            "vp_planning_control_position" => $this->vp_planning_control_position,


        ];
    }
}
