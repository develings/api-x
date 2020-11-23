<?php

namespace API;

use API\Command\MakeApiCommand;
use API\Command\MakeModelCommand;
use API\Command\MigrateCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class APIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeApiCommand::class,
                MakeModelCommand::class,
                MigrateCommand::class,
            ]);
        }
    
        $app = $this->app;
        
        $middlewares = [
            'api.auth.member' => \API\Auth\AuthenticateMember::class,
            'api.auth' => \API\Auth\Authenticate::class,
        ];
        
        if ($app instanceof \Illuminate\Foundation\Application) {
            /** @var Router $router */
            $router = $app->get('router');
            foreach ($middlewares as $key => $middleware) {
                $router->aliasMiddleware($key, $middleware);
            }
        } else {
            // Lumen
            app()->routeMiddleware($middlewares);
        }
    }
}
