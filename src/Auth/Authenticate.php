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
        
        $route = $request->route();
        $routeParameters = $route[2] ?? null;
        $endpointName = $routeParameters['api'] ?? null;
        $routeName = $route[1]['as'] ?? null;
        
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
        
        $methods = [
            'api.create' => 'create',
            'api.update' => 'update',
            'api.delete' => 'delete',
            'api.update' => 'update',
            'api.index' => 'index',
            'api.get' => 'get',
        ];
        
        /** @var EndpointPath $path */
        if($endpoint && ($path = $endpoint->{$methods[$routeName]} ?? null) && $path->authentication) {
            $auths->prepend($path->authentication);
        }
        
        foreach ($auths as $auth) {
            $expression = explode(':', $auth);
            $method = $expression[0] ?? null;
            if ($method === 'none') {
                break; // not allowed for anyone
            }
            
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