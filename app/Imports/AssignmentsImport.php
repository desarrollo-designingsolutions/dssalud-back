<?php

namespace App\Imports;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\Assignment;
use Maatwebsite\Excel\Concerns\ToModel;

class AssignmentsImport implements ToModel
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Assignment::updateOrCreate(
            ['id' => $row[0]],
            [
                'company_id' => Constants::COMPANY_UUID,
                'assignment_batch_id' => $row[1],
                'user_id' => $row[2],
                'invoice_audit_id' => $row[3],
                'phase' => $row[4],
                'status' => $this->changeStatus($row[5]),
            ]
        );
    }

    public function changeStatus($status)
    {
        switch ($status) {
            case 'FINISHED':
                return StatusAssignmentEnum::ASSIGNMENT_EST_003->value;
                break;
            default:
                return $status;
        }
    }
}
