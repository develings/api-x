<?php

namespace ApiX\Command;

use ApiX\ApiX;
use ApiX\Definition\DB;
use ApiX\DynamoDB\Migrator;
use ApiX\Model;
use ApiX\MySQL\Migrator as MySQLMigrator;
use Aws\AwsClient;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use Aws\DynamoDb\Exception\DynamoDbException;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class MakeApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:make {--name=} {--force}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make ApiX json file';
    
    public function handle()
    {
        $name = $this->option('name') ?: 'api';
        $file = "$name.json";
        $force = $this->option('force') ?: false;
        
        if (!$force && file_exists(base_path($file))) {
            $this->error("\nApiX File already exists ($file)");
            return $this->line('');
        }
        
        $this->line('');
        $this->line('<info>Creating</info> api json file...');
    
        $stub = File::get(__DIR__ . '/../../resources/stubs/ApiJson.stub');
    
        File::put(
            $path = base_path($file),
            $stub
        );
    
        $this->line('<info>Success</info> Created json file: ' . $path );
        
        $this->line('');
        $this->info('All done');
        $this->line('');
    }
}
