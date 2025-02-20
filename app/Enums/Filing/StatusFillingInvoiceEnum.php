<?php

namespace App\Enums\Filing;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusFillingInvoiceEnum: string
{
    use AttributableEnum;

    //OTROS
    #[Description('Pre radicado')]
    #[BackgroundColor('#F4511E')]
    case PRE_FILING = 'PRE_FILING';

    #[Description('Radicado')]
    #[BackgroundColor('success')]
    case FILING = 'FILING';


    #[Description('Validado')]
    #[BackgroundColor('success')]
    case VALIDATED = 'VALIDATED';

    #[Description('Sin validar')]
    #[BackgroundColor('')]
    case NOT_VALIDATED = 'NOT_VALIDATED';



}
