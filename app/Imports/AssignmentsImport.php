<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Assignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class AssignmentsImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Assignment::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'assignment_batch_id' => $row['assignment_batch_id'],
                'user_id' => $row['user_id'],
                'invoice_audit_id' => $row['invoice_audit_id'],
                'phase' => $row['phase'],
                'status' => $row['status'],
            ]
        );
    }
}
