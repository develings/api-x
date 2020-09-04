<?php

namespace API;

use API\Command\MigrateCommand;
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
                MigrateCommand::class
            ]);
        }
    }
}
