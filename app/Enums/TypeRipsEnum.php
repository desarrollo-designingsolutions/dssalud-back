<?php

namespace App\Enums;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum TypeRipsEnum: string
{
    use AttributableEnum;

    #[BackgroundColor('asdasdsadsadasdasdsad')]
    #[Description('Automatico')]
    case AUTOMATIC = 'AUTOMATIC';

    #[Description('Manual')]
    case MANUAL = 'MANUAL';
}
