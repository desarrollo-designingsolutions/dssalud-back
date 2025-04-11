<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class UserImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return User::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'name' => $row['name'],
                'surname' => $row['surname'],
                'email' => $row['email'],
                'password' => $row['password'],
                'role_id' => $row['role_id'],
                'is_active' => $row['is_active'],
                'photo' => $row['photo'],
                'first_time' => $row['first_time'],
                'third_id' => $row['third_id'],
            ]
        );
    }
}
