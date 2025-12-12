<?php

namespace App\Imports;

use App\Helpers\Constants;
use App\Models\File;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Agrega esta interfaz

class FileImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return File::updateOrCreate(
            ['id' => $row['id']],
            [
                'company_id' => Constants::COMPANY_UUID,
                'fileable_type' => $row['fileable_type'],
                'fileable_id' => $row['fileable_id'],
                'pathname' => $row['pathname'],
                'filename' => $row['filename'],
                'support_type_id' => $row['support_type_id'],
                'size' => $row['size'],
                'ext' => $row['ext'],
            ]
        );
    }
}
