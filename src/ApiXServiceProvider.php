<?php

namespace ApiX;

use ApiX\Command\InfoCommand;
use ApiX\Command\MakeApiCommand;
use ApiX\Command\MakeModelCommand;
use ApiX\Command\MigrateCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ApiXServiceProvider extends ServiceProvider
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
                InfoCommand::class,
            ]);
        }
   
        $app = $this->app;
        
        $middlewares = [
            'api.auth.member' => \ApiX\Auth\AuthenticateMember::class,
            'api.auth' => \ApiX\Auth\Authenticate::class,
        ];
        
        if ($app instanceof \Illuminate\Foundation\Application) {
            /** @var Router $router */
            $router = $app->get('router');
            foreach ($middlewares as $key => $middleware) {
                $router->aliasMiddleware($key, $middleware);
            }
        } else {
            // Lumen
            $app->routeMiddleware($middlewares);
        }
    }
    
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api');
    }
}
