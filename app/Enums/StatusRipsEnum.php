<?php

namespace App\Enums;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusRipsEnum: string
{
    use AttributableEnum;

    //OTROS
    #[Description('Incompleto')]
    #[BackgroundColor('')]
    case INCOMPLETE = 'INCOMPLETE';

    #[Description('Completado')]
    #[BackgroundColor('')]
    case COMPLETED = 'COMPLETED';

    #[Description('Sin enviar')]
    #[BackgroundColor('')]
    case NOT_SENT = 'NOT SENT';

    #[Description('En proceso')]
    #[BackgroundColor('warning')]
    case IN_PROCESS = 'IN PROCESS';

    #[Description('Procesado')]
    #[BackgroundColor('success')]
    case PROCESSED = 'PROCESSED';

    #[Description('Sin validar')]
    #[BackgroundColor('')]
    case NOT_VALIDATED = 'NOT VALIDATED';

    #[Description('Validado')]
    #[BackgroundColor('')]
    case VALIDATED = 'VALIDATED';


    //PENDING
    #[Description('Pendiente de XML')]
    #[BackgroundColor('')]
    case PENDING_XML = 'PENDING XML';

    #[Description('Pendiente de excel')]
    #[BackgroundColor('')]
    case PENDING_EXCEL = 'PENDING EXCEL';


    //ERROR
    #[Description('Error NIT')]
    #[BackgroundColor('')]
    case ERROR_NIT = 'ERROR_NIT';

    #[Description('Error XML')]
    #[BackgroundColor('')]
    case ERROR_XML = 'ERROR_XML';

    #[Description('Error Zip')]
    #[BackgroundColor('')]
    case ERROR_ZIP = 'ERROR_ZIP';

    #[Description('Error Excel')]
    #[BackgroundColor('')]
    case ERROR_EXCEL = 'ERROR_EXCEL';

}
