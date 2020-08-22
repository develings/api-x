<?php

namespace API\DynamoDB;

use API\Definition\Base;
use Aws\AwsClient;
use Illuminate\Support\Facades\App;

class Migration
{
    /**
     * @var AwsClient
     */
    private $client;
    
    /**
     * @var array
     */
    private $config;
    
    /**
     * DynamoDBMigrator constructor.
     */
    public function __construct(array $config = [])
    {
        $this->client = App::make('aws')->createClient('dynamodb', $this->config = $config);
    }
    
    public function getDefinition(Base $base)
    {
        $definitions = [];
        foreach ($base->api as $api) {
            $definition = new Table($base->getTableName($api));
            $field = $api->getField($api->getIdentifier());
            dd($field);
            $definition->addKey();
            dd($definition);
            
            $definitions[] = $definition;
        }
        
        dd($definitions);
        
        return $definitions;
    }
    
    public function migrate(Base $base)
    {
        $definition = $this->getDefinition($base);
        
        dd($definition);
    }
    
    
    public function create(string $name, array $definition = [])
    {
        foreach($tables as $basic => $table) {
            $path = join(DIRECTORY_SEPARATOR, [base_path(), 'database', 'schemas', $table . '.json']);
            $this->line('Loading: ' . $path);
            $input = json_decode(file_get_contents($path), true);
            $env = config('app.env');
            if($env !== 'local') {
                $input['TableName'] = $table;
            }
            $error = json_last_error();
            if($error) {
                $this->line('ERROR: ' . $error);
            }
            $table = $this->client->createTable($input);
            if($tag) {
                $description = $table->get('TableDescription');
                echo "ARN: "; var_dump($description['TableArn']);
                if(!empty($description['TableArn'])) {
                    $tags = config('aws.tags', null);
                    if($tags !== null) {
                        echo "Waiting for 5s before calling TagResource ... ";
                        sleep(5); // wait, because it takes a moment for the table to become available to tag
                        echo "Done.\n";
                        $errors = 0;
                        while($errors < 3) {
                            try {
                                $tagged = $this->client->tagResource([
                                    'ResourceArn' => $description['TableArn'],
                                    "Tags" => $tags
                                ]);
                                break;
                            } catch(Exception $e) {
                                $this->line($e->getMessage());
                                $errors++;
                            }
                        }
                        echo "Tagged: "; var_dump($tagged);
                    }
                }
            }
            $this->line('Created ' . $table);
        }
    }
}