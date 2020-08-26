<?php

namespace API\Auth;

use API\API;
use API\Definition\Endpoint;
use Closure;
use Illuminate\Http\Request;

class Authenticate
{
    public function handle(Request $request, Closure $next, $s = null)
    {
        /** @var API $api */
        $api = app()->get(API::class);
        
        $routeParameters = $request->route()[2] ?? null;
        $endpointName = $routeParameters['api'] ?? null;
        
        if ($endpointName) {
            $endpoint = $api->getEndpoint($endpointName);
            if (!$endpoint) {
                return false;
            }
            
            
        }
        
        dd($routeParameters, $request->route());
        //dd(, $this, $next, $s);
        return $next($request);
    }
}