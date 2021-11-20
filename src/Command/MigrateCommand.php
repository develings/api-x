<?php

namespace ApiX\Command;

use ApiX\API;
use ApiX\Definition\DB;
use ApiX\DynamoDB\Migrator;
use ApiX\MySQL\Migrator as MySQLMigrator;
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
    protected $signature = 'api:migrate {--table=} {--id=} {--user=} {--tag=true}  {--force=false}';
    
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
        
        $migrator = new MySQLMigrator($api);
        
        $migrator->setInput($this->input);
        $migrator->setOutput($this->output);
        
        $this->line('');
        $this->line('<info>Starting</info> migration...');
        $this->line('');
        
        $tables = $tables ? explode(',', $tables) : [];

        $force = false;
        $forceParam = $this->option('force') ?: false;
        if ($forceParam && ($forceParam === 'true' || $forceParam === true)) {
            $force = true;
        }

        $migrator->migrate($tables, $force);
    
        $this->line('');
        $this->info('All done');
        $this->line('');
    }
}
