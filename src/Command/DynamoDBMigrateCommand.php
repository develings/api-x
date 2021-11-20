<?php

namespace ApiX\Command;

use ApiX\DynamoDB\Migrator;
use Aws\AwsClient;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Aws\DynamoDb\Exception\DynamoDbException;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DynamoDBMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dynamodb:migrate {--table=} {--id=} {--user=} {--tag=true}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DynamoDB Migrate';
    
    public function handle()
    {
        $tables = $this->option('table');
        
        if (!$tables) {
            // get All tables from
        }
        
        $migrator = new Migrator();
        
        $migrator->migrate();
    }
}
