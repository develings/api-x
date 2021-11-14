<?php

namespace API\Command;

use API\API;
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
    protected $description = 'API Migrate';
    
    public function handle()
    {
        $api = API::getInstance();
        
        $this->line('');
        $this->line('<info>API X</info> info:');
        $this->line('');
        
        $this->line('Swagger URL: ' . route('api.swagger'));
        $this->line('OpenAPI URL: ' . route('api.openapi'));
        
        $this->line('');
    }
}
