<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\InvoiceAudit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class InvoiceAuditsImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return InvoiceAudit::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'third_id' => $row['third_id'],
                'invoice_number' => $row['invoice_number'],
                'total_value' => $row['total_value'],
                'origin' => $row['origin'],
                'modality' => $row['modality'],
                'regimen' => $row['regimen'],
                'coverage' => $row['coverage'],
                'contract_number' => $row['contract_number'],
            ]
        );
    }
}
