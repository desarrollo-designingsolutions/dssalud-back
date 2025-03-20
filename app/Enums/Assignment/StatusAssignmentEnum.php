<?php

namespace App\Enums\Assignment;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusAssignmentEnum: string
{
    use AttributableEnum;

    // OTROS
    #[Description('Sin Asignar')]
    #[BackgroundColor('warning')]
    case ASSIGNMENT_EST_001 = 'ASSIGNMENT_EST_001';

    #[Description('Asignado')]
    #[BackgroundColor('error')]
    case ASSIGNMENT_EST_002 = 'ASSIGNMENT_EST_002';

    #[Description('Finalizado')]
    #[BackgroundColor('success')]
    case ASSIGNMENT_EST_003 = 'ASSIGNMENT_EST_003';

    #[Description('Devolución')]
    #[BackgroundColor('info')]
    case ASSIGNMENT_EST_004 = 'ASSIGNMENT_EST_004';

}
