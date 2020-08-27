<?php

namespace API\Auth;

use API\API;
use API\Definition\Endpoint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            
            $authentication = $endpoint->authentication;
            $auths = collect($authentication);
            
            $auths->map(function($rule) use($request){
                $expression = explode(':', $rule);
                $method = $expression[0] ?? null;
                abort_unless($method, 500, 'Authentication method missing');
                
                $className = '\API\\Auth\\' . ucfirst($method);
                abort_unless(class_exists($className), 500, 'Authentication class does not exist');
                $parameters = explode(',', $expression[1] ?? '');
                
                $class = new $className(...$parameters);
                dd($class->handle($request));
            });
            
            dd($endpoint->authentication);
        }
        
        //dd($routeParameters, $request->route());
        //dd(, $this, $next, $s);
        return $next($request);
    }
}