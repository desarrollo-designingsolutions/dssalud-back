<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Patient;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class PatientsImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Patient::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'invoice_audit_id' => $row['invoice_audit_id'],
                'type_identification' => $row['type_identification'],
                'identification_number' => $row['identification_number'],
                'first_name' => $row['first_name'],
                'second_name' => $row['second_name'],
                'first_surname' => $row['first_surname'],
                'second_surname' => $row['second_surname'],
                'gender' => $row['gender'],
            ]
        );
    }
}
