<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\InvoiceAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;

// Agrega esta interfaz

class InvoiceAuditsImport implements ShouldQueue, ToModel, WithChunkReading
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return InvoiceAudit::updateOrCreate(
            ['id' => $row[0]],
            [
                'company_id' => Constants::COMPANY_UUID,
                'third_id' => $row[1],
                'invoice_number' => $row[2],
                'total_value' => $row[3],
                'origin' => $row[4],
                'modality' => $row[8],
                'regimen' => $row[9],
                'coverage' => $row[10],
                'contract_number' => $row[11],
            ]
        );
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE;
    }
}
