<?php

namespace Tests;

use ApiX\ApiXServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ApiXServiceProvider::class];
    }
    
    /**
     * Setup the test environment.
     */
    //protected function setUp(): void
    //{
    //    parent::setUp();
    //
    //    $this->artisan('api:migrate')->run();
    //}
    
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('view.paths', [
            __DIR__ . '/../resources/views',
        ]);
        $app['config']->set('app.key', 'base64:Hupx3yAySikrM2/edkZQNQHslgDWYfiBfCuSThJ5SK8=');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
