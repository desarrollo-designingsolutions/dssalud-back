<?php

namespace App\Enums\Filing;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum TypeFilingEnum: string
{
    use AttributableEnum;

    #[Description('Radicación antigua')]
    case FILING_TYPE_001 = 'FILING_TYPE_001';

    #[Description('Radicación 2275')]
    case FILING_TYPE_002 = 'FILING_TYPE_002';
}
