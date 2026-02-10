<?php

namespace App\Enums\TypeEvent;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum TypeEventEnum: string
{
    use AttributableEnum;

    #[Description('Conciliación')]
    #[BackgroundColor('#f0ad4e')]
    case TYPE_EVENT_001 = 'TYPE_EVENT_001';

}
