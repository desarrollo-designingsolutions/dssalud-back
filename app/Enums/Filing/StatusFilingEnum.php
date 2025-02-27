<?php

namespace App\Enums\Filing;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusFilingEnum: string
{
    use AttributableEnum;

    //OTROS
    #[Description('En proceso')]
    #[BackgroundColor('warning')]
    case IN_PROCESS = 'IN PROCESS';

    #[Description('Procesado')]
    #[BackgroundColor('success')]
    case PROCESSED = 'PROCESSED';

    #[Description('Radicado')]
    #[BackgroundColor('success')]
    case FILING = 'FILING';

    #[Description('Incompleto')]
    #[BackgroundColor('error')]
    case INCOMPLETE = 'INCOMPLETE';

    #[Description('Completo')]
    #[BackgroundColor('success')]
    case COMPLETED = 'COMPLETED';


    //PENDING


    //ERROR
    #[Description('Error Zip')]
    #[BackgroundColor('')]
    case ERROR_ZIP = 'ERROR_ZIP';

    #[Description('Error Txt')]
    #[BackgroundColor('')]
    case ERROR_TXT = 'ERROR_TXT';

}
