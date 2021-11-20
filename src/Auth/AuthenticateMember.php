<?php

namespace ApiX\Auth;

use ApiX\ApiX;
use ApiX\Definition\Endpoint;
use ApiX\Definition\EndpointPath;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class AuthenticateMember
{
    public function handle(Request $request, Closure $next)
    {
        /** @var ApiX $api */
        $api = app()->get(ApiX::class);
        
        $route = $request->route();
        if ($route instanceof Route) {
            $routeParameters = $route->parameters();
            $routeName = $route->getAction('as');
        } else {
            $routeParameters = $route[2] ?? null;
            $routeName = $route[1]['as'] ?? null;
        }
    
        $routeName = str_replace('..', '.', $routeName);
        
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
        
        $methods = [
            'api.post' => 'create',
            'api.put' => 'update',
            'api.delete' => 'delete',
            'api.index' => 'index',
            'api.get' => 'get',
        ];

        if ($endpoint) {
            /** @var EndpointPath|false $endpointPath */
            $endpointPath = $endpoint->{$methods[$routeName]} ?? null;
            abort_if($endpointPath === false, 404);
    
            $pathAuth = $endpointPath->authentication ?? null;
            if ($endpointPath && $pathAuth) {
                if (is_array($pathAuth) && !isset($pathAuth['on'])) {
                    $auths->prepend(...$pathAuth);
                } else {
                    $auths->prepend($pathAuth);
                }
            }
        }
        
        foreach ($auths as $authOne) {
            $auth = $authOne;
            $inherit = true;
            if (is_array($auth)) {
                $auth = $authOne['on'];
                $inherit = $authOne['inherit'] ?? true;
            }
            $expression = explode(':', $auth);
            
            $method = $expression[0] ?? null;
            if ($method === 'none') {
                break; // not allowed for anyone
            }
            
            abort_unless($method, 501, 'Authentication method missing');

            $className = '\ApiX\\Auth\\' . ucfirst($method);
            abort_unless(class_exists($className), 501, 'Authentication class does not exist');
            $parameters = explode(',', $expression[1] ?? '');

            $class = new $className(...$parameters);
            
            if($class->handle($request)) {
                return $next($request);
            }
            
            if (!$inherit) {
                break;
            }
        }
        
        return abort(403);
    }
}
