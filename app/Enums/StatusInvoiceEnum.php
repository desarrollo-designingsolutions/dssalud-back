<?php

namespace App\Enums;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusInvoiceEnum: string
{
    use AttributableEnum;

    //ERROR
    #[Description('Error Excel')]
    #[BackgroundColor('#F4511E')]
    case ERROR_EXCEL = 'EXCEL ERROR';

    #[Description('Error XML')]
    #[BackgroundColor('')]
    case ERROR_XML = 'XML ERROR';

    //OTROS
    #[Description('Completado')]
    #[BackgroundColor('#F4511E')]
    case COMPLETED = 'COMPLETED';

    #[Description('Incompleto')]
    #[BackgroundColor('#F4511E')]
    case INCOMPLETE = 'INCOMPLETE';

    #[Description('Validado')]
    #[BackgroundColor('')]
    case VALIDATED = 'VALIDATED';

    #[Description('Sin validar')]
    #[BackgroundColor('')]
    case NOT_VALIDATED = 'NOT VALIDATED';



}
