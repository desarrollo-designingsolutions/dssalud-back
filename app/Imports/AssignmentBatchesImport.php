<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\AssignmentBatche;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class AssignmentBatchesImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return AssignmentBatche::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'description' => $row['description'],
                'status' => $row['status'],
            ]
        );
    }
}
