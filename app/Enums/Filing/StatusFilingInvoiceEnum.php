<?php

namespace App\Enums\Filing;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusFilingInvoiceEnum: string
{
    use AttributableEnum;
    // FILINGINVOICE_EST_001

    // OTROS
    #[Description('Pre radicado')]
    #[BackgroundColor('#F4511E')]
    case FILINGINVOICE_EST_001 = 'FILINGINVOICE_EST_001';

    #[Description('Radicado')]
    #[BackgroundColor('success')]
    case FILINGINVOICE_EST_002 = 'FILINGINVOICE_EST_002';

    #[Description('Validado')]
    #[BackgroundColor('success')]
    case FILINGINVOICE_EST_003 = 'FILINGINVOICE_EST_003';

    #[Description('Sin validar')]
    #[BackgroundColor('')]
    case FILINGINVOICE_EST_004 = 'FILINGINVOICE_EST_004';

    // ERROR
    #[Description('Error XML')]
    #[BackgroundColor('error')]
    case FILINGINVOICE_EST_005 = 'ERROR_XML';

}
