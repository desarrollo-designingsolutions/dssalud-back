<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\SupportType;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class SupportTypeImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return SupportType::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'code' => $row['code'],
                'name' => $row['name'],
                'is_active' => $row['is_active'],
            ]
        );
    }
}
