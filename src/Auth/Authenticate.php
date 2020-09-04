<?php

namespace API\Auth;

use API\API;
use API\Definition\Endpoint;
use API\Definition\EndpointPath;
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
            
        $default = $api->base->authentication ?: [];
        if (!is_array($default)) {
            $default = [$default];
        }

        $endpoint = $endpointName ? $api->getEndpoint($endpointName) : null;
        if ($endpoint) {
            $authentication = $endpoint->authentication ?: [];
            if (!is_array($authentication)) {
                $authentication = [$authentication];
            }
        }
        
        $auths = collect(array_merge($authentication ?? [], $default));
        
        $method = strtolower($request->method());
        $methods = [
            'post' => 'create',
            'put' => 'update',
            'delete' => 'delete',
            'patch' => 'update',
            'get' => 'index',
        ];
        
        /** @var EndpointPath $path */
        if($endpoint && ($path = $endpoint->{$methods[$method]} ?? null) && $path->authentication) {
            $auths->prepend($path->authentication);
        }

        //dd($auths, $s, $request->method());
        foreach ($auths as $auth) {
            $expression = explode(':', $auth);
            $method = $expression[0] ?? null;
            abort_unless($method, 500, 'Authentication method missing');

            $className = '\API\\Auth\\' . ucfirst($method);
            abort_unless(class_exists($className), 500, 'Authentication class does not exist');
            $parameters = explode(',', $expression[1] ?? '');

            $class = new $className(...$parameters);
            
            if($class->handle($request)) {
                return $next($request);
            }
        }
            
        return abort(403);
    }
}