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
    case FILING_EST_001 = 'FILING_EST_001';

    #[Description('Procesado')]
    #[BackgroundColor('success')]
    case FILING_EST_002 = 'FILING_EST_002';

    #[Description('Radicado')]
    #[BackgroundColor('success')]
    case FILING_EST_003 = 'FILING_EST_003';

    #[Description('Incompleto')]
    #[BackgroundColor('error')]
    case FILING_EST_004 = 'FILING_EST_004';

    #[Description('Completo')]
    #[BackgroundColor('success')]
    case FILING_EST_005 = 'FILING_EST_005';


    //PENDING


    //ERROR
    #[Description('Error Zip')]
    #[BackgroundColor('')]
    case FILING_EST_006 = 'ERROR_ZIP';

    #[Description('Error Txt')]
    #[BackgroundColor('')]
    case FILING_EST_007 = 'ERROR_TXT';

}
