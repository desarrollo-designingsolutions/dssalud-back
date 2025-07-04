<?php

namespace App\Enums\Schedule;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum ScheduleResponseStatusEnum: string
{
    use AttributableEnum;

    #[Description('Pendiente')]
    case SCHEDULE_RESPONSE_STATUS_001 = 'SCHEDULE_RESPONSE_STATUS_001';

    #[Description('Aceptado')]
    case SCHEDULE_RESPONSE_STATUS_002 = 'SCHEDULE_RESPONSE_STATUS_002';

    #[Description('Rechazado')]
    case SCHEDULE_RESPONSE_STATUS_003 = 'SCHEDULE_RESPONSE_STATUS_003';

}
