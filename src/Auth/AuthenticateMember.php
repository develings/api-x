<?php

namespace API\Auth;

use API\API;
use API\Definition\Endpoint;
use API\Definition\EndpointPath;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthenticateMember
{
    public function handle(Request $request, Closure $next)
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
            'api.post' => 'create',
            'api.put' => 'update',
            'api.delete' => 'delete',
            'api.index' => 'index',
            'api.get' => 'get',
        ];
        
        /** @var EndpointPath $path */
        if($endpoint && ($path = $endpoint->{$methods[$routeName]} ?? null) && $pathAuth = $path->authentication) {
            if (is_array($pathAuth) && !isset($pathAuth['on'])) {
                $auths->prepend(...$pathAuth);
            } else {
                $auths->prepend($pathAuth);
            }
            //$auths->prepend($path->authentication);
            
            //
        }
        
        //dump($auths, $request);
        
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
            
            abort_unless($method, 500, 'Authentication method missing');

            $className = '\API\\Auth\\' . ucfirst($method);
            abort_unless(class_exists($className), 500, 'Authentication class does not exist');
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