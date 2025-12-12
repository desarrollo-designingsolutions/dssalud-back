<?php

namespace App\Models;

use Altwaireb\World\Models\Country as Model;
use App\Traits\Cacheable;

class Country extends Model
{
    use Cacheable;
}
