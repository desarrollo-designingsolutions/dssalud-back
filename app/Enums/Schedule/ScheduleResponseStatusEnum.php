<?php

namespace App\Enums\Schedule;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum ScheduleResponseStatusEnum: string
{
    use AttributableEnum;

    #[Description('Pendiente')]
    #[BackgroundColor('#fb9515')]

    case SCHEDULE_RESPONSE_STATUS_001 = 'SCHEDULE_RESPONSE_STATUS_001';

    #[Description('Aceptado')]
    #[BackgroundColor('#4caf50')]

    case SCHEDULE_RESPONSE_STATUS_002 = 'SCHEDULE_RESPONSE_STATUS_002';

    #[Description('Rechazado')]
    #[BackgroundColor('#c51162')]

    case SCHEDULE_RESPONSE_STATUS_003 = 'SCHEDULE_RESPONSE_STATUS_003';
}
