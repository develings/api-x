<?php

namespace API\Command;

use API\API;
use API\Definition\DB;
use API\DynamoDB\Migrator;
use API\MySQL\Migrator as MySQLMigrator;
use Aws\AwsClient;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Aws\DynamoDb\Exception\DynamoDbException;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:migrate {--table=} {--id=} {--user=} {--tag=true}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'API Migrate';
    
    public function handle()
    {
        $tables = $this->option('table');
        
        $api = API::getInstance();
        
        if ($api->base->db->driver === DB::DRIVER_MYSQL) {
            $migrator = new MySQLMigrator($api);
        }
        
        $migrator->setInput($this->input);
        $migrator->setOutput($this->output);
        
        $this->line('');
        $this->line('<info>Starting</info> migration...');
        $this->line('');
        
        $tables = $tables ? explode(',', $tables) : [];
    
        $migrator->migrate($tables);
    
        $this->line('');
        $this->info('All done');
        $this->line('');
    }
}