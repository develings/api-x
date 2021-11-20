<?php

namespace ApiX\Command;

use ApiX\Facade\ApiX;
use ApiX\Generate\Factory;
use Illuminate\Console\Command;

class MakeFactoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:make-factory {--table=} {--force=false}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make factory';
    
    public function handle()
    {
        $tables = $this->option('table');
        
        $api = ApiX::getInstance();
        
        $this->line('');
        $this->line('<info>Creating</info> factory class...');
        $this->line('');
        
        $tables = $tables ? explode(',', $tables) : [];
        
        if (!$tables) {
            $tables = array_keys($api->getApis());
        }
    
        $force = false;
        $forceParam = $this->option('force') ?: false;
        if ($forceParam && ($forceParam === 'true' || $forceParam === true)) {
            $force = true;
        }
        
        $creator = new Factory();
        foreach ($tables as $table) {
            $endpoint = $api->getEndpoint($table);
            if (!$endpoint) {
                $this->line(sprintf('<error> FAIL </error> %s definition not found', $table));
                continue;
            }
            
            $created = $creator->create($endpoint, $force);
            if ($created) {
                $this->line('<info>Success</info> ' . $endpoint->name);
            } else {
                $this->line('<error> FAIL </error> ' . $endpoint->name);
            }
        }
        
        $this->line('');
        $this->info('All done');
        $this->line('');
    }
}
