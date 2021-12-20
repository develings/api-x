<?php

namespace ApiX\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method load(string $file)
 * @method openApiJson()
 * @method setRoutes()
 * @method getApis()
 * @method getUser()
 * @method getEndpoint(string $name)
 */
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
