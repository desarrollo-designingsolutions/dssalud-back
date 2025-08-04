<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ChunkDataImport implements ToArray, WithLimit, WithStartRow
{
    private int $startRow;

    private int $limit;

    public function __construct(int $startRow, int $limit)
    {
        $this->startRow = $startRow;
        $this->limit = $limit;
    }

    public function array(array $array): array
    {
        return $array;
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}
