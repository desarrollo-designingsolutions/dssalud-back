<?php

namespace App\Enums\Filing;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum TypeFilingEnum: string
{
    use AttributableEnum;

    #[Description('Radicación antigua')]
    case RADICATION_OLD = 'RADICATION_OLD';

    #[Description('Radicación 2275')]
    case RADICATION_2275 = 'RADICATION_2275';
}
