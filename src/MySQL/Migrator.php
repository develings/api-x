<?php

namespace API\MySQL;

use API\API;
use API\Definition\Field;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Migrator
{
    use \Illuminate\Console\Concerns\InteractsWithIO;
    public $api;
    
    public function __construct(API $api)
    {
        $this->api = $api;
    }
    
    public function migrate(array $tables, $force = false)
    {
        // Schema::dropIfExists('users');
        
        // Schema::create('users', function (Blueprint $table) {
        //           $table->id();
        //           $table->string('name');
        //           $table->string('email')->unique();
        //           $table->timestamp('email_verified_at')->nullable();
        //           $table->string('password');
        //           $table->rememberToken();
        //           $table->timestamps();
        //       });
        
        $data = $this->api->base;
        
        foreach ($data->api as $table) {
            
            if ($tables && !in_array($table->name, $tables, true) ) {
                // Skip table because it is not selected to be migrated
                $this->line(sprintf('<info>Skipping</info> %s', $table->name));
                continue;
            }
            
            $tableName = $this->api->base->getTableName($table);
            
            if ($force) {
                Schema::disableForeignKeyConstraints();
                Schema::dropIfExists($tableName);
                Schema::enableForeignKeyConstraints();
            }
            
            //continue;
            $exists = Schema::hasTable($tableName);
            if ($exists) {
                $this->line(sprintf('<error> FAIL </error> Table (<info>%s</info>) already exists.', $table->name));
                continue;
            }
            
            $this->line(sprintf('Migrating <info>%s</info>...', $tableName));
            
            //continue;
            
            // For testing purposes only
            //$blueprint = new Blueprint($tableName);
            //$blueprint->create();
            //$blueprint = $this->create($table, $blueprint);
            //dd($blueprint);
    
    
            Schema::create($tableName, function(Blueprint $blueprint) use($table) {
                $this->create($table, $blueprint);
            });
            //
            $this->line(sprintf('Migrated <info>%s</info>.', $tableName));
            //dd($blueprint);
        }
        
        return true;
    }
    
    public function getTableName(string $tableName)
    {
        $prefix = $this->definition['db_prefix'] ?? '';
        
        return $prefix . $tableName;
    }
    
    /**
     * @param $field
     * @param Blueprint $blueprint
     * @param $key
     *
     * @return Blueprint
     */
    public function parseColumnDefinition(Field $field, Blueprint $blueprint, $key)
    {
        $parts = explode('|', $field->definition);
        $column = null;
        $modifiers = [
            'after', 'autoIncrement', 'charset', 'collation', 'comment',
            'first', 'nullable', 'storedAs', 'unsigned', 'useCurrent', 'virtualAs',
            'generatedAs', 'always', 'spatialIndex', 'index', 'persisted', 'primary',
            'type',
            //'unique', 'default'
        ];
        
        foreach ($parts as $part) {
            $parameters = explode(':', $part);
            $method = array_shift($parameters);
            
            //dump($key . '-'. $field, $parameters);
            $column = $column ?: $blueprint;
            if( $parameters ) {
                $parameters = explode(',', $parameters[0]);
            }
            
            // handle special modifiers such as unsigned, index
            if (in_array($method, $modifiers, true)) {
                $column->$method(...$parameters);
                continue;
            }
            
            if( !method_exists($column, $method)  ) {
                // method does not exist in laravel
                
                if (!$this->methods($column, $key, $method, $parameters)) {
                    // custom method does not exist in php api
                    $this->error('Method does not exist: ' . $method);
                }
                
                continue;
            }
            
            $column = $column->$method($key, ...$parameters);
        }
        
        return $blueprint;
    }
    
    public function methods($column, $key, $method, $parameters = null)
    {
        if ($method === 'password') {
            $column->string($key, ...$parameters);
            return true;
        }
    
        if ($method === 'email') {
            $column->string($key, ...$parameters);
            return true;
        }
    
        return false;
    }
    
    /**
     * @param \API\Definition\Endpoint $table
     * @param Blueprint $blueprint
     *
     * @return Blueprint
     */
    private function create(\API\Definition\Endpoint $table, Blueprint $blueprint)
    {
        $fields = $table->fields;
        foreach ($fields as $key => $field) {
            $this->parseColumnDefinition($field, $blueprint, $key);
        }
        
        $relations = $table->relations ?? [];
        //$relations = [];
        foreach ($relations as $relationName => $relation) {
            $parts = explode('|', $relation->definition);
            $column = null;
            foreach ($parts as $part) {
                $parameters = explode(':', $part);
                $method = array_shift($parameters);
                
                $validRelationTypes = ['belongsTo', 'hasOne'];
                if( !in_array($method, $validRelationTypes, true) ) {
                    continue;
                }
                
                $column = $column ?: $blueprint;
                //if( !method_exists($column, $method) ) {
                //    dump('method does not exist: ' . $method);
                //    continue;
                //}
                
                if( $parameters ) {
                    $parameters = explode(',', $parameters[0]);
                }
                
                //if( $method === 'belongsTo' || $method === 'hasOne' ) {
                    $fieldName = $parameters[1] ?? $relationName . '_id';
                    $relationEndpoint = $this->api->getEndpoint($parameters[0]);
                    if( !$relationEndpoint ) {
                        $this->error(sprintf('Relation table %s does not exist', $parameters[0]));
                        continue;
                    }
                    $relationTableName = $this->api->base->getTableName($relationEndpoint);
                    $field = $column->foreignId($fieldName);
                    if ($relation->isNullable()) {
                        $field = $field->nullable();
                    }
                    if ($relation->isUnique()) {
                        $field = $field->unique();
                    }
                    $field->constrained($relationTableName);
                //}
    
                //if( $method === 'hasOne' ) {
                //    $fieldName = $parameters[1] ?? $relationName . '_id';
                //    $relationEndpoint = $this->api->getEndpoint($parameters[0]);
                //    if( !$relationEndpoint ) {
                //        $this->error(sprintf('Relation table %s does not exist', $parameters[0]));
                //        continue;
                //    }
                //    $relationTableName = $this->api->base->getTableName($relationEndpoint);
                //    $column->foreignId($fieldName)->nullable()->constrained($relationTableName);
                //}
            }
        }
    
        if( $table->timestamps ?? false ) {
            $blueprint->timestamps();
        }
    
        if( $table->soft_deletes ?? false ) {
            $blueprint->softDeletes();
        }
        
        return $blueprint;
    }
}
