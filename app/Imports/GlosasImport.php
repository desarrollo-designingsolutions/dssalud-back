<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Glosa;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class GlosasImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Glosa::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'user_id' => $row['user_id'],
                'service_id' => $row['service_id'],
                'code_glosa_id' => $row['code_glosa_id'],
                'glosa_value' => $row['glosa_value'],
                'observation' => $row['observation'],
            ]
        );
    }
}
