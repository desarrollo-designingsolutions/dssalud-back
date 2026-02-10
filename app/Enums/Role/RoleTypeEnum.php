<?php

namespace App\Enums\Role;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum RoleTypeEnum: string
{
    use AttributableEnum;

    #[Description('Auditor')]
    case ROLE_TYPE_001 = 'ROLE_TYPE_001';

    #[Description('Prestador')]
    case ROLE_TYPE_002 = 'ROLE_TYPE_002';
}
