<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\Service;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class ServicesImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return Service::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'invoice_audit_id' => $row['invoice_audit_id'],
                'patient_id' => $row['patient_id'],
                'detail_code' => $row['detail_code'],
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_value' => $row['unit_value'],
                'total_value' => $row['total_value'],
                'value_glosa' => $row['value_glosa'],
                'value_approved' => $row['value_approved'],
            ]
        );
    }
}
