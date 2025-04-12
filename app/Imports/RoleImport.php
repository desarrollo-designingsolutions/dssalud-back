<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Role;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class RoleImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Role::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'name' => $row['name'],
                'guard_name' => $row['guard_name'],
                'viewable' => $row['viewable'],
                'description' => $row['description'],
                'type' => $row['type'],
            ]
        );
    }
}
