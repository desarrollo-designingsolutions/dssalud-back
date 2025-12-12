<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Third;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class ThirdsImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Third::updateOrCreate(
            ['id' => $row['nit']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'name' => $row['razon_social'],
                'nit' => $row['nit'],
            ]
        );
    }
}
