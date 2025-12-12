<?php

namespace App\Enums\AssignmentBatche;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusAssignmentBatcheEnum: string
{
    use AttributableEnum;

    // OTROS
    #[Description('Sin Asignar')]
    #[BackgroundColor('warning')]
    case ASSIGNMENT_BATCHE_EST_001 = 'ASSIGNMENT_BATCHE_EST_001';

    #[Description('Asignado')]
    #[BackgroundColor('error')]
    case ASSIGNMENT_BATCHE_EST_002 = 'ASSIGNMENT_BATCHE_EST_002';

    #[Description('Finalizado')]
    #[BackgroundColor('success')]
    case ASSIGNMENT_BATCHE_EST_003 = 'ASSIGNMENT_BATCHE_EST_003';
}
