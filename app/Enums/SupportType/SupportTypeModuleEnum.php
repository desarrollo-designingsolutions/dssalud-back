<?php

namespace App\Enums\SupportType;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum SupportTypeModuleEnum: string
{
    use AttributableEnum;

    #[Description('Radicación')]
    #[BackgroundColor('#f0ad4e')]
    case SUPPORT_TYPE_MODULE_001 = 'SUPPORT_TYPE_MODULE_001';

    #[Description('Conciliación')]
    #[BackgroundColor('#3ba044ff')]
    case SUPPORT_TYPE_MODULE_002 = 'SUPPORT_TYPE_MODULE_002';

}
