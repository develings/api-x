<?php

namespace ApiX\Facade;

use Illuminate\Support\Facades\Facade;

class ApiX extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ApiX';
    }
}
