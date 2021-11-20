<?php

namespace ApiX\Command;

use ApiX\API;
use ApiX\Definition\DB;
use ApiX\DynamoDB\Migrator;
use ApiX\Model;
use ApiX\MySQL\Migrator as MySQLMigrator;
use Aws\AwsClient;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Aws\DynamoDb\Exception\DynamoDbException;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:make-model {--table=}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make model';
    
    public function handle()
    {
        $tables = $this->option('table');
        
        $api = API::getInstance();
        
        $this->line('');
        $this->line('<info>Creating</info> model class...');
        $this->line('');
        
        $tables = $tables ? explode(',', $tables) : [];
        
        if (!$tables) {
            $tables = array_keys($api->getApis());
        }
        
        $modelCreator = new Model();
        foreach ($tables as $table) {
            $endpoint = $api->getEndpoint($table);
            if (!$endpoint) {
                $this->line(sprintf('<error> FAIL </error> %s definition not found', $table));
                continue;
            }
            
            $modelCreator->createModel($endpoint);
            $this->line('<info>Success</info> ' . $endpoint->name);
        }
        
        $this->line('');
        $this->info('All done');
        $this->line('');
    }
}
