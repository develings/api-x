<?php

namespace ApiX\Facade;

use ApiX\Definition\Endpoint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Facade;

/**
 * @method \ApiX\ApiX load(string $file)
 * @method array openApiJson()
 * @method \ApiX\ApiX setRoutes()
 * @method Endpoint[] getApis()
 * @method User getUser()
 * @method Endpoint getEndpoint(string $name)
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
