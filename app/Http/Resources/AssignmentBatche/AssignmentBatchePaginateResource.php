<?php

namespace App\Http\Resources\AssignmentBatche;

use App\Enums\Assignment\StatusAssignmentEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentBatchePaginateResource extends JsonResource
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
            'description' => $this->description,
            'count_invoice' => $this->countInvoiceByFilter(),
            'count_invoice_pending' => $this->countInvoiceByFilter(['status' => StatusAssignmentEnum::ASSIGNMENT_EST_001->value]),
            'count_invoice_completed' => $this->countInvoiceByFilter(['status' => StatusAssignmentEnum::ASSIGNMENT_EST_003->value]),
        ];
    }
}
