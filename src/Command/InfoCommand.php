<?php

namespace ApiX\Command;

use ApiX\Facade\ApiX;
use Illuminate\Console\Command;

class InfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:info';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ApiX Migrate';
    
    public function handle()
    {
        $this->line('');
        $this->line('<info>ApiX X</info> info:');
        $this->line('');
        
        $this->line('Swagger URL: ' . route('api.swagger'));
        $this->line('OpenAPI URL: ' . route('api.openapi'));
        
        $this->line('');
    }
}
