<?php

namespace App\Enums\Filing;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusFilingEnum: string
{
    use AttributableEnum;

    // OTROS
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

    #[Description('Error Zip')]
    #[BackgroundColor('error')]
    case FILING_EST_006 = 'FILING_EST_006';

    #[Description('Error Txt')]
    #[BackgroundColor('error')]
    case FILING_EST_007 = 'FILING_EST_007';

    #[Description('Abierto')]
    #[BackgroundColor('success')]
    case FILING_EST_008 = 'FILING_EST_008';

    #[Description('Cerrado')]
    #[BackgroundColor('warning')]
    case FILING_EST_009 = 'FILING_EST_009';

}
